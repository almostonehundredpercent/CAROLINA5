@extends('layouts.app')

@section('content')
<style>
.stay-date-fields{display:grid;grid-template-columns:1fr 1fr;gap:15px}.stay-date-fields input[readonly]{cursor:pointer;background:#fff}.availability-calendar{border:1px solid var(--line);background:#fff;border-radius:9px;padding:16px}.calendar-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.calendar-heading strong{display:block;font-size:.92rem}.calendar-heading small{display:block;color:var(--muted);font-size:.73rem;margin-top:3px}.calendar-controls{display:flex;align-items:center;gap:8px}.calendar-controls b{min-width:119px;text-align:center;font-size:.78rem}.calendar-controls button{width:29px;height:29px;border:1px solid var(--line);background:#fff;border-radius:5px;color:var(--deep);font-size:1.35rem;line-height:1;cursor:pointer}.calendar-controls button:hover{background:var(--sand)}.calendar-weekdays,.calendar-days{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}.calendar-weekdays{margin-bottom:6px}.calendar-weekdays span{text-align:center;font-size:.65rem;color:var(--muted);font-weight:700}.calendar-day,.calendar-blank{aspect-ratio:1;min-width:0;border:0;border-radius:5px;background:transparent;color:var(--ink);font:600 .75rem 'DM Sans';display:grid;place-items:center}.calendar-day{cursor:pointer}.calendar-day:hover:not([disabled]){background:#fff0d8;color:var(--deep)}.calendar-day.booked{background:#fbe1df;color:#a84239;cursor:not-allowed;text-decoration:line-through}.calendar-day.past{color:#c8c0b8;cursor:not-allowed}.calendar-day.selected{background:var(--gold);color:#fff}.calendar-note{margin:13px 0 0;font-size:.77rem;color:var(--deep);line-height:1.45}.booking-form form .availability-calendar{margin-top:-4px}@media(max-width:520px){.stay-date-fields{grid-template-columns:1fr}.availability-calendar{padding:13px}.calendar-heading{display:block}.calendar-controls{margin-top:11px;justify-content:space-between}.calendar-controls b{min-width:auto}.calendar-weekdays,.calendar-days{gap:4px}.calendar-day,.calendar-blank{font-size:.7rem}}
</style>
<style>
.calendar-days{column-gap:0;row-gap:5px}.calendar-day{border-radius:0}.calendar-day.booked{background:#d9524b;color:#fff;text-decoration:none}.calendar-day.booked-start{border-radius:5px 0 0 5px}.calendar-day.booked-end{border-radius:0 5px 5px 0}.calendar-day.booked-start.booked-end{border-radius:5px}.calendar-day.selected{background:var(--gold)}.calendar-day.selected.start{border-radius:5px 0 0 5px}.calendar-day.selected.end{border-radius:0 5px 5px 0}.calendar-day.selected.start.end{border-radius:5px}.calendar-day.selected.range{background:#f6d59b;color:var(--deep)}
</style>
<style>.availability-calendar{max-width:500px}.calendar-day,.calendar-blank{font-size:.88rem}.calendar-weekdays span{font-size:.72rem}@media(max-width:520px){.availability-calendar{max-width:none}.calendar-day,.calendar-blank{font-size:.8rem}}</style>
<style>.hourly-rental{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;padding:17px;border:1px solid var(--line);border-radius:9px;background:#fff}.hourly-rental>p{grid-column:1/-1;margin:0;line-height:1.55}.hourly-rental #hourly-total{padding:10px 12px;border-radius:6px;background:#fff1db;color:var(--deep);font-weight:700}@media(max-width:520px){.hourly-rental{grid-template-columns:1fr;padding:14px}}</style>
<style>.booking-type-selector{padding:16px;border:1px solid var(--line);border-radius:9px;background:#fff}.booking-type-label{display:block;margin-bottom:10px;color:var(--muted);font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.booking-type-options{display:grid;grid-template-columns:1fr 1fr;gap:10px}.booking-type-options a{display:grid;gap:4px;padding:13px 14px;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--ink);transition:background .18s ease,border-color .18s ease,color .18s ease}.booking-type-options a:hover{border-color:var(--gold);background:#fff8ee}.booking-type-options strong{font-size:.92rem}.booking-type-options small{color:var(--muted);font-size:.75rem;line-height:1.35}.booking-type-options a.active{border-color:var(--gold);background:var(--gold);color:#fff}.booking-type-options a.active small{color:#fff6e7}@media(max-width:520px){.booking-type-options{grid-template-columns:1fr}}</style>
<section class="booking-page">
    <div class="booking-form">
        <span class="eyebrow">RESERVE {{ strtoupper($room->name) }}</span>
        <h1>Complete your booking.</h1>

        @guest
            <div class="checkout-choice">
                <a class="{{ !$isGuest ? 'active' : '' }}" href="{{ route('login') }}">Sign in</a>
                <a class="{{ $isGuest ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1]) }}">Continue as guest</a>
            </div>
            @if(!$isGuest)
                <p>Sign in to book with your account, or continue as a guest without creating one.</p>
                <a class="button" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1]) }}">Continue as guest</a>
            @endif
        @endguest

        @auth
            <p>Book using your Carolina account.</p>
        @endauth

        @if(auth()->check() || $isGuest)
            <form method="POST" action="{{ route('bookings.store', $room) }}">
                @csrf
                <input type="hidden" name="checkout_type" value="{{ $isGuest ? 'guest' : 'account' }}">
                <input type="hidden" name="booking_type" value="{{ $bookingMode === 'hourly' ? 'hourly' : 'dates' }}">
                <section class="booking-type-selector" aria-label="Choose booking type">
                    <span class="booking-type-label">Choose your stay type</span>
                    <div class="booking-type-options">
                        <a class="{{ $bookingMode !== 'hourly' ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => $isGuest ? 1 : null, 'mode' => 'dates']) }}"><strong>Book by dates</strong><small>Overnight or multi-day stays</small></a>
                        <a class="{{ $bookingMode === 'hourly' ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => $isGuest ? 1 : null, 'mode' => 'hourly']) }}"><strong>Rent by hours</strong><small>Short stays for 3, 12, or 24 hours</small></a>
                    </div>
                </section>

                @if($bookingMode === 'hourly')
                    <div class="hourly-rental"><p><strong>Short stay rental</strong><br>Rate: ₱{{ number_format($room->price_per_night / 24, 2) }} per hour.</p><label>Duration<select name="hours" id="hourly-hours"><option value="3">3 hours</option><option value="12">12 hours</option><option value="24">24 hours</option></select></label><label>Check-in date<input name="hourly_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('hourly_date', now()->toDateString()) }}" required></label><label>Check-in time<input name="check_in_time" type="time" value="{{ old('check_in_time', now()->addHour()->format('H:i')) }}" required></label><p id="hourly-total">Estimated total: ₱{{ number_format(($room->price_per_night / 24) * 3, 2) }}</p></div>
                @else
                <div class="stay-date-fields">
                    <label>Check in<input id="check-in-display" type="text" placeholder="Select a date" readonly required><input id="check-in" name="check_in" type="hidden" value="{{ old('check_in') }}"></label>
                    <label>Check out<input id="check-out-display" type="text" placeholder="Select a date" readonly required><input id="check-out" name="check_out" type="hidden" value="{{ old('check_out') }}"></label>
                </div>
                <section class="availability-calendar" aria-label="Room availability calendar" data-availability-url="{{ route('rooms.availability', $room) }}">
                    <div class="calendar-heading"><div><strong>Room availability</strong><small>Unavailable dates are shown in red.</small></div><div class="calendar-controls"><button type="button" id="previous-month" aria-label="Previous month">‹</button><b id="calendar-month"></b><button type="button" id="next-month" aria-label="Next month">›</button></div></div>
                    <div class="calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                    <div class="calendar-days" id="calendar-days"></div>
                    <p class="calendar-note" id="calendar-note" aria-live="polite"></p>
                </section>
                @endif
                <label>Guests<select name="guests">@for($i = 1; $i <= $room->guests; $i++)<option value="{{ $i }}" @selected(old('guests') == $i)>{{ $i }} guest{{ $i > 1 ? 's' : '' }}</option>@endfor</select></label>

                @if($isGuest)
                    <hr>
                    <h3>Guest and billing information</h3>
                    <label>Full name<input name="guest_name" value="{{ old('guest_name') }}" required></label>
                    <label>Email address<input type="email" name="guest_email" value="{{ old('guest_email') }}" required></label>
                    <label>Philippine phone number<input type="tel" name="guest_phone" value="{{ old('guest_phone') }}" placeholder="09169907895" pattern="[0-9+() -]+" title="Use 09169907895, 639169907895, or +63 916-990-7895" required></label>
                    <label>Street address<input name="billing_street" value="{{ old('billing_street') }}" required></label>
                    <label>City<input name="billing_city" value="{{ old('billing_city') }}" required></label>
                    <label>Province<input name="billing_province" value="{{ old('billing_province') }}" required></label>
                    <label>Postal code<input name="billing_postal_code" value="{{ old('billing_postal_code') }}" inputmode="numeric" pattern="[0-9]{4}" required></label>
                    <label class="checkbox">
                        <input name="billing_verified" type="checkbox" value="1" @checked(old('billing_verified')) required>
                        <span><strong>Confirm billing details</strong><small>I confirm that my billing contact and address are correct.</small></span>
                    </label>
                @endif

                <label>Payment method<select name="payment_method"><option value="gcash">GCash</option><option value="cash">Pay at property</option></select></label>
                <label>Special request<textarea name="special_request" rows="3">{{ old('special_request') }}</textarea></label>
                <button class="button">Continue to payment</button>
            </form>
        @endif
    </div>

    <aside class="booking-summary">
        <img src="{{ $room->image_url }}" alt="{{ $room->name }}">
        <h3>{{ $room->name }}</h3>
        <p>{{ $room->room_type }} · Up to {{ $room->guests }} guests</p>
        @if($bookingMode === 'hourly')
            <strong>₱{{ number_format($room->price_per_night / 24, 2) }} <small>/ hour</small></strong>
        @else
            <strong>₱{{ number_format($room->price_per_night) }} <small>/ night</small></strong>
        @endif
        <hr>
        <small>{{ $bookingMode === 'hourly' ? 'Your total is based on your selected hours.' : 'Final total is calculated from your dates.' }} A GCash payment link is sent after the reservation is reviewed.</small>
    </aside>
