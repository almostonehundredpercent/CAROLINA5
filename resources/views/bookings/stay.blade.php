@extends('layouts.app')
@section('title', 'Your arrival · Carolina')
@push('late-styles')
<link rel="stylesheet" href="{{ asset('css/stay.css') }}?v={{ filemtime(public_path('css/stay.css')) }}">
@endpush
@section('content')
<section class="stay-page">
    <span class="eyebrow">YOUR ARRIVAL</span><h1>A little less to worry about.</h1>
    <p>{{ $booking->room?->name }} · {{ $booking->reference }}</p>
    @foreach($notices as $notice)
        <aside class="stay-notice" role="status"><strong>{{ $notice->title }}</strong><p>{{ $notice->message }}</p><small>{{ ucfirst($notice->type) }} update · {{ \Carbon\Carbon::parse($notice->updated_at)->format('M j, g:i A') }}</small></aside>
    @endforeach
    <div class="stay-grid">
        <article class="stay-card"><h2>Your reservation</h2><p>{{ ucfirst($booking->status) }}</p><strong>{{ ($booking->check_in_at ?? $booking->check_in)->format('M j, Y · g:i A') }}</strong><p>Until {{ ($booking->check_out_at ?? $booking->check_out)->format('M j, Y · g:i A') }}</p>
            @if($booking->status === 'pending')<p>Reception still needs to confirm availability.</p>@endif
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
    <p>This private link expires after seven days. Use <a href="{{ route('bookings.lookup') }}">Find booking</a> to open a fresh arrival page. Keep this link private.</p>
</section>
@endsection
