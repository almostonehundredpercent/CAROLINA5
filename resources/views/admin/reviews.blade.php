<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guest reviews · Carolina Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        @if(session('success'))<div class="admin-flash">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-flash" style="background:#fbe3e0;color:#a84336">{{ $errors->first() }}</div>@endif

        <section class="panel guest-review-page">
            <div class="panel-heading">
                <div><p class="admin-kicker">GUEST REVIEWS</p><h2>Review moderation</h2></div>
                <span class="pill">{{ $pendingReviewCount }} to publish</span>
            </div>

            <div class="review-moderation-list">
                @forelse($reviews as $review)
                    <article class="review-moderation-card">
                        <div class="review-moderation-heading">
                            <div>
                                <h3>{{ $review->guest_name }} <span>· {{ $review->booking?->room?->name ?? 'Room unavailable' }}</span></h3>
                                <p class="review-moderation-rating" aria-label="Rated {{ $review->rating }} out of 5">
                                    <span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                    <b>{{ $review->rating }} / 5</b>
                                </p>
                            </div>
                            <span class="review-moderation-pending">{{ $review->status === 'hidden' ? 'Hidden' : 'Ready to publish' }}</span>
                        </div>

                        <p class="review-moderation-guidance">The guest’s name and review are shown as submitted and cannot be edited. Publish the review unchanged or hide it.</p>

                        <div class="review-moderation-submission">
                            <div><b>Name submitted</b><p>{{ $review->guest_name }}</p></div>
                            <div><b>Comment submitted</b><blockquote>{{ $review->comment ?: 'No written comment.' }}</blockquote></div>
                        </div>

                        <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="review-moderation-form">
                            @csrf @method('PATCH')
                            <div class="review-moderation-actions">
                                @if($review->status !== 'approved')<button class="review-moderation-button review-moderation-approve" type="submit" name="status" value="approved">{{ $review->status === 'pending' ? 'Approve &amp; publish unchanged' : 'Publish unchanged' }}</button>@endif
                                @if($review->status !== 'hidden')<button class="review-moderation-button review-moderation-hide" type="submit" name="status" value="hidden">Hide review</button>@endif
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="empty-copy">No reviews are waiting for moderation.</p>
                @endforelse
            </div>

            @if($reviews->hasPages())
                <nav class="booking-pagination" aria-label="Review page navigation">
                    <div class="booking-pagination-buttons">
                        @if($reviews->onFirstPage())<span class="booking-page-previous is-disabled" aria-disabled="true">Previous</span>@else<a class="booking-page-previous" href="{{ $reviews->previousPageUrl() }}">Previous</a>@endif
                        <span class="booking-page-number is-current" aria-current="page">Page {{ $reviews->currentPage() }} of {{ $reviews->lastPage() }}</span>
                        @if($reviews->hasMorePages())<a class="booking-page-next" href="{{ $reviews->nextPageUrl() }}">Next</a>@else<span class="booking-page-next is-disabled" aria-disabled="true">Next</span>@endif
                    </div>
                </nav>
            @endif
        </section>
    </main>
</div>
@include('admin.partials.confirmation-dialog')
</body>
</html>
