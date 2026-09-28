<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoCheckout
{
    private const BASE_URL = 'https://api.paymongo.com/v1';

    /** Create a hosted GCash checkout. Amounts are in centavos. */
    public function create(Booking $booking): array
    {
        $secretKey = $this->secretKey();
        $response = Http::acceptJson()
            ->timeout(15)
            ->withBasicAuth($secretKey, '')
            ->post(self::BASE_URL.'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => array_filter([
                            'name' => $booking->guest_name ?? $booking->user?->name,
                            'email' => $booking->guest_email ?? $booking->user?->email,
                            'phone' => $booking->guest_phone,
                        ]),
                        'cancel_url' => route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'cancelled']),
                        'success_url' => route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success']),
                        'description' => 'Carolina reservation '.$booking->reference,
                        'payment_method_types' => ['gcash'],
                        'reference_number' => $booking->reference,
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'line_items' => [[
                            'amount' => (int) round(((float) $booking->total_amount) * 100),
                            'currency' => 'PHP',
                            'description' => $booking->room?->name ?? 'Carolina stay',
                            'name' => 'Carolina reservation',
                            'quantity' => 1,
                        ]],
                    ],
                ],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('PayMongo checkout creation failed: '.$response->status()));
            throw new RuntimeException('Online checkout is temporarily unavailable. Please try again shortly.');
        }

        $checkout = $response->json('data');
        $id = data_get($checkout, 'id');
        $url = data_get($checkout, 'attributes.checkout_url');
        if (! is_string($id) || ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            report(new RuntimeException('PayMongo returned an incomplete checkout session.'));
            throw new RuntimeException('Online checkout is temporarily unavailable. Please try again shortly.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /** Retrieve a checkout session from PayMongo; never trust browser return parameters. */
    public function retrieve(string $checkoutId): array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->withBasicAuth($this->secretKey(), '')
            ->get(self::BASE_URL.'/checkout_sessions/'.$checkoutId);

        if ($response->failed() || ! is_array($response->json('data'))) {
            report(new RuntimeException('PayMongo checkout lookup failed: '.$response->status()));
            throw new RuntimeException('We could not confirm the payment yet. Please try again in a moment.');
        }

        return $response->json();
    }

    public function isPaid(array $checkout): bool
    {
        $statuses = [
            data_get($checkout, 'data.attributes.payment_intent.attributes.status'),
            data_get($checkout, 'data.attributes.payments.0.attributes.status'),
            data_get($checkout, 'data.attributes.payment.attributes.status'),
        ];

        return in_array('succeeded', $statuses, true) || in_array('paid', $statuses, true);
    }

    private function secretKey(): string
    {
        $mode = config('services.paymongo.mode');
        $secretKey = (string) config('services.paymongo.secret_key');
        if ($mode !== 'test' || ! str_starts_with($secretKey, 'sk_test_')) {
            throw new RuntimeException('Online test checkout is not configured.');
        }

        return $secretKey;
    }
}
