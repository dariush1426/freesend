<?php

namespace App\Support\Payments;

use App\Models\Setting;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class ZibalPaymentGateway
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function isEnabled(): bool
    {
        return Setting::getValue('zibal_enabled', 'false') === 'true';
    }

    public function isConfigured(): bool
    {
        return $this->testModeEnabled() || trim((string) Setting::getValue('zibal_merchant', '')) !== '';
    }

    public function requestPayment(SubscriptionOrder $order, SubscriptionPayment $payment): array
    {
        $payload = [
            'merchant' => $this->merchant(),
            'amount' => $order->amount,
            'callbackUrl' => route('subscriptions.payments.zibal.callback', [
                'order' => $order->order_number,
            ]),
            'description' => $order->description ?: ('Subscription purchase for '.$order->plan->name),
            'orderId' => $order->order_number,
        ];

        if (! empty($order->user?->mobile)) {
            $payload['mobile'] = $order->user->mobile;
        }

        if (! empty($order->user?->email)) {
            $payload['email'] = $order->user->email;
        }

        $response = $this->http
            ->acceptJson()
            ->timeout(15)
            ->post($this->requestUrl(), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' while requesting payment.');
        }

        $data = $response->json();
        $result = (int) ($data['result'] ?? 0);
        $trackId = (string) ($data['trackId'] ?? '');

        if ($result !== 100 || $trackId === '') {
            throw new RuntimeException((string) ($data['message'] ?? 'Payment request was rejected.'));
        }

        return [
            'payload' => $payload,
            'response' => $data,
            'track_id' => $trackId,
            'redirect_url' => rtrim($this->startUrl(), '/').'/'.$trackId,
        ];
    }

    public function verify(string $trackId): array
    {
        $payload = [
            'merchant' => $this->merchant(),
            'trackId' => $trackId,
        ];

        $response = $this->http
            ->acceptJson()
            ->timeout(15)
            ->post($this->verifyUrl(), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' while verifying payment.');
        }

        return $response->json();
    }

    private function merchant(): string
    {
        if ($this->testModeEnabled()) {
            return 'zibal';
        }

        $merchant = trim((string) Setting::getValue('zibal_merchant', ''));

        if ($merchant === '') {
            throw new RuntimeException('Zibal merchant is not configured.');
        }

        return $merchant;
    }

    private function testModeEnabled(): bool
    {
        return Setting::getValue('zibal_test_mode', 'true') === 'true';
    }

    private function requestUrl(): string
    {
        return rtrim((string) Setting::getValue('zibal_request_url', 'https://gateway.zibal.ir/v1/request'), '/');
    }

    private function startUrl(): string
    {
        return rtrim((string) Setting::getValue('zibal_start_url', 'https://gateway.zibal.ir/start'), '/');
    }

    private function verifyUrl(): string
    {
        return rtrim((string) Setting::getValue('zibal_verify_url', 'https://gateway.zibal.ir/v1/verify'), '/');
    }
}
