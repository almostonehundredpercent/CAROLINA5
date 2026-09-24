<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * The payment ledger is authoritative for money that has actually been
 * recorded. Booking totals describe the value reserved, not money collected.
 */
final class FinancialSummary
{
    /** @return array{booking_value: float, paid: float, refunds: float, net_collected: float, outstanding: float} */
    public static function forBooking(Booking $booking): array
    {
        $payments = $booking->relationLoaded('payments') ? $booking->payments : $booking->payments()->get();
        $paid = (float) $payments->where('status', 'paid')->sum('amount');
        $refunds = (float) $payments->where('status', 'refunded')->sum('amount');

        return [
            'booking_value' => (float) $booking->total_amount,
            'paid' => $paid,
            'refunds' => $refunds,
            'net_collected' => $paid - $refunds,
            'outstanding' => $booking->status === 'cancelled' ? 0.0 : max(0.0, (float) $booking->total_amount - $paid + $refunds),
        ];
    }

    /** Amount that can still be returned without exceeding recorded payments. */
    public static function refundableAmount(Booking $booking): float
    {
        $payments = $booking->relationLoaded('payments') ? $booking->payments : $booking->payments()->get();

        return max(0.0, (float) $payments->where('status', 'paid')->sum('amount')
            - (float) $payments->where('status', 'refunded')->sum('amount'));
    }

    /** @return array{paid: float, refunds: float, net_collected: float} */
    public static function forPayments(Collection $payments): array
    {
        $paid = (float) $payments->where('status', 'paid')->sum('amount');
        $refunds = (float) $payments->where('status', 'refunded')->sum('amount');

        return ['paid' => $paid, 'refunds' => $refunds, 'net_collected' => $paid - $refunds];
    }

    /** @return array{booked_value: float, confirmed_value: float, paid_revenue: float, refunds: float, net_collected_revenue: float, outstanding_balance: float} */
    public static function forBookings(Collection $bookings): array
    {
        $bookings->loadMissing('payments');
        $ledger = self::forPayments($bookings->flatMap->payments);

        return [
            'booked_value' => (float) $bookings->where('status', '!=', 'cancelled')->sum('total_amount'),
            'confirmed_value' => (float) $bookings->where('status', 'confirmed')->sum('total_amount'),
            'paid_revenue' => $ledger['paid'],
            'refunds' => $ledger['refunds'],
            'net_collected_revenue' => $ledger['net_collected'],
            'outstanding_balance' => (float) $bookings->where('status', '!=', 'cancelled')->sum(fn (Booking $booking) => self::forBooking($booking)['outstanding']),
        ];
    }
}
