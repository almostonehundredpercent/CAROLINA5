@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-pagination.css') }}?v={{ filemtime(public_path('css/booking-pagination.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer-profile.css') }}?v={{ filemtime(public_path('css/customer-profile.css')) }}">
@endpush
@section('content')
<section class="page-hero slim customer-profile-hero">
    <div class="customer-profile-head">
        <span class="eyebrow">YOUR ACCOUNT</span>
        <h1>Welcome back, {{ auth()->user()->name }}!</h1>
        <p>Manage your profile and view your Carolina stays.</p>
    </div>
</section>
<section class="section compact customer-profile">
    <div class="customer-profile-actions" aria-label="Quick actions">
        <a class="customer-profile-action" href="{{ route('booking.start') }}"><span aria-hidden="true">＋</span><strong>New booking</strong><small>Find your next stay</small></a>
        <a class="customer-profile-action" href="#your-bookings"><span aria-hidden="true">▣</span><strong>Your stays</strong><small>View reservations and receipts</small></a>
        <a class="customer-profile-action" href="#contact"><span aria-hidden="true">✉</span><strong>Contact Carolina</strong><small>Get help with your stay</small></a>
    </div>

    <nav class="customer-profile-tabs" aria-label="Profile sections">
        <a href="#profile-information">Profile information</a>
        <a href="#your-bookings">My bookings</a>
    </nav>

    <article class="customer-profile-information" id="profile-information">
        <div class="customer-profile-card-heading"><div><span class="profile-kicker">ACCOUNT DETAILS</span><h2>Personal information</h2></div><span class="profile-member-badge">Member since {{ auth()->user()->created_at->format('Y') }}</span></div>
        <dl class="customer-profile-details">
            <div><dt>Full name</dt><dd>{{ auth()->user()->name }}</dd></div>
            <div><dt>Email address</dt><dd>{{ auth()->user()->email }}</dd></div>
            <div><dt>Email status</dt><dd>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}</dd></div>
            <div><dt>Account created</dt><dd>{{ auth()->user()->created_at->format('F j, Y') }}</dd></div>
        </dl>
    </article>

    <section class="customer-profile-stays" id="your-bookings" aria-labelledby="your-bookings-title">
        <div class="customer-profile-section-heading"><div><span class="profile-kicker">YOUR STAYS</span><h2 id="your-bookings-title">My bookings</h2><p>See your reservations, payment details, and stay updates in one place.</p></div><span class="profile-stay-count">{{ $bookings->total() }} {{ \Illuminate\Support\Str::plural('booking', $bookings->total()) }}</span></div>
        <div class="booking-list">
