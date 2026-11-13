@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/home-premium.css') }}?v={{ filemtime(public_path('css/home-premium.css')) }}">
<link rel="stylesheet" href="{{ asset('css/promo-campaign.css') }}?v={{ filemtime(public_path('css/promo-campaign.css')) }}">
@endpush

@section('content')
<section class="hero home-hero">
    <div class="home-hero-slideshow" data-interval="3000" role="region" aria-roledescription="carousel" aria-label="Photos of Carolina Transient and Airbnb">
        <img class="home-hero-slide is-active" src="{{ asset('images/home-hero/lobby-1-1800.jpg') }}" srcset="{{ asset('images/home-hero/lobby-1-900.jpg') }} 900w, {{ asset('images/home-hero/lobby-1-1800.jpg') }} 1800w" sizes="100vw" width="1800" height="1013" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
        <img class="home-hero-slide" src="{{ asset('images/home-hero/lobby-2-1800.jpg') }}" srcset="{{ asset('images/home-hero/lobby-2-900.jpg') }} 900w, {{ asset('images/home-hero/lobby-2-1800.jpg') }} 1800w" sizes="100vw" width="1800" height="1013" alt="" aria-hidden="true" loading="lazy" decoding="async">
        <img class="home-hero-slide" src="{{ asset('images/home-hero/lobby-3-1800.jpg') }}" srcset="{{ asset('images/home-hero/lobby-3-900.jpg') }} 900w, {{ asset('images/home-hero/lobby-3-1800.jpg') }} 1800w" sizes="100vw" width="1800" height="1013" alt="" aria-hidden="true" loading="lazy" decoding="async">
    </div>
    <div class="home-hero-glow" aria-hidden="true"></div>
    <div class="hero-copy">
        <span class="eyebrow">CAROLINA · TABACO CITY</span>
        <h1>Stay well.<br>Spend less.</h1>
        <p>Affordable bed &amp; breakfast and boarding-house stays in Tabaco City.</p>
        <div class="home-hero-actions">
            <a class="button home-hero-primary" href="{{ route('booking.start') }}"><span>Book now</span><span class="home-hero-arrow" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M3.5 10h12m-5-5 5 5-5 5" /></svg></span></a>
            @auth<a class="home-hero-link" href="{{ route('bookings.index') }}">My bookings</a>@else<a class="home-hero-link" href="#find-booking">Already booked?</a>@endauth
        </div>
        <div class="home-hero-details" aria-label="Carolina stay highlights"><span><b>18</b> stay options</span><span><b>4.8</b> guest rating</span><span><b>24/7</b> booking access</span></div>
    </div>
</section>
<script src="{{ asset('js/home-hero-slideshow.js') }}?v={{ filemtime(public_path('js/home-hero-slideshow.js')) }}" defer></script>