</section>
@if(auth()->check() || $isGuest)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const hourlyHours = document.getElementById('hourly-hours');
    if (hourlyHours) { const total = document.getElementById('hourly-total'); const rate = {{ $room->price_per_night / 24 }}; const refresh = () => total.textContent = `Estimated total: ₱${(rate * Number(hourlyHours.value)).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`; hourlyHours.addEventListener('change', refresh); refresh(); return; }
    const ranges = @json($blockedRanges);
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    const checkInDisplay = document.getElementById('check-in-display');
    const checkOutDisplay = document.getElementById('check-out-display');
    const days = document.getElementById('calendar-days');
    const monthLabel = document.getElementById('calendar-month');
    const note = document.getElementById('calendar-note');
    const today = new Date(); today.setHours(0, 0, 0, 0);
    let cursor = new Date(today.getFullYear(), today.getMonth(), 1);
    const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const pretty = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const occupied = (value) => ranges.some(range => value >= range.start && value < range.end);
    const overlap = (start, end) => ranges.some(range => start < range.end && end > range.start);
    const nextAvailable = () => { const date = new Date(today); while (occupied(iso(date))) date.setDate(date.getDate() + 1); return iso(date); };
    const refreshInputs = () => { checkInDisplay.value = pretty(checkIn.value); checkOutDisplay.value = pretty(checkOut.value); };
    const choose = (value) => {
        if (!checkIn.value || checkOut.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in selected. Now select your check-out date.'; }
        else if (value <= checkIn.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in updated. Now select your check-out date.'; }
        else if (overlap(checkIn.value, value)) { note.textContent = 'Those dates include an unavailable stay. Please choose dates outside the red period.'; return; }
        else { checkOut.value = value; note.textContent = 'Your selected dates are available.'; }
        refreshInputs(); render();
    };
    const render = () => {
        days.innerHTML = '';
        monthLabel.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
        for (let blank = 0; blank < first.getDay(); blank++) days.insertAdjacentHTML('beforeend', '<span class="calendar-blank"></span>');
        for (let number = 1; number <= last.getDate(); number++) {
            const date = new Date(cursor.getFullYear(), cursor.getMonth(), number);
            const value = iso(date); const isPast = date < today; const isBooked = occupied(value);
            const previous = new Date(date); previous.setDate(previous.getDate() - 1);
            const next = new Date(date); next.setDate(next.getDate() + 1);
            const bookedRange = isBooked ? ` booked${occupied(iso(previous)) ? '' : ' booked-start'}${occupied(iso(next)) ? '' : ' booked-end'}` : '';
            const selected = value === checkIn.value ? ' selected start' : value === checkOut.value ? ' selected end' : checkIn.value && checkOut.value && value > checkIn.value && value < checkOut.value ? ' selected range' : '';
            const disabled = isPast || isBooked ? ' disabled' : '';
            const state = isBooked ? bookedRange : isPast ? ' past' : '';
            days.insertAdjacentHTML('beforeend', `<button type="button" class="calendar-day${state}${selected}" data-date="${value}"${disabled}>${number}</button>`);
        }
        document.querySelectorAll('.calendar-day:not([disabled])').forEach(button => button.addEventListener('click', () => choose(button.dataset.date)));
        if (!checkIn.value && !note.textContent) note.textContent = `Next available date: ${pretty(nextAvailable())}. Select check-in, then check-out.`;
    };
    document.getElementById('previous-month').addEventListener('click', () => { const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; render(); } });
    document.getElementById('next-month').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
    checkInDisplay.addEventListener('click', () => document.querySelector('.availability-calendar').scrollIntoView({ behavior: 'smooth', block: 'center' }));
    checkOutDisplay.addEventListener('click', () => document.querySelector('.availability-calendar').scrollIntoView({ behavior: 'smooth', block: 'center' }));
    const refreshAvailability = async () => {
        try {
            const response = await fetch(document.querySelector('.availability-calendar').dataset.availabilityUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            ranges.splice(0, ranges.length, ...data.ranges);
            if (checkIn.value && checkOut.value && overlap(checkIn.value, checkOut.value)) {
                checkOut.value = ''; refreshInputs(); note.textContent = 'Availability changed. Please select a new check-out date.';
            }
            render();
        } catch (error) { /* Keep the last known availability if the connection is unavailable. */ }
    };
    setInterval(refreshAvailability, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshAvailability(); });
    refreshInputs(); render();
});
</script>
@endif
@endsection
