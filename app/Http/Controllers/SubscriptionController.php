<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Support\PlanPolicy;
use App\Support\Payments\ZibalPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request, ZibalPaymentGateway $gateway): View
    {
        $profile = PlanPolicy::profileForUser($request->user());

        return view('subscriptions.index', [
            'planProfile' => $profile,
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('price_amount')
                ->orderBy('name')
                ->get(),
            'gatewayReady' => false,
            'paidPurchasesAvailable' => $this->paidPurchasesAvailable(),
            'recentOrders' => SubscriptionOrder::query()
                ->with(['plan', 'latestPayment'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function purchase(Request $request, SubscriptionPlan $plan, ZibalPaymentGateway $gateway): RedirectResponse
    {
        abort_unless($plan->is_active, 404);

        $user = $request->user();
        $planProfile = PlanPolicy::profileForUser($user);

        if (($planProfile['plan']?->id ?? null) === $plan->id) {
            return back()->with('status', __('messages.subscriptions.already_on_plan', [
                'plan' => $plan->name,
            ]));
        }

        if (! $plan->isPaid()) {
            PlanPolicy::assignPlan($user, $plan, null, null, 'Selected free plan');

            return redirect()
                ->route('subscriptions.upgrade')
                ->with('status', __('messages.subscriptions.free_plan_activated', [
                    'plan' => $plan->name,
                ]));
        }

        if (! $this->paidPurchasesAvailable()) {
            return back()->withErrors([
                'payment' => __('messages.subscriptions.paid_purchase_disabled_for_launch'),
            ]);
        }

        if (! $gateway->isEnabled()) {
            return back()->withErrors([
                'payment' => __('messages.subscriptions.gateway_disabled'),
            ]);
        }

        if (! $gateway->isConfigured()) {
            return back()->withErrors([
                'payment' => __('messages.subscriptions.gateway_not_configured'),
            ]);
        }

        $order = SubscriptionOrder::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_number' => SubscriptionOrder::generateOrderNumber(),
            'gateway' => 'zibal',
            'amount' => (int) $plan->price_amount,
            'currency' => 'IRR',
            'status' => SubscriptionOrder::STATUS_PENDING,
            'description' => 'Subscription purchase for '.$plan->name,
        ]);

        $payment = SubscriptionPayment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'zibal',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'amount' => (int) $plan->price_amount,
        ]);

        try {
            $gatewayResponse = $gateway->requestPayment($order->load(['user', 'plan']), $payment);
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

            return back()->withErrors([
                'payment' => __('messages.subscriptions.request_failed', [
                    'message' => $exception->getMessage(),
                ]),
            ]);
        }

        $redirectUrl = (string) ($gatewayResponse['redirect_url'] ?? '');

        if ($redirectUrl === '') {
            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_FAILED,
                'failed_at' => now(),
                'message' => 'Gateway redirect URL is empty.',
            ])->save();

            $order->forceFill([
                'status' => SubscriptionOrder::STATUS_FAILED,
                'failed_at' => now(),
                'notes' => 'Gateway redirect URL is empty.',
            ])->save();

            return back()->withErrors([
                'payment' => __('messages.subscriptions.request_failed', [
                    'message' => 'Gateway redirect URL is empty.',
                ]),
            ]);
        }

        $payment->forceFill([
            'status' => SubscriptionPayment::STATUS_REDIRECTED,
            'track_id' => (string) $gatewayResponse['track_id'],
            'gateway_result' => (int) ($gatewayResponse['response']['result'] ?? 0),
            'request_payload' => $gatewayResponse['payload'],
            'request_response' => $gatewayResponse['response'],
            'message' => (string) ($gatewayResponse['response']['message'] ?? ''),
        ])->save();

        $order->forceFill([
            'status' => SubscriptionOrder::STATUS_REDIRECTED,
            'notes' => 'Redirected to gateway with track '.$payment->track_id,
        ])->save();

        return redirect()->away($redirectUrl);
    }

    private function paidPurchasesAvailable(): bool
    {
        return false;
    }
}
