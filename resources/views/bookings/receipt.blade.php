@extends('layouts.app')
@section('content')
<section class="confirmation receipt-page">
    <span class="eyebrow">PAYMENT RECEIPT</span>
    <h1>Save this receipt.</h1>
    <p>Screenshot this page for your records, then confirm that you have completed payment.</p>
    <div class="confirmation-card receipt-card">
        <div><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Payment method</small><b>{{ strtoupper($booking->payment_method) }}</b></div>
        <div><small>Guest</small><b>{{ $booking->guest_name ?? $booking->user?->name }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Check-in</small><b>{{ $booking->check_in->format('M j, Y') }}</b></div>
        <div><small>Check-out</small><b>{{ $booking->check_out->format('M j, Y') }}</b></div>
        <div><small>Nights</small><b>{{ $booking->nights }}</b></div>
        <div><small>Amount due</small><b>₱{{ number_format($booking->total_amount) }}</b></div>
    </div>
    <p class="receipt-note">Your booking is pending until the Carolina team verifies payment.</p>
    <div class="receipt-actions"><button class="button light" type="button" onclick="window.print()">Print / save receipt</button><form method="POST" action="{{ route('bookings.confirm-payment', $booking) }}">@csrf<button class="button" type="submit">I've paid - confirm booking</button></form></div>
</section>
@endsection
