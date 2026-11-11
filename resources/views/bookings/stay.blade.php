@extends('layouts.app')
@section('title', 'Your arrival · Carolina')
@push('late-styles')
<link rel="stylesheet" href="{{ asset('css/stay.css') }}?v={{ filemtime(public_path('css/stay.css')) }}">
@endpush
@section('content')
<section class="stay-page stay-unified-page">
    @php
        $stayPaid = max(0, round((float) $financial['net_collected'], 2));
        if ($stayPaid < 0.01 && $booking->payment_status === 'paid') $stayPaid = (float) $booking->total_amount;
        $stayPaidInFull = $booking->payment_status === 'paid' || $stayPaid + 0.01 >= (float) $booking->total_amount;
        $stayBalance = $stayPaidInFull ? 0 : round((float) $financial['outstanding'], 2);
        $stayPaymentMode = $booking->payment_status === 'refunded'
            ? 'Refund recorded'
            : ($booking->status === 'cancelled' && $stayPaid <= 0
                ? 'No payment received'
                : ($stayPaidInFull ? 'Paid in full' : ($stayPaid > 0 ? 'Downpayment / partial payment' : ((float) $booking->deposit_amount > 0 ? 'Downpayment selected' : 'Full payment selected'))));
        $stayPaymentMethod = match ($booking->payment_method) { 'gcash' => 'GCash', 'cash' => 'Cash', null => 'Not paid yet', default => strtoupper((string) $booking->payment_method) };
        $backUrl = auth()->check() && auth()->id() === $booking->user_id
            ? route('bookings.index', ['section' => 'bookings'])
            : route('bookings.lookup');
        $backLabel = auth()->check() && auth()->id() === $booking->user_id ? 'My bookings' : 'Find booking';
    @endphp

    <article class="stay-unified-card">
        <header class="stay-unified-header">
            <div class="stay-booking-context">
                <a class="stay-back-link" href="{{ $backUrl }}"><span aria-hidden="true">←</span> Back to {{ $backLabel }}</a>
                <div class="stay-identifiers" role="group" aria-label="Room and booking reference">
                    <div><small>Room</small><strong>{{ $booking->room?->name ?? 'Room unavailable' }}</strong></div>
                    <div><small>Booking reference</small><strong>{{ $booking->reference }}</strong></div>
                </div>
            </div>
            <div class="stay-unified-intro">
                <span class="eyebrow">YOUR ARRIVAL</span>
                <h1>A little less to worry about.</h1>
                <p>Stay prepared with live room readiness and arrival updates from Carolina.</p>
            </div>
        </header>

        @foreach($notices as $notice)
            <aside class="stay-notice" role="status"><strong>{{ $notice->title }}</strong><p>{{ $notice->message }}</p><small>{{ ucfirst($notice->type) }} update · {{ \Carbon\Carbon::parse($notice->updated_at)->format('M j, g:i A') }}</small></aside>
        @endforeach

        <div class="stay-overview-grid">
            <section class="stay-overview-section" aria-labelledby="stay-reservation-title">
                <div class="stay-section-heading">
                    <h2 id="stay-reservation-title">Your reservation</h2>
                    <span class="stay-status-pill stay-status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                </div>
                <dl class="stay-reservation-facts">
                    <div><dt>Check-in</dt><dd>{{ ($booking->check_in_at ?? $booking->check_in)->format('M j, Y · g:i A') }}</dd></div>
                    <div><dt>Check-out</dt><dd>{{ ($booking->check_out_at ?? $booking->check_out)->format('M j, Y · g:i A') }}</dd></div>
                </dl>
                @if($booking->status === 'pending')<p class="stay-reservation-note">Reception still needs to confirm availability.</p>@endif
            </section>

            <section class="stay-overview-section stay-room-preparation" aria-labelledby="stay-preparation-title">
                <h2 id="stay-preparation-title">Room preparation</h2>
                @if($booking->status === 'cancelled')
                    <strong>Reservation cancelled</strong>
                @elseif($booking->checked_out_at)
                    <strong>Stay completed</strong>
                @elseif($booking->checked_in_at)
                    <strong>Welcome in</strong>
                @elseif($booking->status !== 'confirmed' || !$booking->readiness_updated_at || \Carbon\Carbon::parse($booking->readiness_updated_at)->lt(now()->subHours(12)))
                    <strong>Awaiting a fresh update from reception</strong>
                @else
                    <strong>{{ ['unconfirmed' => 'Awaiting preparation update', 'preparing' => 'We are preparing your room', 'ready' => 'Reception has marked your room ready'][$booking->readiness] }}</strong>
                    @if($booking->readiness === 'preparing' && $booking->ready_estimate)<p>Estimated ready: {{ \Carbon\Carbon::parse($booking->ready_estimate)->format('M j, g:i A') }}. Timing may change; refresh for updates.</p>@endif
                    <p>{{ $booking->bag_drop_available ? 'Bag drop is available. Please check with reception on arrival.' : 'Please contact reception before arriving early or dropping off bags.' }}</p>
                    <small>Updated {{ \Carbon\Carbon::parse($booking->readiness_updated_at)->diffForHumans() }}. Your booked arrival time still applies.</small>
                @endif
            </section>
        </div>

        <section class="stay-payment-section" aria-labelledby="stay-payment-title">
            <h2 id="stay-payment-title">Payment summary</h2>
            <dl class="stay-payment-facts">
                <div><dt>Booking total</dt><dd>₱{{ number_format($booking->total_amount, 2) }}</dd></div>
                @if($booking->payment_status === 'refunded')
                    <div><dt>Refund recorded</dt><dd>₱{{ number_format((float) $financial['refunds'], 2) }}</dd></div>
                @else
                    <div><dt>Amount paid</dt><dd>₱{{ number_format($stayPaid, 2) }}</dd></div>
                @endif
                <div><dt>Remaining balance</dt><dd>₱{{ number_format($stayBalance, 2) }}</dd></div>
                <div><dt>Payment option</dt><dd>{{ $stayPaymentMode }}</dd></div>
                <div><dt>Payment method</dt><dd>{{ $stayPaymentMethod }}</dd></div>
            </dl>
        </section>

        @if($booking->status !== 'cancelled' && !$booking->checked_in_at && !$booking->checked_out_at && !($booking->check_out_at ?? $booking->check_out->copy()->endOfDay())->isPast())
            <section class="stay-update-section" aria-labelledby="stay-update-title">
                <h2 id="stay-update-title">How is your journey going?</h2>
                <p>Your update goes to reception. It does not change or cancel your booking.</p>
                @if($booking->arrival_update)<p>Last update: <strong>{{ str_replace('_', ' ', ucfirst($booking->arrival_update)) }}</strong></p>@endif
                <form class="stay-actions" method="POST" action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('stay.arrival', now()->addHour(), ['booking' => $booking->id]) }}">@csrf
                    <button class="button" name="arrival_update" value="on_time">On time</button>
                    <button class="button" name="arrival_update" value="running_late">Running late</button>
                    <button class="button" name="arrival_update" value="cannot_make_it">Cannot make it</button>
                </form>
            </section>
        @endif

        @if($booking->arrival_instructions)
            <section class="stay-instructions-section" aria-labelledby="stay-instructions-title"><h2 id="stay-instructions-title">From reception</h2><p class="stay-message">{{ $booking->arrival_instructions }}</p></section>
        @endif

        <footer class="stay-private-note">This private link expires after seven days. @auth Use <a href="{{ route('bookings.index', ['section' => 'bookings']) }}">My bookings</a> to open your reservation again. @else Use <a href="{{ route('bookings.lookup') }}">Find booking</a> to open a fresh arrival page. @endauth Keep this link private.</footer>
    </article>
</section>
@endsection
