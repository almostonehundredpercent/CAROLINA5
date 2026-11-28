@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking-pagination.css') }}?v={{ filemtime(public_path('css/booking-pagination.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer-profile.css') }}?v={{ filemtime(public_path('css/customer-profile.css')) }}">
@endpush
@section('content')
<section class="page-hero slim customer-profile-hero">
    <div class="customer-profile-head">
        <span class="eyebrow">YOUR STAYS</span>
        <h1>Your stays</h1>
        <p>See your reservations, payment details, and stay updates in one place.</p>
    </div>
</section>
<section class="section compact customer-profile">
    @if(session('success'))<p class="customer-profile-alert" role="status">{{ session('success') }}</p>@endif
    <div class="customer-profile-actions" aria-label="Quick actions">
        <a class="customer-profile-action" href="{{ route('booking.start') }}"><span aria-hidden="true">＋</span><strong>New booking</strong><small>Find your next stay</small></a>
        <a class="customer-profile-action" href="{{ route('contact') }}"><span aria-hidden="true">✉</span><strong>Contact Carolina</strong><small>Get help with your stay</small></a>
    </div>

    <nav class="customer-profile-tabs" aria-label="Account pages">
        <a href="{{ route('profile.show') }}">Profile information</a>
        <a href="{{ route('bookings.index') }}" aria-current="page">My bookings</a>
    </nav>

    <section class="customer-profile-stays" id="your-bookings" aria-labelledby="your-bookings-title">
        <div class="customer-profile-section-heading"><div><span class="profile-kicker">BOOKING HISTORY</span><h2 id="your-bookings-title">My bookings</h2><p>See your reservations, payment details, and stay updates in one place.</p></div><span class="profile-stay-count">{{ $bookings->total() }} {{ \Illuminate\Support\Str::plural('booking', $bookings->total()) }}</span></div>
        <div class="booking-list">