<style>.home-room-carousel{display:flex;gap:25px;overflow-x:auto;padding:2px 2px 16px;scroll-snap-type:x mandatory;scrollbar-width:none}.home-room-carousel::-webkit-scrollbar{display:none}.home-room-carousel .room-card{flex:0 0 calc((100% - 50px)/3);min-width:0;scroll-snap-align:start}@media(max-width:800px){.home-room-carousel{gap:16px;padding-bottom:13px}.home-room-carousel .room-card{flex-basis:84%}}</style>
<section class="home-booking-wrap" aria-label="Find available rooms">
    <form class="home-booking-search" method="GET" action="{{ route('booking.start') }}">
        <input type="hidden" name="mode" value="hourly">
        <div class="home-search-field home-search-date" data-home-date-picker>
            <label id="home-date-label" for="home-date-trigger">Check-in date</label>
            <input id="home-date" type="hidden" name="check_in" min="{{ now('Asia/Manila')->toDateString() }}" data-home-date>
            <button id="home-date-trigger" type="button" class="home-search-trigger home-date-trigger" data-home-date-trigger aria-haspopup="dialog" aria-expanded="false" aria-labelledby="home-date-label home-date-value"><span id="home-date-value" data-home-date-value>Choose date</span><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4.5" width="14" height="13" rx="2"/><path d="M6.5 2.5v4M13.5 2.5v4M3 8.5h14"/></svg></button>
            <div class="home-date-calendar" data-home-date-calendar role="dialog" aria-label="Choose check-in date" hidden></div>
        </div>
        <div class="home-search-field home-custom-select" data-home-select>
            <label id="home-stay-label" for="home-stay-trigger">Stay length</label>
            <select class="home-native-select" id="home-stay-native" name="stay" data-home-stay aria-hidden="true" tabindex="-1">
                <option value="3" data-mode="hourly" data-display="3 hours" selected>3 hours · air-conditioned</option>
                <option value="6" data-mode="hourly" data-display="6 hours">6 hours · air-conditioned</option>
                <option value="8" data-mode="hourly" data-display="8 hours">8 hours · air-conditioned</option>
                <option value="12" data-mode="hourly" data-display="12 hours">12 hours · air-conditioned</option>
                <option value="22" data-mode="hourly" data-display="22 hours" data-detail="Fan ₱450 · Air-conditioned ₱850">22 hours · Fan ₱450 / Air-conditioned ₱850</option>
                <option value="month" data-mode="hourly" data-display="1 month">1 month · air-conditioned</option>
            </select>
            <button id="home-stay-trigger" type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="home-stay-label home-stay-value"><span id="home-stay-value" data-home-select-value></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5.5 7.5 4.5 4.5 4.5-4.5"/></svg></button>
            <div class="home-select-list" data-home-select-list role="listbox" aria-labelledby="home-stay-label" hidden></div>
        </div>
        <div class="home-search-field home-custom-select" data-home-select>
            <label id="home-time-label" for="home-time-trigger">Check-in time</label>
            <select class="home-native-select" id="home-time-native" name="check_in_time" aria-hidden="true" tabindex="-1">
                @for($hour = 6; $hour < 24; $hour++)
                    <option value="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00" data-display="{{ \Carbon\Carbon::createFromTime($hour)->format('g:i A') }}" @selected($hour === 12)>{{ \Carbon\Carbon::createFromTime($hour)->format('g:i A') }}</option>
                @endfor
            </select>
            <button id="home-time-trigger" type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="home-time-label home-time-value"><span id="home-time-value" data-home-select-value></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5.5 7.5 4.5 4.5 4.5-4.5"/></svg></button>
            <div class="home-select-list" data-home-select-list role="listbox" aria-labelledby="home-time-label" hidden></div>
        </div>
        <div class="home-search-field home-custom-select" data-home-select>
            <label id="home-guests-label" for="home-guests-trigger">Guests</label>
            <select class="home-native-select" id="home-guests-native" name="guests" aria-hidden="true" tabindex="-1">
                <option value="" data-display="Any guests">Any guests</option>
                @foreach(range(1, 6) as $guests)
                    <option value="{{ $guests }}" data-display="{{ $guests }}+ guests">{{ $guests }}+ guest{{ $guests > 1 ? 's' : '' }}</option>
                @endforeach
            </select>
            <button id="home-guests-trigger" type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="home-guests-label home-guests-value"><span id="home-guests-value" data-home-select-value></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5.5 7.5 4.5 4.5 4.5-4.5"/></svg></button>
            <div class="home-select-list" data-home-select-list role="listbox" aria-labelledby="home-guests-label" hidden></div>
        </div>
        <div class="home-search-field home-custom-select" data-home-select>
            <label id="home-sort-label" for="home-sort-trigger">Sort by price</label>
            <select class="home-native-select" id="home-sort-native" name="sort" aria-hidden="true" tabindex="-1">
                <option value="recommended" data-display="Recommended">Recommended</option>
                <option value="price_asc" data-display="Lowest price">Lowest price first</option>
                <option value="price_desc" data-display="Highest price">Highest price first</option>
            </select>
            <button id="home-sort-trigger" type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="home-sort-label home-sort-value"><span id="home-sort-value" data-home-select-value></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5.5 7.5 4.5 4.5 4.5-4.5"/></svg></button>
            <div class="home-select-list" data-home-select-list role="listbox" aria-labelledby="home-sort-label" hidden></div>
        </div>
        <button type="submit" class="home-search-submit">Check availability <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4.5 4.5M8 10.5h5M10.5 8v5"></path></svg></button>
    </form>
