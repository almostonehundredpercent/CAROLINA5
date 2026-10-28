<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PayMongoCheckout
{
    private const BASE_URL = 'https://api.paymongo.com/v1';

    /** Create a hosted GCash checkout. Amounts are in centavos. */
    public function create(Booking $booking, ?string $attemptId = null, ?BookingTimeAdjustment $adjustment = null): array
    {
        $secretKey = $this->secretKey();
        $startedAt = hrtime(true);
        Log::info('PayMongo checkout request started.', [
            'attempt_id' => $attemptId,
            'booking_id' => $booking->id,
        ]);

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
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
                            'cancel_url' => route('bookings.paymongo.return', array_filter(['booking' => $booking, 'outcome' => 'cancelled', 'adjustment' => $adjustment?->id])),
                            'success_url' => route('bookings.paymongo.return', array_filter(['booking' => $booking, 'outcome' => 'success', 'adjustment' => $adjustment?->id])),
                            'description' => $adjustment ? 'Carolina stay time change '.$booking->reference : 'Carolina reservation '.$booking->reference,
                            'merchant' => 'Carolina',
                            'payment_method_types' => ['gcash'],
                            'reference_number' => $booking->reference,
                            'send_email_receipt' => false,
                            'show_description' => true,
                            'show_line_items' => true,
                            'line_items' => [[
                                'amount' => (int) round(((float) ($adjustment?->amount ?? $booking->total_amount)) * 100),
                                'currency' => 'PHP',
                                'description' => $adjustment
                                    ? ($adjustment->type === 'early_checkin' ? 'Early check-in' : 'Extra checkout time')
                                    : ($booking->room?->name ?? 'Carolina stay'),
                                'name' => $adjustment ? 'Carolina stay time change' : 'Carolina reservation',
                                'quantity' => 1,
                            ]],
                        ],
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::warning('PayMongo checkout request failed before a response.', [
                'attempt_id' => $attemptId,
                'booking_id' => $booking->id,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        Log::info('PayMongo checkout response received.', [
            'attempt_id' => $attemptId,
            'booking_id' => $booking->id,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'http_status' => $response->status(),
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

    /** Request expiry of a hosted checkout session after Carolina's booking hold ends. */
    public function expire(string $checkoutId): array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->withBasicAuth($this->secretKey(), '')
            ->post(self::BASE_URL.'/checkout_sessions/'.$checkoutId.'/expire');

        if ($response->failed() || ! is_array($response->json('data'))) {
            report(new RuntimeException('PayMongo checkout expiry failed: '.$response->status()));
            throw new RuntimeException('PayMongo could not expire this checkout session yet.');
        }

        return $response->json();
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
