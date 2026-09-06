@extends('layouts.app')

@section('content')
<section class="booking-page">
    <div class="booking-form">
        <span class="eyebrow">RESERVE {{ strtoupper($room->name) }}</span>
        <h1>Complete your booking.</h1>

        @guest
            <div class="checkout-choice">
                <a class="{{ !$isGuest ? 'active' : '' }}" href="{{ route('login') }}">Sign in</a>
                <a class="{{ $isGuest ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1]) }}">Continue as guest</a>
            </div>
            @if(!$isGuest)
                <p>Sign in to book with your account, or continue as a guest without creating one.</p>
                <a class="button" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1]) }}">Continue as guest</a>
            @endif
        @endguest

        @auth
            <p>Book using your Carolina account.</p>
        @endauth

        @if(auth()->check() || $isGuest)
            <form method="POST" action="{{ route('bookings.store', $room) }}">
                @csrf
                <input type="hidden" name="checkout_type" value="{{ $isGuest ? 'guest' : 'account' }}">
                <label>Check in<input name="check_in" type="date" value="{{ old('check_in') }}" min="{{ now()->toDateString() }}" required></label>
                <label>Check out<input name="check_out" type="date" value="{{ old('check_out') }}" min="{{ now()->addDay()->toDateString() }}" required></label>
                <label>Guests<select name="guests">@for($i = 1; $i <= $room->guests; $i++)<option value="{{ $i }}" @selected(old('guests') == $i)>{{ $i }} guest{{ $i > 1 ? 's' : '' }}</option>@endfor</select></label>

                @if($isGuest)
                    <hr>
                    <h3>Guest and billing information</h3>
                    <label>Full name<input name="guest_name" value="{{ old('guest_name') }}" required></label>
                    <label>Email address<input type="email" name="guest_email" value="{{ old('guest_email') }}" required></label>
                    <label>Philippine phone number<input type="tel" name="guest_phone" value="{{ old('guest_phone') }}" placeholder="09169907895" pattern="[0-9+() -]+" title="Use 09169907895, 639169907895, or +63 916-990-7895" required></label>
                    <label>Street address<input name="billing_street" value="{{ old('billing_street') }}" required></label>
                    <label>City<input name="billing_city" value="{{ old('billing_city') }}" required></label>
                    <label>Province<input name="billing_province" value="{{ old('billing_province') }}" required></label>
                    <label>Postal code<input name="billing_postal_code" value="{{ old('billing_postal_code') }}" inputmode="numeric" pattern="[0-9]{4}" required></label>
                    <label class="checkbox">
                        <input name="billing_verified" type="checkbox" value="1" @checked(old('billing_verified')) required>
                        <span><strong>Confirm billing details</strong><small>I confirm that my billing contact and address are correct.</small></span>
                    </label>
                @endif

                <label>Payment method<select name="payment_method"><option value="gcash">GCash</option><option value="card">Credit/debit card</option><option value="cash">Pay at property</option></select></label>
                <label>Special request<textarea name="special_request" rows="3">{{ old('special_request') }}</textarea></label>
                <button class="button">Continue to payment</button>
            </form>
        @endif
    </div>

    <aside class="booking-summary">
        <img src="{{ $room->image_url }}" alt="{{ $room->name }}">
        <h3>{{ $room->name }}</h3>
        <p>{{ $room->room_type }} · Up to {{ $room->guests }} guests</p>
        <strong>₱{{ number_format($room->price_per_night) }} <small>/ night</small></strong>
        <hr>
        <small>Final total is calculated from your dates. A GCash payment link is sent after the reservation is reviewed.</small>
    </aside>
</section>
@endsection
