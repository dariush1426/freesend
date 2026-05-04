<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPayment;
use App\Support\PlanPolicy;
use App\Support\Payments\ZibalPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionPaymentController extends Controller
{
    public function zibalCallback(Request $request, ZibalPaymentGateway $gateway): RedirectResponse
    {
        $trackId = trim((string) $request->query('trackId', ''));
        $orderNumber = trim((string) $request->query('orderId', $request->query('order', '')));

        if ($trackId === '' && $orderNumber === '') {
            return redirect()
                ->route('subscriptions.upgrade')
                ->withErrors(['payment' => __('messages.subscriptions.callback_not_found')]);
        }

        $payment = SubscriptionPayment::query()
            ->with(['order.user', 'order.plan', 'order.subscription'])
            ->when($trackId !== '', fn ($query) => $query->where('track_id', $trackId))
            ->when($trackId === '' && $orderNumber !== '', function ($query) use ($orderNumber): void {
                $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', $orderNumber));
            })
            ->latest('id')
            ->first();

        if (! $payment) {
            return redirect()
                ->route('subscriptions.upgrade')
                ->withErrors(['payment' => __('messages.subscriptions.callback_not_found')]);
        }

        $order = $payment->order;
        $callbackSuccess = (int) $request->query('success', 0) === 1;
        $gatewayStatus = (int) $request->query('status', 0);

        $payment->forceFill([
            'callback_success' => $callbackSuccess,
            'gateway_status' => $gatewayStatus,
            'callback_payload' => $request->query(),
        ])->save();

        if ($order->status === SubscriptionOrder::STATUS_PAID && $order->subscription) {
            return redirect()
                ->route('subscriptions.upgrade')
                ->with('status', __('messages.subscriptions.payment_already_processed', [
                    'plan' => $order->plan->name,
                ]));
        }

        if (! $callbackSuccess) {
            $status = $gatewayStatus === 2
                ? SubscriptionOrder::STATUS_CANCELLED
                : SubscriptionOrder::STATUS_FAILED;
            $paymentStatus = $gatewayStatus === 2
                ? SubscriptionPayment::STATUS_CANCELLED
                : SubscriptionPayment::STATUS_FAILED;

            $payment->forceFill([
                'status' => $paymentStatus,
                'failed_at' => now(),
                'message' => __('messages.subscriptions.payment_callback_rejected', [
                    'status' => $gatewayStatus,
                ]),
            ])->save();

            $order->forceFill([
                'status' => $status,
                'failed_at' => now(),
                'notes' => __('messages.subscriptions.payment_callback_rejected', [
                    'status' => $gatewayStatus,
                ]),
            ])->save();

            return redirect()
                ->route('subscriptions.upgrade')
                ->withErrors(['payment' => __('messages.subscriptions.payment_failed')]);
        }

        try {
            $verifyResponse = $gateway->verify((string) $payment->track_id);
        } catch (Throwable $exception) {
            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_FAILED,
                'failed_at' => now(),
                'message' => $exception->getMessage(),
            ])->save();

            $order->forceFill([
                'status' => SubscriptionOrder::STATUS_FAILED,
                'failed_at' => now(),
                'notes' => $exception->getMessage(),
            ])->save();

            return redirect()
                ->route('subscriptions.upgrade')
                ->withErrors(['payment' => __('messages.subscriptions.verify_failed', [
                    'message' => $exception->getMessage(),
                ])]);
        }

        $verifyResult = (int) ($verifyResponse['result'] ?? 0);
        $verifyStatus = (int) ($verifyResponse['status'] ?? $gatewayStatus);

        if (! in_array($verifyResult, [100, 201], true) || $verifyStatus !== 1) {
            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_FAILED,
                'gateway_result' => $verifyResult,
                'gateway_status' => $verifyStatus,
                'verify_response' => $verifyResponse,
                'failed_at' => now(),
                'message' => (string) ($verifyResponse['message'] ?? __('messages.subscriptions.payment_failed')),
            ])->save();

            $order->forceFill([
                'status' => SubscriptionOrder::STATUS_FAILED,
                'failed_at' => now(),
                'notes' => (string) ($verifyResponse['message'] ?? __('messages.subscriptions.payment_failed')),
            ])->save();

            return redirect()
                ->route('subscriptions.upgrade')
                ->withErrors(['payment' => __('messages.subscriptions.payment_failed')]);
        }

        DB::transaction(function () use ($order, $payment, $verifyResponse, $verifyResult, $verifyStatus): void {
            $subscription = $order->subscription;

            if (! $subscription) {
                $subscription = PlanPolicy::assignPlan(
                    $order->user,
                    $order->plan,
                    null,
                    null,
                    'Paid order '.$order->order_number,
                );
            }

            $paidAt = $this->resolveGatewayPaidAt($verifyResponse);

            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_VERIFIED,
                'gateway_result' => $verifyResult,
                'gateway_status' => $verifyStatus,
                'verify_response' => $verifyResponse,
                'paid_at' => $paidAt,
                'message' => (string) ($verifyResponse['message'] ?? ''),
            ])->save();

            $order->forceFill([
                'user_subscription_id' => $subscription->id,
                'status' => SubscriptionOrder::STATUS_PAID,
                'paid_at' => $paidAt,
                'notes' => (string) ($verifyResponse['message'] ?? 'Payment verified'),
            ])->save();
        });

        return redirect()
            ->route('subscriptions.upgrade')
            ->with('status', __('messages.subscriptions.payment_success', [
                'plan' => $order->plan->name,
            ]));
    }

    private function resolveGatewayPaidAt(array $verifyResponse): Carbon
    {
        $value = $verifyResponse['paidAt'] ?? $verifyResponse['createdAt'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return now();
        }
    }
}
