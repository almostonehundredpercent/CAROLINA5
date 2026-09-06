@extends('layouts.app')

@section('content')
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">CAROLINA TRANSIENT & AIRBNB</span>
        <h1>Smart stay,<br>spend less.</h1>
        <p>Comfortable, affordable lodging in the heart of Tabaco City.</p>
        <a class="button light" href="{{ route('rooms.index') }}">Find your room <span>→</span></a>
    </div>
</section>

<section class="search-wrap">
    <form class="search-card" method="GET" action="{{ route('rooms.index') }}">
        <label>Check in<input id="home-check-in" type="date" name="check_in" min="{{ now()->toDateString() }}" required></label>
        <label>Check out<input id="home-check-out" type="date" name="check_out" min="{{ now()->addDay()->toDateString() }}" required></label>
        <label>Guests<select name="guests"><option value="1">1 guest</option><option value="2">2 guests</option><option value="3">3 guests</option><option value="4">4+ guests</option></select></label>
        <button class="button" type="submit">Search rooms</button>
    </form>
</section>

<section class="section" id="rooms">
    <div class="section-heading">
        <div><span class="eyebrow">STAY YOUR WAY</span><h2>Comfortable stays, just like home.</h2></div>
        <a href="{{ route('rooms.index') }}" class="text-link">Explore all rooms →</a>
    </div>
    <div class="room-grid">
        @forelse($featuredRooms as $room)
            <article class="room-card">
                <img src="{{ $room->image_url }}" alt="{{ $room->name }}">
                <div class="room-card-body">
                    <span>{{ $room->room_type }} · Up to {{ $room->guests }} guests</span>
                    <h3>{{ $room->name }}</h3>
                    <p>₱{{ number_format($room->price_per_night) }} <small>/ night</small></p>
                    <a href="{{ route('rooms.show', $room) }}">View room →</a>
                </div>
            </article>
        @empty
            <p>Rooms will appear here after the first database seed.</p>
        @endforelse
    </div>
</section>

<section class="section" id="find-booking">
    <div class="empty-state">
        <span class="eyebrow">ALREADY BOOKED?</span>
        <h2>Find your booking.</h2>
        <p>Use your email address and booking reference to view your reservation details anytime - no account required.</p>
        <a class="button" href="{{ route('bookings.lookup') }}">Find your booking</a>
    </div>
</section>

<section class="benefits section" id="about">
    <div class="section-heading center"><div><span class="eyebrow">WHAT WE OFFER</span><h2>Everything you need for an easy stay.</h2></div></div>
    <div class="benefit-grid">
        <div><b>⌂</b><h3>Clean rooms</h3><p>Thoughtfully prepared before every arrival.</p></div>
        <div><b>◌</b><h3>Free Wi-Fi</h3><p>Stay connected throughout your visit.</p></div>
        <div><b>✦</b><h3>Pet-friendly</h3><p>Bring your companion along with you.</p></div>
        <div><b>▣</b><h3>Parking</h3><p>Convenient on-site parking for guests.</p></div>
    </div>
</section>
@endsection
