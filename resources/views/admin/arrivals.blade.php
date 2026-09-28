<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Arrivals · Carolina</title><link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}"><link rel="stylesheet" href="{{ asset('css/stay.css') }}?v={{ filemtime(public_path('css/stay.css')) }}"></head><body><div class="admin-shell">@include('admin.partials.sidebar')<main class="admin-main">
<header class="admin-topbar"><div><p class="admin-kicker">THE NEXT SEVEN DAYS</p><h1>Arrival confidence</h1><p>Keep room preparation and guest arrival details current.</p></div></header>
@include('admin.partials.flash')
<section class="stay-grid">
@forelse($bookings as $booking)
<article class="stay-card"><h2>{{ $booking->room?->name }}</h2><p>{{ $booking->guest_name ?? $booking->user?->name }} · {{ $booking->reference }}</p><p>{{ ($booking->check_in_at ?? $booking->check_in)->format('M j, g:i A') }} · {{ ucfirst($booking->status) }}</p><strong>Guest: {{ $booking->arrival_update ? str_replace('_', ' ', $booking->arrival_update) : 'No arrival update yet' }}</strong>
@if($booking->arrival_updated_at)<p>Updated {{ \Carbon\Carbon::parse($booking->arrival_updated_at)->diffForHumans() }}</p>@endif
@if($booking->status === 'confirmed')
<form class="stay-form" method="POST" action="{{ route('admin.arrivals.update', $booking) }}">@csrf @method('PATCH')
<label>Room preparation<select name="readiness">@foreach(['unconfirmed'=>'Awaiting update','preparing'=>'Preparing','ready'=>'Ready'] as $value=>$label)<option value="{{ $value }}" @selected($booking->readiness === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Estimated ready time (optional)<input type="datetime-local" name="ready_estimate" value="{{ $booking->ready_estimate ? \Carbon\Carbon::parse($booking->ready_estimate)->format('Y-m-d\TH:i') : '' }}"></label>
<label>Bag drop<select name="bag_drop_available"><option value="0">Contact reception first</option><option value="1" @selected($booking->bag_drop_available)>Available</option></select></label>
<label>Arrival instructions<textarea name="arrival_instructions" rows="3" maxlength="1500" placeholder="Approved directions, landmarks or reception instructions">{{ $booking->arrival_instructions }}</textarea></label>
<button class="button" type="submit">Update guest page</button></form>
@else<p>Confirm this booking before publishing readiness.</p>@endif
<p><a href="{{ route('admin.bookings.show', $booking) }}">Open booking</a></p></article>
@empty<p>No upcoming arrivals.</p>@endforelse
</section>{{ $bookings->links() }}
</main></div></body></html>