@forelse($bookings as $booking)
    <article class="booking-row">
        <picture>@if($roomWebpSrcset = $booking->room->responsiveWebpSrcset())<source type="image/webp" srcset="{{ $roomWebpSrcset }}" sizes="(max-width: 800px) 80px, 140px">@endif<img src="{{ $booking->room->versionedImageUrl() }}" alt="{{ $booking->room->name }}" @if($roomSrcset = $booking->room->responsiveImageSrcset()) srcset="{{ $roomSrcset }}" sizes="(max-width: 800px) 80px, 140px" @endif loading="lazy" decoding="async"></picture>
        <div class="booking-details">
            @php($holdExpired = $booking->hasExpiredHold() || $booking->isExpiredHoldRecord())
            <span class="status {{ $holdExpired ? 'cancelled' : $booking->status }}">{{ $holdExpired ? 'Payment hold expired' : ucfirst($booking->status) }}</span>
            <h3>{{ $booking->room->name }}</h3>
            <p>{{ $booking->check_in->format('M j, Y') }} - {{ $booking->check_out->format('M j, Y') }} · {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }} · {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</p>
            <small>Reference: {{ $booking->reference }}</small>
            @php($bookingFinance = \App\Support\FinancialSummary::forBooking($booking))
            <div class="booking-payment-brief" aria-label="Payment summary">
                <div><small>Paid</small><b>₱{{ number_format(max(0, $bookingFinance['net_collected']), 2) }}</b></div>
                <div><small>Remaining balance</small><b>₱{{ number_format($bookingFinance['outstanding'], 2) }}</b></div>
            </div>
        </div>
        <div class="booking-actions">
            @php($isActiveStay = $booking->status === 'confirmed' && $booking->checked_in_at && ! $booking->checked_out_at)
            <strong>₱{{ number_format($booking->total_amount) }}</strong>
            <a class="text-link" href="{{ route('bookings.confirmation', $booking) }}">View details</a>
            @if($holdExpired)
                <a class="button button-outline booking-receipt-link" href="{{ \App\Http\Controllers\StayController::link($booking) }}">View payment status</a>
            @elseif($booking->status === 'pending' && ! $booking->hasProtectedInventory())
                <a class="button booking-receipt-link" href="{{ route('bookings.receipt', $booking) }}">Continue payment</a>
            @endif
            @if($booking->status === 'confirmed' || in_array($booking->payment_status, ['paid', 'partial', 'refunded'], true))
                <a class="{{ $isActiveStay ? 'text-link' : 'button button-outline booking-receipt-link' }}" href="{{ \App\Http\Controllers\StayController::link($booking) }}">{{ $booking->payment_status === 'partial' ? 'View downpayment receipt' : 'View receipt' }}</a>
            @endif
            @if($isActiveStay)
                <button class="button booking-receipt-link" type="button" data-stay-toggle aria-controls="stay-tools-{{ $booking->id }}" aria-expanded="{{ old('booking_id') == $booking->id && ($errors->has('extension') || $errors->has('hours')) ? 'true' : 'false' }}">Manage stay</button>
                <small>Extend hours or request early check-out</small>
            @endif
            @if($booking->early_checkout_status === 'requested' && ! $booking->checked_out_at)
                <small>Early check-out request sent. Reception must confirm before your stay ends.</small>
                @if($bookingFinance['outstanding'] > 0.001)
                    <small role="status">Pay the remaining balance of ₱{{ number_format($bookingFinance['outstanding'], 2) }} before reception can confirm early check-out.</small>
                @endif
            @elseif($booking->early_checkout_status === 'declined' && ! $booking->checked_out_at)
                <small>Reception could not confirm your request. Contact Carolina to arrange check-out.</small>
            @endif
            @if($booking->cancellation_request_status === 'requested' && $booking->status !== 'cancelled')
                <small role="status">Cancellation request sent. Your booking remains active until reception confirms it.</small>
            @elseif($booking->cancellation_request_status === 'declined' && $booking->status !== 'cancelled')
                <small role="status">Reception could not approve your cancellation request. Your booking remains active; contact Carolina for help.</small>
            @elseif($booking->status !== 'cancelled' && ($booking->check_in->isFuture() || $booking->canCancelOnline() || in_array($booking->cancellation_request_status, ['requested', 'declined'], true)))
                @if($booking->canRescheduleOnline())
                    <details class="booking-reschedule-details">
                        <summary>Reschedule booking · {{ $booking->reschedulesRemaining() }} left</summary>
                        <form class="booking-reschedule-form" method="POST" action="{{ route('bookings.reschedule', $booking) }}">
                            @csrf @method('PATCH')
                            @if($booking->booking_type === 'hourly')
                                <label>New check-in time<input type="datetime-local" name="new_check_in_at" min="{{ \App\Support\PropertyTime::now()->addDays(3)->format('Y-m-d\\TH:i') }}" value="{{ old('new_check_in_at') }}" required></label>
                            @else
                                <label>New check-in date<input type="date" name="new_check_in" min="{{ \App\Support\PropertyTime::now()->addDays(3)->toDateString() }}" value="{{ old('new_check_in') }}" required></label>
                            @endif
                            <small>Same room, stay length, and price. Up to 2 online reschedules.</small>
                            @error('reschedule')<small class="booking-reschedule-error">{{ $message }}</small>@enderror
                            <button type="submit">Save new time</button>
                        </form>
                    </details>
                @elseif($booking->reschedulesRemaining() === 0)
                    <small>Online rescheduling limit reached (2). Contact Carolina if you need to change your stay.</small>
                @endif
                @if($booking->canCancelOnline())
                    <small>{{ $booking->isRefundEligible() ? 'Cancellation is immediate. Recorded payments may qualify for a refund.' : 'Cancellation is immediate and non-refundable (less than 3 days before check-in).' }}</small>
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}" data-booking-cancel-confirm>@csrf @method('PATCH')<button class="link-danger" type="submit">Cancel booking</button></form>
                @else
                    <small>Online cancellation closes at the scheduled check-in time. Contact Carolina if you need help.</small>
                @endif
            @endif
        </div>
        @if($isActiveStay)
            <section class="booking-stay-tools" id="stay-tools-{{ $booking->id }}" aria-label="Manage stay for {{ $booking->room->name }}" @if(old('booking_id') != $booking->id || ! ($errors->has('extension') || $errors->has('hours'))) hidden @endif>
                <div class="booking-stay-tools-heading"><div><span class="profile-kicker">CURRENT STAY</span><h4>Manage your stay</h4></div><span>Scheduled check-out: {{ ($booking->check_out_at ?? $booking->check_out->copy()->endOfDay())->format('M j, g:i A') }}</span></div>
                <div class="booking-stay-tools-grid">
                    <section class="booking-stay-tool"><h5>Extend hours</h5><p>Add time at ₱{{ number_format(\App\Support\StayPricing::EXTRA_HOUR_RATE, 0) }} per hour. We check room availability and cleaning time before saving.</p>
                        <form method="POST" action="{{ route('bookings.lookup.extend', $booking) }}">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                            <label>Extra hours<input type="number" name="hours" min="1" max="168" step="1" value="{{ old('booking_id') == $booking->id ? old('hours', 1) : 1 }}" required></label>
                            <small>The added charge is payable at the property.</small>
                            @if(old('booking_id') == $booking->id)
                                @error('extension')<small class="booking-stay-error" role="alert">{{ $message }}</small>@enderror
                                @error('hours')<small class="booking-stay-error" role="alert">{{ $message }}</small>@enderror
                            @endif
                            <button class="button" type="submit" onclick="return window.confirm('Add these hours to your stay? The extra charge will be added to your balance.')">Add hours</button>
                        </form>
                    </section>
                    <section class="booking-stay-tool"><h5>Check out early</h5>
                        @if($booking->early_checkout_status === 'requested')<p>Your request is waiting for reception confirmation. Your stay remains active until staff records checkout.</p>
                        @elseif($booking->early_checkout_status === 'declined')<p>Reception could not confirm your request. Please contact Carolina.</p>
                        @elseif(($booking->check_out_at ?? $booking->check_out->copy()->endOfDay())->lte(\App\Support\PropertyTime::now()))<p>Your scheduled checkout time has passed. Please contact reception.</p>
                        @elseif($bookingFinance['outstanding'] > 0.001)<p>Settle your remaining balance of ₱{{ number_format($bookingFinance['outstanding'], 2) }} with reception before requesting early checkout.</p>
                        @else<p>Reception must confirm your departure. Your booking total stays the same.</p><form method="POST" action="{{ route('bookings.early-checkout', $booking) }}" onsubmit="return window.confirm('Send an early check-out request? Your stay remains active until reception confirms your departure.')">@csrf<button class="button button-outline" type="submit">Request early check-out</button></form>@endif
                    </section>
                </div>
            </section>
        @endif
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
@include('bookings.cancel-dialog')
<script>
document.querySelectorAll('[data-stay-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        panel.hidden = !panel.hidden;
        button.setAttribute('aria-expanded', String(!panel.hidden));
    });
});
</script>
@endsection
