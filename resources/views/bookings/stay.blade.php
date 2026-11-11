@extends('layouts.app')
@section('title', 'Your arrival · Carolina')
@push('late-styles')
<link rel="stylesheet" href="{{ asset('css/stay.css') }}?v={{ filemtime(public_path('css/stay.css')) }}">
@endpush
@section('content')
<section class="stay-page">
    @php
        $stayPaid = max(0, round((float) $financial['net_collected'], 2));
        if ($stayPaid < 0.01 && $booking->payment_status === 'paid') $stayPaid = (float) $booking->total_amount;
        $stayPaidInFull = $booking->payment_status === 'paid' || $stayPaid + 0.01 >= (float) $booking->total_amount;
        $stayBalance = $stayPaidInFull ? 0 : round((float) $financial['outstanding'], 2);
        $stayPaymentMode = $booking->payment_status === 'refunded' ? 'Refund recorded' : ($stayPaidInFull ? 'Paid in full' : ($stayPaid > 0 ? 'Downpayment / partial payment' : ((float) $booking->deposit_amount > 0 ? 'Downpayment selected' : 'Full payment selected')));
        $stayPaymentMethod = match ($booking->payment_method) { 'gcash' => 'GCash', 'cash' => 'Cash', null => 'Not paid yet', default => strtoupper((string) $booking->payment_method) };
    @endphp
    @include('bookings.partials.context-bar', ['backUrl' => auth()->check() && auth()->id() === $booking->user_id ? route('bookings.index') : route('bookings.lookup'), 'backLabel' => auth()->check() && auth()->id() === $booking->user_id ? 'My bookings' : 'Find booking', 'roomName' => $booking->room?->name ?? 'Room unavailable', 'bookingReference' => $booking->reference])
    <span class="eyebrow">YOUR ARRIVAL</span><h1>A little less to worry about.</h1>
    <p>Stay prepared with live room readiness and arrival updates from Carolina.</p>
    @foreach($notices as $notice)
        <aside class="stay-notice" role="status"><strong>{{ $notice->title }}</strong><p>{{ $notice->message }}</p><small>{{ ucfirst($notice->type) }} update · {{ \Carbon\Carbon::parse($notice->updated_at)->format('M j, g:i A') }}</small></aside>
    @endforeach
    <div class="stay-grid">
        <article class="stay-card"><h2>Your reservation</h2><p>{{ ucfirst($booking->status) }}</p><strong>{{ ($booking->check_in_at ?? $booking->check_in)->format('M j, Y · g:i A') }}</strong><p>Until {{ ($booking->check_out_at ?? $booking->check_out)->format('M j, Y · g:i A') }}</p>
            @if($booking->status === 'pending')<p>Reception still needs to confirm availability.</p>@endif
            <div class="stay-payment-summary" aria-label="Payment summary">
                <h3>Payment summary</h3>
                <div><small>Booking total</small><b>₱{{ number_format($booking->total_amount, 2) }}</b></div>
                @if($booking->payment_status === 'refunded')
                    <div><small>Refund recorded</small><b>₱{{ number_format((float) $financial['refunds'], 2) }}</b></div>
                @else
                    <div><small>Amount paid</small><b>₱{{ number_format($stayPaid, 2) }}</b></div>
                @endif
                <div><small>Remaining balance</small><b>₱{{ number_format($stayBalance, 2) }}</b></div>
                <div><small>Payment option</small><b>{{ $stayPaymentMode }}</b></div>
                <div><small>Payment method</small><b>{{ $stayPaymentMethod }}</b></div>
            </div>
        </article>
        <article class="stay-card"><h2>Room preparation</h2>
            @if($booking->status === 'cancelled')<strong>Reservation cancelled</strong>
            @elseif($booking->checked_out_at)<strong>Stay completed</strong>
            @elseif($booking->checked_in_at)<strong>Welcome in</strong>
            @elseif($booking->status !== 'confirmed' || !$booking->readiness_updated_at || \Carbon\Carbon::parse($booking->readiness_updated_at)->lt(now()->subHours(12)))<strong>Awaiting a fresh update from reception</strong>
            @else<strong>{{ ['unconfirmed' => 'Awaiting preparation update', 'preparing' => 'We are preparing your room', 'ready' => 'Reception has marked your room ready'][$booking->readiness] }}</strong>
                @if($booking->readiness === 'preparing' && $booking->ready_estimate)<p>Estimated ready: {{ \Carbon\Carbon::parse($booking->ready_estimate)->format('M j, g:i A') }}. Timing may change; refresh for updates.</p>@endif
                <p>{{ $booking->bag_drop_available ? 'Bag drop is available. Please check with reception on arrival.' : 'Please contact reception before arriving early or dropping off bags.' }}</p>
                <small>Updated {{ \Carbon\Carbon::parse($booking->readiness_updated_at)->diffForHumans() }}. Your booked arrival time still applies.</small>
            @endif
        </article>
    </div>
    @if($booking->status !== 'cancelled' && !$booking->checked_in_at && !$booking->checked_out_at && !($booking->check_out_at ?? $booking->check_out->copy()->endOfDay())->isPast())
    <article class="stay-card"><h2>How is your journey going?</h2><p>Your update goes to reception. It does not change or cancel your booking.</p>
        @if($booking->arrival_update)<p>Last update: <strong>{{ str_replace('_', ' ', ucfirst($booking->arrival_update)) }}</strong></p>@endif
        <form class="stay-actions" method="POST" action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('stay.arrival', now()->addHour(), ['booking' => $booking->id]) }}">@csrf
            <button class="button" name="arrival_update" value="on_time">On time</button><button class="button" name="arrival_update" value="running_late">Running late</button><button class="button" name="arrival_update" value="cannot_make_it">Cannot make it</button>
        </form>
    </article>
    @endif
    @if($booking->arrival_instructions)<article class="stay-card"><h2>From reception</h2><p class="stay-message">{{ $booking->arrival_instructions }}</p></article>@endif
    <p>This private link expires after seven days. @auth Use <a href="{{ route('bookings.index') }}">My bookings</a> to open your reservation again. @else Use <a href="{{ route('bookings.lookup') }}">Find booking</a> to open a fresh arrival page. @endauth Keep this link private.</p>
</section>
@endsection
