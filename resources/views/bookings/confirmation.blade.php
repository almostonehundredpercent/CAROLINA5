@extends('layouts.app')
@section('content')
<section class="confirmation">
    <div class="check">✓</div>
    <span class="eyebrow">BOOKING RECEIVED</span>
    <h1>Your reservation request was received, {{ $booking->guest_name ?? auth()->user()?->name }}.</h1>
    <p>Keep your reference number. Carolina will review availability and contact you with the next steps.</p>
    <div class="confirmation-card">
        <div><small>Reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in_at?->format('M j, g A') }} - {{ $booking->check_out_at?->format('M j, g A') }}</b></div>
        <div><small>Total</small><b>₱{{ number_format($booking->total_amount) }}</b>@if($booking->discount_amount > 0)<small>{{ $booking->promo_code }} saved ₱{{ number_format($booking->discount_amount) }}</small>@endif</div>
    </div>
    @include('bookings.partials.location-map', ['booking' => $booking])
    <div class="receipt-actions">
        @auth
            <a class="button" href="{{ route('bookings.index') }}">View my bookings</a>
        @else
            <a class="button" href="{{ route('bookings.lookup') }}">Find booking later</a>
        @endauth
        @if($booking->status === 'confirmed' || in_array($booking->payment_status, ['paid', 'refunded'], true))
            <a class="button button-outline" href="{{ route('bookings.receipt', $booking) }}">View receipt</a>
        @endif
    </div>
</section>
@endsection
