@extends('layouts.app')
@push('late-styles')
<link rel="stylesheet" href="{{ asset('css/rooms-premium.css') }}?v={{ filemtime(public_path('css/rooms-premium.css')) }}">
<link rel="stylesheet" href="{{ asset('css/promo-campaign.css') }}?v={{ filemtime(public_path('css/promo-campaign.css')) }}">
@endpush
@section('content')
<style>.room-promo-badge{display:inline-flex;margin:8px 0 0;padding:6px 9px;border-radius:999px;background:#fff0d8;color:#a05200;font-size:.72rem;font-weight:800}.dark-mode .room-promo-badge{background:#4a3220;color:#ffd391}</style>
<section class="page-hero rooms-hero"><div><span class="eyebrow">CAROLINA ROOMS</span><h1>Find a stay that feels right.</h1><p>Start with your arrival date, choose how long you’ll stay, and we’ll take care of the timing.</p></div></section>
<section class="ber-promo ber-promo-rooms" aria-labelledby="rooms-ber-promo-title"><div class="ber-promo-layout"><div class="ber-promo-copy"><span class="ber-promo-kicker">Ber-Months Promo · Code CAROLINA</span><h2 id="rooms-ber-promo-title">Fan Room Solo—with free breakfast.</h2><p>Book the 22-hour stay for only <strong>₱450</strong> and enter <strong>CAROLINA</strong> at checkout to save 10%.</p><div class="ber-promo-highlights"><span>Smart TV</span><span>WiFi</span><span>Parking with CCTV</span><span>Pet-friendly</span></div></div><a class="button" href="{{ route('rooms.show', 'fan-room-solo') }}">See Fan Room Solo <span aria-hidden="true">→</span></a></div></section>
<section class="section compact rooms-results">
    <form class="filter-bar rooms-search" method="GET">
        <input id="rooms-check-in" type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}">
        <button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false"><span>Check-in date</span><b>Choose date</b></button>
        <div class="rooms-choice" id="rooms-stay-choice"><span>Stay length</span><input id="rooms-stay" type="hidden" name="stay" value="{{ $filters['stay'] ?? 'day' }}"><button id="rooms-stay-trigger" type="button" aria-expanded="false">1 day stay</button><div class="rooms-choice-menu" id="rooms-stay-menu" hidden><button type="button" data-stay="day">1 day stay</button>@foreach([48 => '2 day stay', 72 => '3 day stay', 96 => '4 day stay', 120 => '5 day stay', 168 => '1 week stay', 'month' => '1 month stay'] as $hours => $label)<button type="button" data-stay="{{ $hours }}">{{ $label }}</button>@endforeach<div class="rooms-choice-divider">Rent by hour</div>@foreach([3,6,12,24] as $hours)<button type="button" data-stay="{{ $hours }}">{{ $hours }} hours</button>@endforeach</div></div>
        <div class="rooms-time-field" id="rooms-time-field" hidden>
            <span>Check-in time</span>
            <input id="rooms-check-in-time" name="check_in_time" type="hidden" value="{{ $filters['check_in_time'] ?? '12:00' }}">
            <button id="rooms-time-trigger" type="button" aria-expanded="false">Choose time</button>
            <div class="rooms-time-menu" id="rooms-time-menu" hidden>@for($hour = 6; $hour < 24; $hour++)<button type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div>
        </div>
        <div class="rooms-choice" id="rooms-guests-choice"><span>Guests</span><input id="rooms-guests" type="hidden" name="guests" value="{{ $filters['guests'] ?? '' }}"><button id="rooms-guests-trigger" type="button" aria-expanded="false">Any guests</button><div class="rooms-choice-menu" id="rooms-guests-menu" hidden><button type="button" data-guests="">Any guests</button>@foreach([1,2,3,4,5,6] as $number)<button type="button" data-guests="{{ $number }}">{{ $number }}+ guests</button>@endforeach</div></div>
        <button class="button">Check availability</button>
        <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose check-in date">
            <div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div>
            <div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="rooms-calendar-days" id="rooms-calendar-days"></div>
            <p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date. Your stay length sets the check-out automatically.</p>
        </section>
    </form>

    <p class="result-count">{{ $rooms->count() }} room{{ $rooms->count() === 1 ? '' : 's' }} available</p>
    <div class="room-grid">
        @forelse($rooms as $room)
            <article class="room-card rooms-card">
                <img src="{{ $room->image_url }}" alt="{{ $room->name }}" @if($loop->index > 2) loading="lazy" @endif>
                <div class="room-card-body">
                    <span>{{ $room->room_type }} · {{ $room->beds }} bed{{ $room->beds > 1 ? 's' : '' }} · {{ $room->guests }} guests</span>
                    <h3>{{ $room->name }}</h3>
                    @if($room->promoCodes->isNotEmpty())<small class="room-promo-badge">{{ $room->promoCodes->first()->name }} · {{ $room->promoCodes->first()->code }}</small>@endif
                    @if($room->approved_reviews_count)<small class="card-rating">★ {{ number_format($room->approved_reviews_avg_rating, 1) }} · {{ $room->approved_reviews_count }} {{ Str::plural('review', $room->approved_reviews_count) }}</small>@endif
                    <p>{{ Str::limit($room->description, 86) }}</p>
                    <div class="rooms-card-footer"><strong>₱{{ number_format($room->price_per_night) }} <small>{{ $room->rate_label }}</small></strong><a class="button small" href="{{ route('rooms.show', $room) }}">View room <span aria-hidden="true">→</span></a></div>
                </div>
            </article>
        @empty
            <div class="empty-state"><h2>No rooms found</h2><p>Try another date, stay length, or smaller group.</p><a class="text-link" href="{{ route('rooms.index') }}">Clear search</a></div>
        @endforelse
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendar=document.getElementById('rooms-search-calendar'),days=document.getElementById('rooms-calendar-days'),month=document.getElementById('rooms-calendar-month'),note=document.getElementById('rooms-calendar-note'),checkIn=document.getElementById('rooms-check-in'),trigger=document.getElementById('rooms-check-in-trigger'),stay=document.getElementById('rooms-stay'),stayChoice=document.getElementById('rooms-stay-choice'),stayTrigger=document.getElementById('rooms-stay-trigger'),stayMenu=document.getElementById('rooms-stay-menu'),guests=document.getElementById('rooms-guests'),guestsChoice=document.getElementById('rooms-guests-choice'),guestsTrigger=document.getElementById('rooms-guests-trigger'),guestsMenu=document.getElementById('rooms-guests-menu'),timeField=document.getElementById('rooms-time-field'),timeValue=document.getElementById('rooms-check-in-time'),timeTrigger=document.getElementById('rooms-time-trigger'),timeMenu=document.getElementById('rooms-time-menu');
    const today=new Date(); today.setHours(0,0,0,0); let cursor=new Date(today.getFullYear(),today.getMonth(),1);
    const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const pretty=v=>v?new Date(`${v}T00:00:00`).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}):'Choose date';
    const prettyTime=value=>new Date(`2000-01-01T${value}`).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit'});
    const stayLabel=value=>value==='day'?'1 day stay':({48:'2 day stay',72:'3 day stay',96:'4 day stay',120:'5 day stay',168:'1 week stay',month:'1 month stay'}[value]||`${value} hours`);
    const sync=()=>{trigger.querySelector('b').textContent=pretty(checkIn.value);stayTrigger.textContent=stayLabel(stay.value);guestsTrigger.textContent=guests.value?`${guests.value}+ guests`:'Any guests';timeField.hidden=false;timeTrigger.textContent=prettyTime(timeValue.value);note.textContent='Choose your check-in date and time. Your stay length sets the check-out automatically.'};
    const render=()=>{days.innerHTML='';month.textContent=cursor.toLocaleDateString('en-PH',{month:'long',year:'numeric'});const first=new Date(cursor.getFullYear(),cursor.getMonth(),1),last=new Date(cursor.getFullYear(),cursor.getMonth()+1,0);for(let i=0;i<first.getDay();i++)days.insertAdjacentHTML('beforeend','<span class="rooms-calendar-blank"></span>');for(let n=1;n<=last.getDate();n++){const d=new Date(cursor.getFullYear(),cursor.getMonth(),n),v=iso(d);days.insertAdjacentHTML('beforeend',`<button type="button" class="rooms-calendar-day${v===checkIn.value?' selected start':''}${d<today?' past':''}" data-date="${v}" ${d<today?'disabled':''}>${n}</button>`)}days.querySelectorAll('.rooms-calendar-day:not([disabled])').forEach(b=>b.addEventListener('click',()=>{checkIn.value=b.dataset.date;calendar.hidden=true;trigger.setAttribute('aria-expanded','false');sync();render()}));};
    const closeMenus=()=>{[stayMenu,guestsMenu,timeMenu].forEach(menu=>menu.hidden=true);[stayTrigger,guestsTrigger,timeTrigger].forEach(button=>button.setAttribute('aria-expanded','false'))};
    const toggleMenu=(menu,button)=>{const opening=menu.hidden;closeMenus();menu.hidden=!opening;button.setAttribute('aria-expanded',String(opening))};
    trigger.addEventListener('click',()=>{calendar.hidden=!calendar.hidden;trigger.setAttribute('aria-expanded',String(!calendar.hidden));render()});stayTrigger.addEventListener('click',event=>{event.stopPropagation();toggleMenu(stayMenu,stayTrigger)});guestsTrigger.addEventListener('click',event=>{event.stopPropagation();toggleMenu(guestsMenu,guestsTrigger)});stayMenu.querySelectorAll('[data-stay]').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();stay.value=button.dataset.stay;closeMenus();sync()}));guestsMenu.querySelectorAll('[data-guests]').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();event.stopPropagation();guests.value=button.dataset.guests;closeMenus();sync()}));timeTrigger.addEventListener('click',event=>{event.stopPropagation();toggleMenu(timeMenu,timeTrigger)});timeMenu.querySelectorAll('[data-time]').forEach(button=>button.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();timeValue.value=button.dataset.time;closeMenus();sync()}));document.getElementById('rooms-calendar-prev').addEventListener('click',()=>{const p=new Date(cursor.getFullYear(),cursor.getMonth()-1,1);if(p>=new Date(today.getFullYear(),today.getMonth(),1)){cursor=p;render()}});document.getElementById('rooms-calendar-next').addEventListener('click',()=>{cursor=new Date(cursor.getFullYear(),cursor.getMonth()+1,1);render()});document.addEventListener('click',event=>{if(!calendar.hidden&&!calendar.contains(event.target)&&!trigger.contains(event.target)){calendar.hidden=true;trigger.setAttribute('aria-expanded','false')}if(!stayChoice.contains(event.target)&&!guestsChoice.contains(event.target)&&!timeField.contains(event.target))closeMenus()});sync();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const labelDate = value => new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    const labelTime = value => new Date(value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });

    document.querySelectorAll('.room-card-availability').forEach(card => {
        const toggle = card.querySelector('.room-availability-toggle');
        const panel = card.querySelector('.room-availability-panel');
        const days = card.querySelector('[data-calendar-days]');
        const monthLabel = card.querySelector('[data-month-label]');
        const detail = card.querySelector('.room-availability-detail');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let cursor = new Date(today.getFullYear(), today.getMonth(), 1);
        let ranges = [], slots = [], loaded = false, selectedDate = '';
        const forDate = value => {
            const start = new Date(`${value}T00:00:00`);
            const end = new Date(start);
            end.setDate(end.getDate() + 1);
            return slots.filter(slot => new Date(slot.start) < end && new Date(slot.end) > start);
        };
        const renderDetail = value => {
            selectedDate = value;
            const bookings = forDate(value);
            detail.textContent = '';
            const heading = document.createElement('strong');
            heading.textContent = labelDate(value);
            detail.append(heading);
            if (!bookings.length) {
                const free = document.createElement('span');
                free.textContent = 'No booked time recorded for this date.';
                detail.append(free);
                return;
            }
            bookings.forEach(slot => {
                const dateStart = new Date(`${value}T00:00:00`);
                const dateEnd = new Date(dateStart);
                dateEnd.setDate(dateEnd.getDate() + 1);
                const slotStart = new Date(slot.start), slotEnd = new Date(slot.end);
                const overlapStart = new Date(Math.max(dateStart.getTime(), slotStart.getTime()));
                const overlapEnd = new Date(Math.min(dateEnd.getTime(), slotEnd.getTime()));
                const allDay = overlapStart.getTime() === dateStart.getTime() && overlapEnd.getTime() === dateEnd.getTime();
                const row = document.createElement('span');
                row.className = 'room-availability-booked';
                row.textContent = allDay ? 'Unavailable · all day' : `Unavailable · ${labelTime(overlapStart.toISOString())}–${labelTime(overlapEnd.toISOString())}`;
                detail.append(row);
            });
        };
        const render = () => {
            days.innerHTML = '';
            monthLabel.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
            for (let blank = 0; blank < first.getDay(); blank++) days.insertAdjacentHTML('beforeend', '<span class="room-availability-blank"></span>');
            for (let number = 1; number <= last.getDate(); number++) {
                const date = new Date(cursor.getFullYear(), cursor.getMonth(), number);
                const value = iso(date);
                const booked = ranges.some(range => value >= range.start && value < range.end) || forDate(value).length > 0;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `room-availability-day${booked ? ' booked' : ''}${selectedDate === value ? ' selected' : ''}`;
                button.textContent = String(number);
                button.setAttribute('aria-label', `${labelDate(value)}${booked ? ', unavailable' : ', no booking recorded'}`);
                if (booked) button.title = 'Unavailable — reservation or room operation recorded';
                button.addEventListener('click', () => { renderDetail(value); render(); });
                days.append(button);
            }
        };
        const load = async () => {
            if (loaded) return;
            detail.textContent = 'Loading this room’s schedule…';
            try {
                const response = await fetch(card.dataset.availabilityUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Availability unavailable');
                const data = await response.json();
                ranges = Array.isArray(data.ranges) ? data.ranges : [];
                slots = Array.isArray(data.slots) ? data.slots : [];
                loaded = true;
                render();
                renderDetail(selectedDate || iso(today));
            } catch (error) {
                detail.textContent = 'This room’s availability could not be loaded. Please try again.';
            }
        };
        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
            toggle.lastElementChild.textContent = panel.hidden ? '＋' : '−';
            if (!panel.hidden) load();
        });
        card.querySelector('[data-month-prev]').addEventListener('click', () => {
            const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
            if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; render(); }
        });
        card.querySelector('[data-month-next]').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
    });
});
</script>
@endsection
