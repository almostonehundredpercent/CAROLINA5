@extends('layouts.app')
@section('content')
<section class="page-hero slim"><span class="eyebrow">YOUR STAYS</span><h1>My bookings</h1></section>
<section class="section compact"><div class="booking-list">
@forelse($bookings as $booking)
    <article class="booking-row">
        <img src="{{ $booking->room->image_url }}" alt="{{ $booking->room->name }}">
        <div><span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span><h3>{{ $booking->room->name }}</h3><p>{{ $booking->check_in->format('M j, Y') }} - {{ $booking->check_out->format('M j, Y') }} · {{ $booking->nights }} nights · {{ $booking->guests }} guests</p><small>Reference: {{ $booking->reference }}</small></div>
        <div><strong>₱{{ number_format($booking->total_amount) }}</strong><a class="text-link" href="{{ route('bookings.confirmation', $booking) }}">View details</a>@if($booking->status !== 'cancelled' && $booking->check_in->isFuture())<form method="POST" action="{{ route('bookings.cancel', $booking) }}">@csrf @method('PATCH')<button class="link-danger">Cancel</button></form>@endif</div>
        @if($booking->checked_out_at)
            <div class="booking-review-area">
                @if($booking->review)
                    <b>Your review</b><span class="review-stars">{{ str_repeat('★', $booking->review->rating) }}{{ str_repeat('☆', 5 - $booking->review->rating) }}</span><small>{{ $booking->review->status === 'approved' ? 'Published' : ($booking->review->status === 'hidden' ? 'Not published' : 'Awaiting staff approval') }}</small>
                @elseif($booking->status !== 'cancelled')
                    <details class="review-form"><summary>Leave a review</summary><form method="POST" action="{{ route('bookings.review', $booking) }}">@csrf
                        <label>Overall rating<select name="rating" required><option value="">Choose rating</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }} star{{ $rating === 1 ? '' : 's' }}</option>@endfor</select></label>
                        <div class="review-score-grid"><label>Cleanliness<select name="cleanliness_rating"><option value="">Optional</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }}</option>@endfor</select></label><label>Comfort<select name="comfort_rating"><option value="">Optional</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }}</option>@endfor</select></label><label>Value<select name="value_rating"><option value="">Optional</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }}</option>@endfor</select></label></div>
                        <label>Comment<textarea name="comment" rows="3" maxlength="750" placeholder="Tell future guests about your stay (optional)"></textarea></label><button class="button small" type="submit">Submit review</button>
                    </form></details>
                @endif
            </div>
        @endif
    </article>
@empty
    <div class="empty-state"><h2>No bookings yet</h2><p>Your upcoming stays will appear here.</p><a class="button" href="{{ route('rooms.index') }}">Browse rooms</a></div>
@endforelse
</div></section>
@endsection