</section>
<script src="{{ asset('js/home-booking-search.js') }}?v={{ filemtime(public_path('js/home-booking-search.js')) }}" defer></script>

<section class="ber-promo ber-promo-home" aria-labelledby="home-ber-promo-title"><div class="ber-promo-layout"><div class="ber-promo-copy"><span class="ber-promo-kicker">22-hour package · Code CAROLINA</span><h2 id="home-ber-promo-title">Free breakfast with the 22-hour air-conditioned package.</h2><p>Choose the <strong>₱850 air-conditioned package</strong> for 22 hours to receive complimentary breakfast. The <strong>₱450 fan package</strong> does not include breakfast. Use code <strong>CAROLINA</strong> for 10% off, subject to the promo terms.</p><div class="ber-promo-highlights"><span>22-hour air-conditioned stay</span><span>₱850 for up to 2 guests</span><span>Breakfast included</span><span>Fan option: ₱450, no breakfast</span></div></div><a class="button" href="{{ route('rooms.index') }}">Choose a room <span aria-hidden="true">→</span></a></div></section>

<section class="section home-rooms" id="rooms"><div class="section-heading"><div><span class="eyebrow">STAY YOUR WAY</span><h2>Rooms that feel easy to settle into.</h2></div><div class="home-room-heading-actions">@if($featuredRooms->count() > 1)<div class="home-room-navigation" aria-label="Featured room navigation"><button type="button" data-room-previous aria-label="Previous rooms" aria-controls="featured-rooms">‹</button><button type="button" data-room-next aria-label="Next rooms" aria-controls="featured-rooms">›</button></div>@endif<a href="{{ route('rooms.index') }}" class="text-link">View every room <span aria-hidden="true">→</span></a></div></div><div class="home-room-carousel" id="featured-rooms" role="region" aria-roledescription="carousel" aria-label="Featured rooms" tabindex="0">@forelse($featuredRooms as $room)<article class="room-card"><picture>@if($roomWebpSrcset = $room->responsiveWebpSrcset())<source type="image/webp" srcset="{{ $roomWebpSrcset }}" sizes="(max-width: 800px) 84vw, 33vw">@endif<img src="{{ $room->versionedImageUrl() }}" alt="{{ $room->name }}" @if($roomSrcset = $room->responsiveImageSrcset()) srcset="{{ $roomSrcset }}" sizes="(max-width: 800px) 84vw, 33vw" @endif loading="lazy" decoding="async"></picture><div class="room-card-body"><span>{{ $room->room_type }} · Up to {{ $room->booking_guest_limit }} guests</span><h3>{{ $room->name }}</h3><div class="room-availability-line"><span class="room-availability-badge {{ $room->availability_reason ? 'is-unavailable' : 'is-available' }}">{{ $room->availability_reason ? 'Unavailable now' : 'Available now' }}</span>@if($room->availability_reason)<small>{{ $room->availability_reason }}</small>@endif</div>@include('rooms.partials.card-rating', ['room' => $room])<p>₱{{ number_format(\App\Support\StayPricing::startingPrice($room)) }} <small>Starting price · {{ \App\Support\StayPricing::startingPackageLabel($room) }}</small></p><a href="{{ route('rooms.show', $room) }}">{{ $room->availability_reason ? 'Check dates' : 'View room' }} <span aria-hidden="true">→</span></a></div></article>@empty<p>Rooms will appear here after the first database seed.</p>@endforelse</div></section>
@guest<section class="section home-lookup" id="find-booking"><div class="empty-state"><div><span class="eyebrow">ALREADY BOOKED?</span><h2>Find your stay in seconds.</h2><p>Use your email address and booking reference to view your reservation anytime—no account required.</p></div><a class="button" href="{{ route('bookings.lookup') }}">Find booking <span aria-hidden="true">→</span></a></div></section>@endguest
<section class="benefits section home-benefits" id="about"><div class="section-heading center"><div><span class="eyebrow">A LITTLE MORE COMFORT</span><h2>Everything you need, already here.</h2><p>Simple thoughtful touches, included with your stay.</p></div></div><div class="benefit-grid"><div><b aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 3v8M7 3v8M4 7h3M5.5 11v10M15 3v7c0 2 1.3 3 3 3h1v8M19 3v10"/></svg></b><h3>Free breakfast</h3><p>Included only with the 22-hour air-conditioned package at ₱850.</p></div><div><b aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 4h6M11 4V2h2v2M8 7h8v13H8zM6 10h2M16 10h2M11 11h2M11 15h2"/></svg></b><h3>Hygiene kits</h3><p>Essential personal-care items for your stay.</p></div><div><b aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 9.5a13 13 0 0 1 18 0M6.5 13a8 8 0 0 1 11 0M10 16.5a3 3 0 0 1 4 0M12 20h.01"/></svg></b><h3>Free Wi-Fi</h3><p>Stay connected throughout your visit.</p></div><div><b aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="m10 8 5 3.5-5 3.5zM8 21h8"/></svg></b><h3>Free Netflix</h3><p>Relax and stream your favorite shows.</p></div><div><b aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 21V3h11v18M8 21h11M13 12h.01"/></svg></b><h3>Private CR</h3><p>Enjoy the privacy and comfort of your own bathroom.</p></div></div></section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.querySelector('.home-room-carousel');
    if (!carousel) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (carousel.children.length < 2) return;
    carousel.scrollLeft = 0;

    const gap = () => Number.parseFloat(getComputedStyle(carousel).gap) || 0;
    const step = () => carousel.querySelector('.room-card')?.getBoundingClientRect().width + gap() || carousel.clientWidth;
    const moveByControl = direction => {
        const maxScroll = carousel.scrollWidth - carousel.clientWidth;
        const left = carousel.scrollLeft;
        const behavior = reducedMotion.matches ? 'auto' : 'smooth';

        if (direction < 0 && left <= 2) {
            carousel.scrollTo({ left: maxScroll, behavior });
            return;
        }
        if (direction > 0 && left >= maxScroll - 2) {
            carousel.scrollTo({ left: 0, behavior });
            return;
        }
        carousel.scrollTo({ left: Math.max(0, Math.min(maxScroll, left + direction * step())), behavior });
    };
    document.querySelector('[data-room-previous]')?.addEventListener('click', () => moveByControl(-1));
    document.querySelector('[data-room-next]')?.addEventListener('click', () => moveByControl(1));
});
</script>
<script>document.addEventListener('DOMContentLoaded',()=>{const roomCalendar=document.getElementById('home-room-calendar');if(!roomCalendar)return;const refresh=()=>{if(!document.hidden&&roomCalendar.value)roomCalendar.dispatchEvent(new Event('change'))};setInterval(refresh,15000);document.addEventListener('visibilitychange',refresh)});</script>
<script src="{{ asset('js/home-touch-cards.js') }}?v={{ filemtime(public_path('js/home-touch-cards.js')) }}" defer></script>
<section class="section home-location" aria-labelledby="booking-location-title">
    @include('bookings.partials.location-map', ['locationEyebrow' => 'HOW TO FIND US'])
