@extends('layouts.app')
@section('content')
<section class="auth-page">
    <form class="auth-card" method="POST" action="{{ route('bookings.lookup.submit') }}">
        @csrf
        <span class="eyebrow">GUEST BOOKING</span>
        <h1>Find your booking.</h1>
        <p>Enter the email address and reference code from your confirmation email.</p>
        <label>Email address<input type="email" name="email" required></label>
        <label>Booking reference<input name="reference" placeholder="CAR-XXXXXXXX" required></label>
        <p class="lookup-help">Can't find your reference? <a href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer">Message Carolina on Facebook</a> and include the email used for your reservation.</p>
        <button class="button">View booking</button>
        <p class="auth-switch"><a href="{{ route('home') }}">← Back to home</a></p>
    </form>
</section>
@endsection