@forelse($bookings as $booking)
    <article class="booking-row">
        <picture>@if($roomWebpSrcset = $booking->room->responsiveWebpSrcset())<source type="image/webp" srcset="{{ $roomWebpSrcset }}" sizes="(max-width: 800px) 80px, 140px">@endif<img src="{{ $booking->room->image_url }}" alt="{{ $booking->room->name }}" @if($roomSrcset = $booking->room->responsiveImageSrcset()) srcset="{{ $roomSrcset }}" sizes="(max-width: 800px) 80px, 140px" @endif loading="lazy" decoding="async"></picture>
        <div class="booking-details">
            @php($holdExpired = $booking->hasExpiredHold() || $booking->isExpiredHoldRecord())
            <span class="status {{ $holdExpired ? 'cancelled' : $booking->status }}">{{ $holdExpired ? 'Payment hold expired' : ucfirst($booking->status) }}</span>
            <h3>{{ $booking->room->name }}</h3>
            <p>{{ $booking->check_in->format('M j, Y') }} - {{ $booking->check_out->format('M j, Y') }} · {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }} · {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</p>
            <small>Reference: {{ $booking->reference }}</small>
        </div>
        <div class="booking-actions">
            <strong>₱{{ number_format($booking->total_amount) }}</strong>
            <a class="text-link" href="{{ route('bookings.confirmation', $booking) }}">View details</a>
            @if($holdExpired)
                <a class="button button-outline booking-receipt-link" href="{{ route('bookings.receipt', $booking) }}">View payment status</a>
            @elseif($booking->status === 'pending' && ! $booking->hasProtectedInventory())
                <a class="button booking-receipt-link" href="{{ route('bookings.receipt', $booking) }}">Continue payment</a>
            @endif
            @if($booking->status === 'confirmed' || in_array($booking->payment_status, ['paid', 'partial', 'refunded'], true))
                <a class="button button-outline booking-receipt-link" href="{{ route('bookings.receipt', $booking) }}">{{ $booking->payment_status === 'partial' ? 'View downpayment receipt' : 'View receipt' }}</a>
            @endif
            @if($booking->status !== 'cancelled' && $booking->check_in->isFuture())
                @if($booking->canCancelOnline())
                    <small>Cancel at least 3 days before check-in to qualify for a refund.</small>
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}">@csrf @method('PATCH')<button class="link-danger">Cancel booking</button></form>
                @else
                    <small>Online cancellation is closed within 3 days of check-in.</small>
                @endif
            @endif
        </div>
        @if($booking->checked_out_at)
            <div class="booking-review-area">
                @if($booking->review)
                    <b>Your review</b><span class="review-stars">{{ str_repeat('★', $booking->review->rating) }}{{ str_repeat('☆', 5 - $booking->review->rating) }}</span><small>{{ $booking->review->status === 'approved' ? 'Published' : ($booking->review->status === 'hidden' ? 'Not published' : 'Awaiting staff approval') }}</small>
                @elseif($booking->status !== 'cancelled')
                    <details class="review-form">
                        <summary>Leave a review</summary>
                        <form method="POST" action="{{ route('bookings.review', $booking) }}">
                            @csrf
                            <fieldset class="review-rating review-rating-overall">
                                <legend>Overall rating</legend>
                                <div class="review-stars-picker">
                                    @for($rating = 5; $rating >= 1; $rating--)
                                        <input class="review-star-input" type="radio" id="review-{{ $booking->id }}-rating-{{ $rating }}" name="rating" value="{{ $rating }}" required>
                                        <label class="review-star-choice" for="review-{{ $booking->id }}-rating-{{ $rating }}" title="{{ $rating }} {{ Str::plural('star', $rating) }}"><span aria-hidden="true">★</span><span class="sr-only">{{ $rating }} {{ Str::plural('star', $rating) }}</span></label>
                                    @endfor
                                </div>
                            </fieldset>
                            <div class="review-score-grid">
                                @foreach(['cleanliness_rating' => 'Cleanliness', 'comfort_rating' => 'Comfort', 'value_rating' => 'Value'] as $field => $label)
                                    <fieldset class="review-rating review-rating-compact">
                                        <legend>{{ $label }} <span class="review-rating-optional">Optional</span></legend>
                                        <div class="review-stars-picker">
                                            @for($rating = 5; $rating >= 1; $rating--)
                                                <input class="review-star-input" type="radio" id="review-{{ $booking->id }}-{{ $field }}-{{ $rating }}" name="{{ $field }}" value="{{ $rating }}">
                                                <label class="review-star-choice" for="review-{{ $booking->id }}-{{ $field }}-{{ $rating }}" title="{{ $rating }} {{ Str::plural('star', $rating) }}"><span aria-hidden="true">★</span><span class="sr-only">{{ $rating }} {{ Str::plural('star', $rating) }}</span></label>
                                            @endfor
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                            <label>Comment<textarea name="comment" rows="3" maxlength="750" placeholder="Tell future guests about your stay (optional)"></textarea></label>
                            <button class="button small" type="submit">Submit review</button>
                        </form>
                    </details>
                @endif
            </div>
        @endif
    </article>
@empty
    <div class="empty-state"><h2>No bookings yet</h2><p>Your upcoming stays will appear here.</p><a class="button" href="{{ route('rooms.index') }}">Browse rooms</a></div>
@endforelse
</div>
</section>
</section>
@if($bookings->hasPages())
    <nav class="booking-pagination" aria-label="Booking pages">
        @if($bookings->onFirstPage())<span class="booking-pagination-disabled" aria-disabled="true">Previous</span>@else<a href="{{ $bookings->previousPageUrl() }}">Previous</a>@endif
        <span class="booking-pagination-page" aria-current="page">Page {{ $bookings->currentPage() }} of {{ $bookings->lastPage() }}</span>
        @if($bookings->hasMorePages())<a href="{{ $bookings->nextPageUrl() }}">Next</a>@else<span class="booking-pagination-disabled" aria-disabled="true">Next</span>@endif
    </nav>
@endif
@endsection
