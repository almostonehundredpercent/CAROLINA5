@extends('layouts.app')
@section('content')
<section class="page-hero slim"><span class="eyebrow">YOUR STAYS</span><h1>My bookings</h1></section>
<section class="section compact"><div class="booking-list">
@forelse($bookings as $booking)
    <article class="booking-row">
        <img src="{{ $booking->room->image_url }}" alt="{{ $booking->room->name }}">
        <div class="booking-details">
            <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
            <h3>{{ $booking->room->name }}</h3>
            <p>{{ $booking->check_in->format('M j, Y') }} - {{ $booking->check_out->format('M j, Y') }} · {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }} · {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</p>
            <small>Reference: {{ $booking->reference }}</small>
        </div>
        <div class="booking-actions">
            <strong>₱{{ number_format($booking->total_amount) }}</strong>
            <a class="text-link" href="{{ route('bookings.confirmation', $booking) }}">View details</a>
            @if($booking->status === 'confirmed' || in_array($booking->payment_status, ['paid', 'refunded'], true))
                <a class="button button-outline booking-receipt-link" href="{{ route('bookings.receipt', $booking) }}">View receipt</a>
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
</div></section>
@endsection
