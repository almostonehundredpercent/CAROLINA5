@extends('layouts.app')
@push('late-styles')
<link rel="stylesheet" href="{{ asset('css/rooms-premium.css') }}?v={{ filemtime(public_path('css/rooms-premium.css')) }}">
<link rel="stylesheet" href="{{ asset('css/promo-campaign.css') }}?v={{ filemtime(public_path('css/promo-campaign.css')) }}">
@endpush
@section('content')
<style>.room-promo-badge{display:inline-flex;margin:8px 0 0;padding:6px 9px;border-radius:999px;background:#fff0d8;color:#a05200;font-size:.72rem;font-weight:800}.dark-mode .room-promo-badge{background:#4a3220;color:#ffd391}</style>
<section class="page-hero rooms-hero"><div><h1>Find a room</h1><p>Choose your dates and stay length.</p></div></section>
<section class="section compact rooms-results">
    <form class="filter-bar rooms-search" method="GET">
        <input id="rooms-check-in" type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}">
        <div class="rooms-date-control" id="rooms-date-control"><button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false" aria-controls="rooms-search-calendar"><span>Check-in date</span><b>Choose date</b></button>
        <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose check-in date"><div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div><div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="rooms-calendar-days" id="rooms-calendar-days"></div><p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date and time. Your stay length sets the check-out automatically.</p></section></div>
        <div class="rooms-choice" id="rooms-stay-choice"><span>Stay length</span><input id="rooms-stay" type="hidden" name="stay" value="{{ $filters['stay'] ?? 'day' }}"><button id="rooms-stay-trigger" type="button" aria-expanded="false">1 day stay</button><div class="rooms-choice-menu" id="rooms-stay-menu" hidden><button type="button" data-stay="day">1 day stay</button>@foreach([48 => '2 day stay', 72 => '3 day stay', 96 => '4 day stay', 120 => '5 day stay', 168 => '1 week stay', 'month' => '1 month stay'] as $hours => $label)<button type="button" data-stay="{{ $hours }}">{{ $label }}</button>@endforeach<div class="rooms-choice-divider">Rent by hour</div>@foreach([3,6,12,24] as $hours)<button type="button" data-stay="{{ $hours }}">{{ $hours }} hours</button>@endforeach</div></div>
        <div class="rooms-time-field" id="rooms-time-field" hidden>
            <span>Check-in time</span>
            <input id="rooms-check-in-time" name="check_in_time" type="hidden" value="{{ $filters['check_in_time'] ?? '12:00' }}">
            <button id="rooms-time-trigger" type="button" aria-expanded="false">Choose time</button>
            <div class="rooms-time-menu" id="rooms-time-menu" hidden>@for($hour = 6; $hour < 24; $hour++)<button type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div>
        </div>
        <div class="rooms-choice" id="rooms-guests-choice"><span>Guests</span><input id="rooms-guests" type="hidden" name="guests" value="{{ $filters['guests'] ?? '' }}"><button id="rooms-guests-trigger" type="button" aria-expanded="false">Any guests</button><div class="rooms-choice-menu" id="rooms-guests-menu" hidden><button type="button" data-guests="">Any guests</button>@foreach([1,2,3,4,5,6] as $number)<button type="button" data-guests="{{ $number }}">{{ $number }}+ guests</button>@endforeach</div></div>
        @php($roomSort = $filters['sort'] ?? 'recommended')
        <div class="rooms-sort-control" id="rooms-sort-control"><span id="rooms-sort-label">Sort by price</span><input id="rooms-sort" type="hidden" name="sort" value="{{ $roomSort }}"><button id="rooms-sort-trigger" type="button" aria-labelledby="rooms-sort-label rooms-sort-current" aria-expanded="false" aria-controls="rooms-sort-menu"><span id="rooms-sort-current">{{ ['recommended' => 'Recommended', 'price_asc' => 'Lowest first', 'price_desc' => 'Highest first'][$roomSort] ?? 'Recommended' }}</span></button><div class="rooms-sort-menu" id="rooms-sort-menu" role="group" aria-label="Sort by price" hidden>@foreach(['recommended' => ['Recommended', 'Best overall match'], 'price_asc' => ['Lowest first', 'Lower price to higher'], 'price_desc' => ['Highest first', 'Higher price to lower']] as $sortValue => [$sortLabel, $sortDescription])<button type="button" data-sort="{{ $sortValue }}" aria-pressed="{{ $roomSort === $sortValue ? 'true' : 'false' }}"><span>{{ $sortLabel }}</span><small>{{ $sortDescription }}</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button>@endforeach</div></div>
        <button class="button">Check availability</button>
    </form>

    @include('rooms.partials.promo-banner')
    <p class="result-count">{{ $rooms->whereNull('availability_reason')->count() }} available · {{ $rooms->whereNotNull('availability_reason')->count() }} unavailable{{ isset($filters['check_in']) ? ' for your stay' : ' right now' }}</p>
    @php($roomPages = $rooms->chunk(6)->values())
    <div class="rooms-carousel" id="rooms-carousel">
        <p class="rooms-page-status" id="rooms-page-status" aria-live="polite">{{ $rooms->isEmpty() ? 'No rooms to display' : 'Showing rooms 1–'.min(6, $rooms->count()).' of '.$rooms->count().($roomPages->count() > 1 ? ' · Group 1 of '.$roomPages->count() : '') }}</p>
        <button class="rooms-page-arrow rooms-page-arrow-previous" type="button" id="rooms-page-previous" aria-label="Show previous six rooms" aria-controls="rooms-page-track" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="rooms-page-track" id="rooms-page-track">
            @forelse($roomPages as $pageIndex => $roomPage)
                <div class="rooms-page" role="group" data-room-page="{{ $pageIndex + 1 }}" aria-label="Rooms {{ $pageIndex * 6 + 1 }} to {{ min(($pageIndex + 1) * 6, $rooms->count()) }}">
                    <div class="room-grid">
                        @foreach($roomPage as $room)
                            <article class="room-card rooms-card">
                                <img src="{{ $room->image_url }}" alt="{{ $room->name }}" @if($roomSrcset = $room->responsiveImageSrcset()) srcset="{{ $roomSrcset }}" sizes="(max-width: 650px) 82vw, (max-width: 900px) 45vw, 30vw" @endif @if(($pageIndex * 6 + $loop->index) > 2) loading="lazy" @endif decoding="async">
                                <div class="room-card-body">
                                    <span>{{ $room->room_type }} · {{ $room->beds }} bed{{ $room->beds > 1 ? 's' : '' }} · {{ $room->guests }} guests</span>
                                    <h3>{{ $room->name }}</h3>
                                    @php($hasStaySearch = isset($filters['check_in']))
                                    <div class="room-availability-line"><span class="room-availability-badge {{ $room->availability_reason ? 'is-unavailable' : 'is-available' }}">{{ $room->availability_reason ? ($hasStaySearch ? 'Unavailable for stay' : 'Unavailable now') : ($hasStaySearch ? 'Available for stay' : 'Available now') }}</span>@if($room->availability_reason)<small>{{ $room->availability_reason === 'Maintenance' ? '(Maintenance)' : $room->availability_reason }}</small>@endif</div>
                                    <section class="room-card-availability" data-availability-url="{{ route('rooms.availability', $room) }}">
                                        <button class="room-availability-toggle" type="button" aria-expanded="false" aria-controls="room-availability-{{ $room->id }}">
                                            <span><b>View booked dates</b><small>Unavailable dates are marked in red.</small></span><span aria-hidden="true">＋</span>
                                        </button>
                                        <div class="room-availability-panel" id="room-availability-{{ $room->id }}" hidden>
                                            <div class="room-availability-calendar" role="group" aria-label="Availability for {{ $room->name }}">
                                                <div class="room-availability-head"><button type="button" data-month-prev aria-label="Previous month">‹</button><b data-month-label></b><button type="button" data-month-next aria-label="Next month">›</button></div>
                                                <div class="room-availability-week" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                                                <div class="room-availability-days" data-calendar-days></div>
                                                <div class="room-availability-detail" aria-live="polite">Choose a date to see its booked times.</div>
                                                <p class="room-availability-legend"><i aria-hidden="true"></i>Booked or unavailable</p>
                                            </div>
                                        </div>
                                    </section>
                                    @if($room->promoCodes->isNotEmpty())<small class="room-promo-badge">{{ $room->promoCodes->first()->name }} · {{ $room->promoCodes->first()->code }}</small>@endif
                                    @include('rooms.partials.card-rating', ['room' => $room])
                                    <p>{{ Str::limit($room->description, 86) }}</p>
                                    <div class="rooms-card-footer"><strong>₱{{ number_format($room->price_per_night) }} <small>{{ $room->rate_label }}</small></strong><a class="button small" href="{{ route('rooms.show', ['room' => $room] + $filters) }}">{{ $room->availability_reason ? ($hasStaySearch ? 'Try other dates' : 'Check dates') : 'View room' }} <span aria-hidden="true">→</span></a></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="room-grid"><div class="empty-state"><h2>No rooms found</h2><p>Try another date, stay length, or smaller group.</p><a class="text-link" href="{{ route('rooms.index') }}">Clear search</a></div></div>
            @endforelse
        </div>
        <button class="rooms-page-arrow rooms-page-arrow-next" type="button" id="rooms-page-next" aria-label="Show next six rooms" aria-controls="rooms-page-track" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendar=document.getElementById('rooms-search-calendar'),days=document.getElementById('rooms-calendar-days'),month=document.getElementById('rooms-calendar-month'),note=document.getElementById('rooms-calendar-note'),checkIn=document.getElementById('rooms-check-in'),trigger=document.getElementById('rooms-check-in-trigger'),stay=document.getElementById('rooms-stay'),stayChoice=document.getElementById('rooms-stay-choice'),stayTrigger=document.getElementById('rooms-stay-trigger'),stayMenu=document.getElementById('rooms-stay-menu'),guests=document.getElementById('rooms-guests'),guestsChoice=document.getElementById('rooms-guests-choice'),guestsTrigger=document.getElementById('rooms-guests-trigger'),guestsMenu=document.getElementById('rooms-guests-menu'),timeField=document.getElementById('rooms-time-field'),timeValue=document.getElementById('rooms-check-in-time'),timeTrigger=document.getElementById('rooms-time-trigger'),timeMenu=document.getElementById('rooms-time-menu'),sortControl=document.getElementById('rooms-sort-control'),sortTrigger=document.getElementById('rooms-sort-trigger'),sortMenu=document.getElementById('rooms-sort-menu'),sortValue=document.getElementById('rooms-sort'),sortCurrent=document.getElementById('rooms-sort-current');
    const today=new Date(); today.setHours(0,0,0,0); let cursor=new Date(today.getFullYear(),today.getMonth(),1);
    const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const pretty=v=>v?new Date(`${v}T00:00:00`).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}):'Choose date';
    const prettyTime=value=>new Date(`2000-01-01T${value}`).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit'});
    const stayLabel=value=>value==='day'?'1 day stay':({48:'2 day stay',72:'3 day stay',96:'4 day stay',120:'5 day stay',168:'1 week stay',month:'1 month stay'}[value]||`${value} hours`);
    const sync=()=>{trigger.querySelector('b').textContent=pretty(checkIn.value);stayTrigger.textContent=stayLabel(stay.value);guestsTrigger.textContent=guests.value?`${guests.value}+ guests`:'Any guests';timeField.hidden=false;timeTrigger.textContent=prettyTime(timeValue.value);note.textContent='Choose your check-in date and time. Your stay length sets the check-out automatically.'};
    const render=()=>{days.innerHTML='';month.textContent=cursor.toLocaleDateString('en-PH',{month:'long',year:'numeric'});const first=new Date(cursor.getFullYear(),cursor.getMonth(),1),last=new Date(cursor.getFullYear(),cursor.getMonth()+1,0);for(let i=0;i<first.getDay();i++)days.insertAdjacentHTML('beforeend','<span class="rooms-calendar-blank"></span>');for(let n=1;n<=last.getDate();n++){const d=new Date(cursor.getFullYear(),cursor.getMonth(),n),v=iso(d);days.insertAdjacentHTML('beforeend',`<button type="button" class="rooms-calendar-day${v===checkIn.value?' selected start':''}${d<today?' past':''}" data-date="${v}" ${d<today?'disabled':''}>${n}</button>`)}days.querySelectorAll('.rooms-calendar-day:not([disabled])').forEach(b=>b.addEventListener('click',()=>{checkIn.value=b.dataset.date;closeCalendar();sync();render()}));};
    const menus=[{menu:stayMenu,button:stayTrigger,owner:stayChoice},{menu:guestsMenu,button:guestsTrigger,owner:guestsChoice},{menu:timeMenu,button:timeTrigger,owner:timeField},{menu:sortMenu,button:sortTrigger,owner:sortControl}];
    const closeMenus=()=>menus.forEach(({menu,button,owner})=>{menu.hidden=true;button.setAttribute('aria-expanded','false');owner.classList.remove('is-open')});
    const dateControl=document.getElementById('rooms-date-control');
    const closeCalendar=()=>{calendar.hidden=true;trigger.setAttribute('aria-expanded','false');dateControl.classList.remove('is-open')};
    const closeAll=()=>{closeMenus();closeCalendar()};
    const toggleMenu=(menu,button,owner)=>{const opening=menu.hidden;closeMenus();closeCalendar();if(opening){menu.hidden=false;button.setAttribute('aria-expanded','true');owner.classList.add('is-open')}};
    trigger.addEventListener('click',()=>{const opening=calendar.hidden;closeMenus();closeCalendar();if(opening){calendar.hidden=false;trigger.setAttribute('aria-expanded','true');dateControl.classList.add('is-open');render()}});
    stayTrigger.addEventListener('click',()=>toggleMenu(stayMenu,stayTrigger,stayChoice));
    guestsTrigger.addEventListener('click',()=>toggleMenu(guestsMenu,guestsTrigger,guestsChoice));
    stayMenu.querySelectorAll('[data-stay]').forEach(button=>button.addEventListener('click',()=>{stay.value=button.dataset.stay;closeMenus();sync()}));
    guestsMenu.querySelectorAll('[data-guests]').forEach(button=>button.addEventListener('click',()=>{guests.value=button.dataset.guests;closeMenus();sync()}));
    timeTrigger.addEventListener('click',()=>toggleMenu(timeMenu,timeTrigger,timeField));
    timeMenu.querySelectorAll('[data-time]').forEach(button=>button.addEventListener('click',event=>{event.preventDefault();timeValue.value=button.dataset.time;closeMenus();sync()}));
    sortTrigger.addEventListener('click',()=>toggleMenu(sortMenu,sortTrigger,sortControl));
    sortMenu.querySelectorAll('[data-sort]').forEach(button=>button.addEventListener('click',()=>{sortValue.value=button.dataset.sort;sortCurrent.textContent=button.querySelector('span').textContent;sortMenu.querySelectorAll('[data-sort]').forEach(option=>option.setAttribute('aria-pressed',String(option===button)));closeMenus();sortTrigger.form.requestSubmit()}));
    document.getElementById('rooms-calendar-prev').addEventListener('click',()=>{const p=new Date(cursor.getFullYear(),cursor.getMonth()-1,1);if(p>=new Date(today.getFullYear(),today.getMonth(),1)){cursor=p;render()}});
    document.getElementById('rooms-calendar-next').addEventListener('click',()=>{cursor=new Date(cursor.getFullYear(),cursor.getMonth()+1,1);render()});
    document.addEventListener('click',event=>{if(!calendar.hidden&&!calendar.contains(event.target)&&!trigger.contains(event.target))closeCalendar();if(!menus.some(({owner})=>owner.contains(event.target)))closeMenus()});
    document.addEventListener('keydown',event=>{if(event.key!=='Escape')return;const openMenu=menus.find(({menu})=>!menu.hidden);if(calendar.hidden&&!openMenu)return;const returnFocus=openMenu?.button||(calendar.hidden?null:trigger);closeAll();returnFocus?.focus()});
    sync();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.getElementById('rooms-carousel');
    const pages = [...(carousel?.querySelectorAll('[data-room-page]') || [])];
    const previous = document.getElementById('rooms-page-previous');
    const next = document.getElementById('rooms-page-next');
    const status = document.getElementById('rooms-page-status');
    if (pages.length < 2) return;

    let currentPage = 1;
    const showPage = page => {
        currentPage = Math.max(1, Math.min(pages.length, page));
        pages.forEach((element, index) => { element.hidden = index + 1 !== currentPage; });
        const firstRoom = (currentPage - 1) * 6 + 1;
        const lastRoom = Math.min(currentPage * 6, {{ $rooms->count() }});
        status.textContent = `Showing rooms ${firstRoom}–${lastRoom} of {{ $rooms->count() }} · Group ${currentPage} of ${pages.length}`;
        previous.hidden = false;
        next.hidden = false;
        previous.disabled = currentPage === 1;
        next.disabled = currentPage === pages.length;
    };
    previous.addEventListener('click', () => showPage(currentPage - 1));
    next.addEventListener('click', () => showPage(currentPage + 1));
    showPage(currentPage);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const nextDate = value => { const date = new Date(`${value}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + 1); return date.toISOString().slice(0, 10); };
    const wallStamp = value => String(value).replace(' ', 'T').slice(0, 19);
    const labelTime = value => new Date(`${wallStamp(value)}Z`).toLocaleTimeString('en-PH', { timeZone: 'UTC', hour: 'numeric', minute: '2-digit' });
    const labelDate = value => new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });

    const mobileViewport = window.matchMedia('(max-width: 650px)');
    const roomCards = [...document.querySelectorAll('.room-card-availability')];
    const closeCalendar = card => {
        const panel = card.querySelector('.room-availability-panel');
        if (panel.hidden) return;
        panel.hidden = true;
        const toggle = card.querySelector('.room-availability-toggle');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.lastElementChild.textContent = '＋';
    };
    const closeOtherCalendars = activeCard => {
        if (!mobileViewport.matches) return;
        roomCards.forEach(card => { if (card !== activeCard) closeCalendar(card); });
    };
    const limitOpenCalendarsOnMobile = () => {
        if (!mobileViewport.matches) return;
        let keptOpenCalendar = false;
        roomCards.forEach(card => {
            const panel = card.querySelector('.room-availability-panel');
            if (panel.hidden) return;
            if (keptOpenCalendar) closeCalendar(card);
            else keptOpenCalendar = true;
        });
    };
    mobileViewport.addEventListener('change', limitOpenCalendarsOnMobile);

    roomCards.forEach(card => {
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
            const start = `${value}T00:00:00`;
            const end = `${nextDate(value)}T00:00:00`;
            return slots.filter(slot => wallStamp(slot.start) < end && wallStamp(slot.end) > start);
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
                const dateStart = `${value}T00:00:00`;
                const dateEnd = `${nextDate(value)}T00:00:00`;
                const slotStart = wallStamp(slot.start), slotEnd = wallStamp(slot.end);
                const overlapStart = slotStart > dateStart ? slotStart : dateStart;
                const overlapEnd = slotEnd < dateEnd ? slotEnd : dateEnd;
                const allDay = overlapStart === dateStart && overlapEnd === dateEnd;
                const row = document.createElement('span');
                row.className = 'room-availability-booked';
                row.textContent = allDay ? 'Unavailable · all day' : `Unavailable · ${labelTime(overlapStart)}–${labelTime(overlapEnd)}`;
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
                button.dataset.date = value;
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
                if (response.status === 429) throw new Error('rate-limited');
                if (!response.ok) throw new Error('Availability unavailable');
                const data = await response.json();
                ranges = Array.isArray(data.ranges) ? data.ranges : [];
                slots = Array.isArray(data.slots) ? data.slots : [];
                loaded = true;
                render();
                renderDetail(selectedDate || iso(today));
            } catch (error) {
                detail.textContent = error.message === 'rate-limited'
                    ? 'Too many availability checks. Please wait a minute and try again.'
                    : 'This room’s availability could not be loaded. Please try again.';
            }
        };
        toggle.addEventListener('click', () => {
            if (panel.hidden) closeOtherCalendars(card);
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
@include('rooms.partials.refresh-on-property-day-change')
@endsection
