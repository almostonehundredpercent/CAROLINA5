@extends('layouts.app')
@section('content')
<section class="page-hero"><span class="eyebrow">CAROLINA ROOMS</span><h1>Find the room that fits your stay.</h1><p>Search live availability and reserve in a few simple steps.</p></section>
<section class="section compact">
    <form class="filter-bar" method="GET">
        <input id="rooms-check-in" type="date" name="check_in" value="{{ $filters['check_in'] ?? '' }}" min="{{ now()->toDateString() }}" aria-label="Check in">
        <input id="rooms-check-out" type="date" name="check_out" value="{{ $filters['check_out'] ?? '' }}" min="{{ now()->addDay()->toDateString() }}" aria-label="Check out">
        <select name="guests"><option value="">Any guests</option>@foreach([1,2,3,4,5,6] as $number)<option value="{{ $number }}" @selected(($filters['guests'] ?? '') == $number)>{{ $number }}+ guests</option>@endforeach</select>
        <button class="button">Check availability</button>
    </form>

    <section class="rooms-availability" aria-label="Room availability calendar">
        <div class="rooms-availability-top">
            <div><span class="eyebrow">ROOM CALENDAR</span><b>See booked dates before you reserve</b><p>Red dates are unavailable. Tap two available dates to use them in your room search.</p></div>
            <label class="rooms-room-picker">View room availability<select id="rooms-calendar-room"><option value="">Choose a room</option>@foreach($availabilityRooms as $room)<option data-url="{{ route('rooms.availability', $room) }}">{{ $room->name }}</option>@endforeach</select></label>
        </div>
        <div class="rooms-calendar" id="rooms-calendar" hidden>
            <div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div>
            <div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="rooms-calendar-days" id="rooms-calendar-days"></div>
            <p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite"></p>
        </div>
    </section>

    <p class="result-count">{{ $rooms->count() }} room{{ $rooms->count() === 1 ? '' : 's' }} available</p>
    <div class="room-grid">@forelse($rooms as $room)<article class="room-card"><img src="{{ $room->image_url }}" alt="{{ $room->name }}"><div class="room-card-body"><span>{{ $room->room_type }} · {{ $room->beds }} bed{{ $room->beds > 1 ? 's' : '' }} · {{ $room->guests }} guests</span><h3>{{ $room->name }}</h3><p>{{ Str::limit($room->description, 86) }}</p><strong>₱{{ number_format($room->price_per_night) }} <small>/ night</small></strong><a class="button small" href="{{ route('rooms.show', $room) }}">Select room</a></div></article>@empty<div class="empty-state"><h2>No rooms found</h2><p>Try different dates or a smaller group size.</p><a class="text-link" href="{{ route('rooms.index') }}">Clear search</a></div>@endforelse</div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const room = document.getElementById('rooms-calendar-room');
    const calendar = document.getElementById('rooms-calendar');
    const days = document.getElementById('rooms-calendar-days');
    const month = document.getElementById('rooms-calendar-month');
    const note = document.getElementById('rooms-calendar-note');
    const checkIn = document.getElementById('rooms-check-in');
    const checkOut = document.getElementById('rooms-check-out');
    const today = new Date(); today.setHours(0, 0, 0, 0);
    let cursor = new Date(today.getFullYear(), today.getMonth(), 1), ranges = [];
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const blocked = value => ranges.some(range => value >= range.start && value < range.end);
    const overlaps = (start, end) => ranges.some(range => start < range.end && end > range.start);
    const render = () => {
        days.innerHTML = ''; month.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1), last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
        for (let blank = 0; blank < first.getDay(); blank++) days.insertAdjacentHTML('beforeend', '<span class="rooms-calendar-blank"></span>');
        for (let number = 1; number <= last.getDate(); number++) {
            const date = new Date(cursor.getFullYear(), cursor.getMonth(), number), value = iso(date), isBlocked = blocked(value), previous = new Date(date), next = new Date(date);
            previous.setDate(previous.getDate() - 1); next.setDate(next.getDate() + 1);
            const bookedClass = isBlocked ? ` booked${blocked(iso(previous)) ? '' : ' booked-start'}${blocked(iso(next)) ? '' : ' booked-end'}` : '';
            const selectedClass = value === checkIn.value ? ' selected start' : value === checkOut.value ? ' selected end' : checkIn.value && checkOut.value && value > checkIn.value && value < checkOut.value ? ' selected range' : '';
            days.insertAdjacentHTML('beforeend', `<button type="button" class="rooms-calendar-day${bookedClass}${selectedClass}${date < today ? ' past' : ''}" data-date="${value}" ${isBlocked || date < today ? 'disabled' : ''}>${number}</button>`);
        }
        days.querySelectorAll('.rooms-calendar-day:not([disabled])').forEach(button => button.addEventListener('click', () => choose(button.dataset.date)));
    };
    const choose = value => {
        if (!checkIn.value || checkOut.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in selected. Now choose a check-out date.'; }
        else if (value <= checkIn.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in updated. Now choose a check-out date.'; }
        else if (overlaps(checkIn.value, value)) { note.textContent = 'That stay includes dates already booked for this room.'; return; }
        else { checkOut.value = value; note.textContent = 'Dates selected. Check availability to see rooms that match.'; }
        render();
    };
    const load = async () => {
        const url = room.selectedOptions[0]?.dataset.url;
        if (!url) { calendar.hidden = true; return; }
        try { const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' }); if (!response.ok) return; ranges = (await response.json()).ranges; calendar.hidden = false; note.textContent = 'Booked dates are shown in red.'; render(); } catch (_) { calendar.hidden = true; }
    };
    room.addEventListener('change', load);
    document.getElementById('rooms-calendar-prev').addEventListener('click', () => { const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; render(); } });
    document.getElementById('rooms-calendar-next').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
});
</script>
@endsection
