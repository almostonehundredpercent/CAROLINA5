@extends('layouts.app')
@section('content')
<section class="page-hero"><span class="eyebrow">CAROLINA ROOMS</span><h1>Find the room that fits your stay.</h1><p>Search live availability and reserve in a few simple steps.</p></section>
<section class="section compact">
    <form class="filter-bar rooms-search" method="GET">
        <input id="rooms-check-in" type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}">
        <input id="rooms-check-out" type="hidden" name="check_out" value="{{ $filters['check_out'] ?? '' }}">
        <button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false"><span>Check in</span><b>Choose date</b></button>
        <button class="rooms-date-trigger" id="rooms-check-out-trigger" type="button" aria-expanded="false"><span>Check out</span><b>Choose date</b></button>
        <select name="guests"><option value="">Any guests</option>@foreach([1,2,3,4,5,6] as $number)<option value="{{ $number }}" @selected(($filters['guests'] ?? '') == $number)>{{ $number }}+ guests</option>@endforeach</select>
        <button class="button">Check availability</button>
        <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose stay dates">
            <div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div>
            <div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="rooms-calendar-days" id="rooms-calendar-days"></div>
            <p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date, then your check-out date.</p>
        </section>
    </form>

    <p class="result-count">{{ $rooms->count() }} room{{ $rooms->count() === 1 ? '' : 's' }} available</p>
    <div class="room-grid">@forelse($rooms as $room)<article class="room-card"><img src="{{ $room->image_url }}" alt="{{ $room->name }}"><div class="room-card-body"><span>{{ $room->room_type }} · {{ $room->beds }} bed{{ $room->beds > 1 ? 's' : '' }} · {{ $room->guests }} guests</span><h3>{{ $room->name }}</h3><p>{{ Str::limit($room->description, 86) }}</p><strong>₱{{ number_format($room->price_per_night) }} <small>/ night</small></strong><a class="button small" href="{{ route('rooms.show', $room) }}">Select room</a></div></article>@empty<div class="empty-state"><h2>No rooms found</h2><p>Try different dates or a smaller group size.</p><a class="text-link" href="{{ route('rooms.index') }}">Clear search</a></div>@endforelse</div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendar = document.getElementById('rooms-search-calendar');
    const days = document.getElementById('rooms-calendar-days');
    const month = document.getElementById('rooms-calendar-month');
    const note = document.getElementById('rooms-calendar-note');
    const checkIn = document.getElementById('rooms-check-in');
    const checkOut = document.getElementById('rooms-check-out');
    const checkInTrigger = document.getElementById('rooms-check-in-trigger');
    const checkOutTrigger = document.getElementById('rooms-check-out-trigger');
    const today = new Date(); today.setHours(0, 0, 0, 0);
    let cursor = new Date(today.getFullYear(), today.getMonth(), 1), activeField = 'check-in';
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const pretty = value => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Choose date';
    const syncTriggers = () => { checkInTrigger.querySelector('b').textContent = pretty(checkIn.value); checkOutTrigger.querySelector('b').textContent = pretty(checkOut.value); };
    const openCalendar = field => { activeField = field; calendar.hidden = false; checkInTrigger.setAttribute('aria-expanded', String(field === 'check-in')); checkOutTrigger.setAttribute('aria-expanded', String(field === 'check-out')); render(); };
    const render = () => {
        days.innerHTML = ''; month.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1), last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
        for (let blank = 0; blank < first.getDay(); blank++) days.insertAdjacentHTML('beforeend', '<span class="rooms-calendar-blank"></span>');
        for (let number = 1; number <= last.getDate(); number++) {
            const date = new Date(cursor.getFullYear(), cursor.getMonth(), number), value = iso(date);
            const selectedClass = value === checkIn.value ? ' selected start' : value === checkOut.value ? ' selected end' : checkIn.value && checkOut.value && value > checkIn.value && value < checkOut.value ? ' selected range' : '';
            days.insertAdjacentHTML('beforeend', `<button type="button" class="rooms-calendar-day${selectedClass}${date < today ? ' past' : ''}" data-date="${value}" ${date < today ? 'disabled' : ''}>${number}</button>`);
        }
        days.querySelectorAll('.rooms-calendar-day:not([disabled])').forEach(button => button.addEventListener('click', () => choose(button.dataset.date)));
    };
    const choose = value => {
        if (activeField === 'check-in' || !checkIn.value || value <= checkIn.value) { checkIn.value = value; checkOut.value = ''; activeField = 'check-out'; note.textContent = 'Check-in selected. Now choose your check-out date.'; }
        else { checkOut.value = value; note.textContent = 'Dates selected. Check availability to see matching rooms.'; calendar.hidden = true; }
        syncTriggers(); checkInTrigger.setAttribute('aria-expanded', String(activeField === 'check-in' && !calendar.hidden)); checkOutTrigger.setAttribute('aria-expanded', String(activeField === 'check-out' && !calendar.hidden)); render();
    };
    checkInTrigger.addEventListener('click', () => openCalendar('check-in'));
    checkOutTrigger.addEventListener('click', () => openCalendar('check-out'));
    document.getElementById('rooms-calendar-prev').addEventListener('click', () => { const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; render(); } });
    document.getElementById('rooms-calendar-next').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
    syncTriggers();
});
</script>
@endsection
