@extends('layouts.app')
@section('content')
<section class="confirmation">
    @include('bookings.partials.context-bar', ['backUrl' => auth()->check() && auth()->id() === $booking->user_id ? route('bookings.index') : ($booking->room ? route('rooms.show', $booking->room) : route('rooms.index')), 'backLabel' => auth()->check() && auth()->id() === $booking->user_id ? 'My bookings' : 'room details', 'roomName' => $booking->room?->name ?? 'Room unavailable', 'bookingReference' => $booking->reference])
    <div class="check">✓</div>
    <span class="eyebrow">BOOKING RECEIVED</span>
    <h1>Your reservation request was received, {{ $booking->guest_name ?? auth()->user()?->name }}.</h1>
    <p>Keep your reference number. Carolina will review availability and contact you with the next steps.</p>
    <div class="confirmation-card">
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
        @if($booking->status === 'confirmed' || in_array($booking->payment_status, ['paid', 'partial', 'refunded'], true))
            <a class="button button-outline" href="{{ route('bookings.receipt', $booking) }}">View receipt</a>
        @endif
    </div>
</section>
@endsection
