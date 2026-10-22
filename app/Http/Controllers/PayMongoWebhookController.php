<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\PayMongoPaymentReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongoPaymentReconciler $payments)
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        $mode = config('services.paymongo.mode');
        if ($secret === '' || ! in_array($mode, ['test', 'live'], true)) {
            Log::error('PayMongo webhook is not configured.');

            return response()->json(['received' => false, 'message' => 'Webhook is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $signatureParts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $signatureParts[$key] = $value;
        }

        $version = $mode === 'live' ? 'li' : 'te';
        $timestamp = $signatureParts['t'] ?? '';
        $providedSignature = $signatureParts[$version] ?? '';
        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        if ($timestamp === '' || $providedSignature === '' || ! hash_equals($expectedSignature, $providedSignature)) {
            return response()->json(['received' => false, 'message' => 'Invalid webhook signature.'], 401);
        }

        try {
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['received' => false, 'message' => 'Invalid webhook payload.'], 400);
        }

        $eventAttributes = data_get($event, 'data.attributes', []);
        if (data_get($eventAttributes, 'livemode') !== ($mode === 'live')) {
            Log::warning('PayMongo webhook mode did not match application configuration.');

            return response()->json(['received' => false, 'message' => 'Webhook mode mismatch.'], 422);
        }

        if (data_get($eventAttributes, 'type') !== 'checkout_session.payment.paid') {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $checkout = data_get($eventAttributes, 'data');
        $checkoutId = data_get($checkout, 'id');
        $reference = data_get($checkout, 'attributes.reference_number');
        if (! is_string($checkoutId) || ! is_string($reference)) {
            return response()->json(['received' => true, 'matched' => false]);
        }

        $booking = Booking::where('reference', $reference)->first();
        if (! $booking) {
            Log::notice('PayMongo paid event did not match a Carolina booking.', ['reference' => $reference]);

            return response()->json(['received' => true, 'matched' => false]);
        }

        $payment = $booking->payments()
            ->where('method', 'gcash')
            ->where('reference', 'paymongo:'.$checkoutId)
            ->first();
        if (! $payment) {
            Log::notice('PayMongo paid event did not match a stored checkout session.', ['booking_id' => $booking->id]);

            return response()->json(['received' => true, 'matched' => false]);
        }

        if ($payment->status === 'paid') {
            return response()->json(['received' => true, 'matched' => true, 'already_recorded' => true]);
        }

        if (! $payments->isMatchingPaidCheckout($booking, $payment, ['data' => $checkout])) {
            Log::warning('PayMongo paid event did not match the stored amount or booking.', ['booking_id' => $booking->id]);

            return response()->json(['received' => true, 'matched' => false]);
        }

        $recorded = $payments->recordPaid($booking, $payment);
        $isPaid = $recorded || $payment->fresh()->status === 'paid';

        return response()->json(['received' => true, 'matched' => $isPaid]);
    }
}
