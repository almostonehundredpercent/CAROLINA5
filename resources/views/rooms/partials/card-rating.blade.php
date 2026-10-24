@if($room->approved_reviews_count)
    @php($averageRating = (float) $room->approved_reviews_avg_rating)
    @php($filledStars = max(1, min(5, (int) round($averageRating))))
    <small class="card-rating" aria-label="Rated {{ number_format($averageRating, 1) }} out of 5 from {{ $room->approved_reviews_count }} {{ Str::plural('review', $room->approved_reviews_count) }}">
        <span class="card-rating-stars" aria-hidden="true">{{ str_repeat('★', $filledStars) }}{{ str_repeat('☆', 5 - $filledStars) }}</span>
        <span class="card-rating-score">{{ number_format($averageRating, 1) }}</span>
        <span class="card-rating-count">{{ $room->approved_reviews_count }} {{ Str::plural('review', $room->approved_reviews_count) }}</span>
    </small>
@else
    <small class="card-rating card-rating-empty" aria-label="No reviews yet"><span aria-hidden="true">☆</span> No reviews yet</small>
@endif