</section>
<section class="section home-reviews" aria-labelledby="home-reviews-title">
    <div class="section-heading">
        <div>
            <span class="eyebrow">GUEST REVIEWS</span>
            <h2 id="home-reviews-title">Experiences shared by our guests.</h2>
            <p>Recent reviews from completed Carolina stays.</p>
        </div>
    </div>
    <div class="review-list">
        @forelse($publishedReviews as $review)
            <article class="public-review home-review-card">
                <div class="home-review-rating">
                    <span class="review-stars" role="img" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    <small>Verified stay</small>
                </div>
                @if(filled($review->comment))
                    <p class="home-review-comment">{{ $review->comment }}</p>
                @else
                    <p class="home-review-comment home-review-no-comment">Guest shared a {{ $review->rating }}-star rating.</p>
                @endif
                <div class="home-review-author">
                    <div><b>{{ $review->guest_name ?: 'Verified guest' }}</b><small>{{ $review->room?->name ?? 'Carolina stay' }}</small></div>
                    @if($review->approved_at)<time datetime="{{ $review->approved_at->toDateString() }}">{{ $review->approved_at->format('M Y') }}</time>@endif
                </div>
            </article>
        @empty
            <p class="empty-review home-review-empty">Guest reviews will appear here after they are approved.</p>
        @endforelse
    </div>
</section>
@endsection
