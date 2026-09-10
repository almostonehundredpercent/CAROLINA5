@extends('layouts.app')

@section('content')
<style>.receipt-card{max-width:760px;margin:0 auto 18px}.receipt-summary{max-width:760px;margin:0 auto 28px;padding:22px;border:1px solid var(--line);border-radius:10px;background:#fff;text-align:left}.receipt-summary-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:16px 0}.receipt-summary-grid div{padding:14px;border-radius:7px;background:var(--sand)}.receipt-summary-grid small{display:block;color:var(--muted);margin-bottom:5px}.receipt-summary-grid b{font-size:1.15rem}.receipt-status{display:inline-flex;padding:6px 10px;border-radius:20px;background:#fff0d4;color:#8d5700;font-size:.8rem;font-weight:700}.receipt-note{margin:0;color:var(--muted)}@media(max-width:600px){.receipt-summary{padding:17px}.receipt-summary-grid{grid-template-columns:1fr}}</style>
<section class="confirmation receipt-page">
    <span class="eyebrow">BOOKING RECEIVED</span>
    <h1>Your receipt is ready.</h1>
    <p>Your reservation request has been recorded. No online payment is needed.</p>

    <div class="confirmation-card receipt-card">
        <div><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Stay type</small><b>{{ $booking->booking_type === 'hourly' ? $booking->hours . ' hours' : $booking->nights . ' night' . ($booking->nights === 1 ? '' : 's') }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in_at && $booking->check_out_at ? $booking->check_in_at->format('M j, g A') . ' – ' . $booking->check_out_at->format('M j, g A') : $booking->check_in->format('M j') . ' – ' . $booking->check_out->format('M j, Y') }}</b></div>
    </div>

    <section class="receipt-summary">
        <span class="receipt-status">Reservation recorded</span>
        <div class="receipt-summary-grid">
            <div><small>Booking total</small><b>₱{{ number_format($booking->total_amount, 2) }}</b></div>
            <div><small>Payment</small><b>No payment required now</b></div>
        </div>
        @if($booking->add_ons)<p class="receipt-note"><strong>Add-ons:</strong> {{ collect($booking->add_ons)->pluck('label')->join(', ') }}</p>@endif
        <p class="receipt-note">Please keep your reference number. Carolina will review your reservation and contact you with any next steps.</p>
    </section>
    <div class="receipt-actions">
        <a class="button button-outline" href="{{ route('home') }}">Back to home</a>
        <button class="button light" type="button" onclick="window.print()">Print / save receipt</button>
    </div>
</section>
@endsection
