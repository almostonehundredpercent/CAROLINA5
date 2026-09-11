@extends('layouts.app')
@section('content')
<section class="page-hero"><span class="eyebrow">CAROLINA ROOMS</span><h1>Find the room that fits your stay.</h1><p>Choose one arrival date and the length of your stay. We calculate the end time for you.</p></section>
<section class="section compact">
    <form class="filter-bar rooms-search" method="GET">
        <input id="rooms-check-in" type="hidden" name="check_in" value="{{ $filters['check_in'] ?? '' }}">
        <button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false"><span>Check-in date</span><b>Choose date</b></button>
        <select name="stay" id="rooms-stay" aria-label="Length of stay">
            <option value="day" @selected(($filters['stay'] ?? 'day') === 'day')>1 day stay</option>
            <option value="3" @selected(($filters['stay'] ?? '') === '3')>Rent by hour · 3 hours</option>
            <option value="6" @selected(($filters['stay'] ?? '') === '6')>Rent by hour · 6 hours</option>
            <option value="12" @selected(($filters['stay'] ?? '') === '12')>Rent by hour · 12 hours</option>
            <option value="24" @selected(($filters['stay'] ?? '') === '24')>Rent by hour · 24 hours</option>
        </select>
        <div class="rooms-time-field" id="rooms-time-field" hidden>
            <span>Check-in time</span>
            <input id="rooms-check-in-time" name="check_in_time" type="hidden" value="{{ $filters['check_in_time'] ?? '12:00' }}">
            <button id="rooms-time-trigger" type="button" aria-expanded="false">Choose time</button>
            <div class="rooms-time-menu" id="rooms-time-menu" hidden>@for($hour = 6; $hour < 24; $hour++)<button type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div>
        </div>
        <select name="guests"><option value="">Any guests</option>@foreach([1,2,3,4,5,6] as $number)<option value="{{ $number }}" @selected(($filters['guests'] ?? '') == $number)>{{ $number }}+ guests</option>@endforeach</select>
        <button class="button">Check availability</button>
        <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose check-in date">
            <div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div>
            <div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="rooms-calendar-days" id="rooms-calendar-days"></div>
            <p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date. Your stay length sets the check-out automatically.</p>
        </section>
    </form>

    <p class="result-count">{{ $rooms->count() }} room{{ $rooms->count() === 1 ? '' : 's' }} available</p>
    <div class="room-grid">@forelse($rooms as $room)<article class="room-card"><img src="{{ $room->image_url }}" alt="{{ $room->name }}"><div class="room-card-body"><span>{{ $room->room_type }} · {{ $room->beds }} bed{{ $room->beds > 1 ? 's' : '' }} · {{ $room->guests }} guests</span><h3>{{ $room->name }}</h3>@if($room->approved_reviews_count)<small class="card-rating">★ {{ number_format($room->approved_reviews_avg_rating, 1) }} · {{ $room->approved_reviews_count }} {{ Str::plural('review', $room->approved_reviews_count) }}</small>@endif<p>{{ Str::limit($room->description, 86) }}</p><strong>₱{{ number_format($room->price_per_night) }} <small>{{ $room->rate_label }}</small></strong><a class="button small" href="{{ route('rooms.show', $room) }}">Select room</a></div></article>@empty<div class="empty-state"><h2>No rooms found</h2><p>Try another date, stay length, or smaller group.</p><a class="text-link" href="{{ route('rooms.index') }}">Clear search</a></div>@endforelse</div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendar=document.getElementById('rooms-search-calendar'),days=document.getElementById('rooms-calendar-days'),month=document.getElementById('rooms-calendar-month'),note=document.getElementById('rooms-calendar-note'),checkIn=document.getElementById('rooms-check-in'),trigger=document.getElementById('rooms-check-in-trigger'),stay=document.getElementById('rooms-stay'),timeField=document.getElementById('rooms-time-field'),timeValue=document.getElementById('rooms-check-in-time'),timeTrigger=document.getElementById('rooms-time-trigger'),timeMenu=document.getElementById('rooms-time-menu');
    const today=new Date(); today.setHours(0,0,0,0); let cursor=new Date(today.getFullYear(),today.getMonth(),1);
    const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const pretty=v=>v?new Date(`${v}T00:00:00`).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}):'Choose date';
    const prettyTime=value=>new Date(`2000-01-01T${value}`).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit'});
    const sync=()=>{trigger.querySelector('b').textContent=pretty(checkIn.value);timeField.hidden=stay.value==='day';timeTrigger.textContent=prettyTime(timeValue.value);note.textContent=stay.value==='day'?'Choose your check-in date. Your one-day stay ends automatically the next day.':'Choose your check-in date and arrival time. Your hourly check-out is calculated automatically.'};
    const render=()=>{days.innerHTML='';month.textContent=cursor.toLocaleDateString('en-PH',{month:'long',year:'numeric'});const first=new Date(cursor.getFullYear(),cursor.getMonth(),1),last=new Date(cursor.getFullYear(),cursor.getMonth()+1,0);for(let i=0;i<first.getDay();i++)days.insertAdjacentHTML('beforeend','<span class="rooms-calendar-blank"></span>');for(let n=1;n<=last.getDate();n++){const d=new Date(cursor.getFullYear(),cursor.getMonth(),n),v=iso(d);days.insertAdjacentHTML('beforeend',`<button type="button" class="rooms-calendar-day${v===checkIn.value?' selected start':''}${d<today?' past':''}" data-date="${v}" ${d<today?'disabled':''}>${n}</button>`)}days.querySelectorAll('.rooms-calendar-day:not([disabled])').forEach(b=>b.addEventListener('click',()=>{checkIn.value=b.dataset.date;calendar.hidden=true;trigger.setAttribute('aria-expanded','false');sync();render()}));};
    trigger.addEventListener('click',()=>{calendar.hidden=!calendar.hidden;trigger.setAttribute('aria-expanded',String(!calendar.hidden));render()});stay.addEventListener('change',sync);timeTrigger.addEventListener('click',event=>{event.stopPropagation();timeMenu.hidden=!timeMenu.hidden;timeTrigger.setAttribute('aria-expanded',String(!timeMenu.hidden))});timeMenu.querySelectorAll('[data-time]').forEach(button=>button.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();timeValue.value=button.dataset.time;timeMenu.hidden=true;timeTrigger.setAttribute('aria-expanded','false');sync()}));document.getElementById('rooms-calendar-prev').addEventListener('click',()=>{const p=new Date(cursor.getFullYear(),cursor.getMonth()-1,1);if(p>=new Date(today.getFullYear(),today.getMonth(),1)){cursor=p;render()}});document.getElementById('rooms-calendar-next').addEventListener('click',()=>{cursor=new Date(cursor.getFullYear(),cursor.getMonth()+1,1);render()});document.addEventListener('click',event=>{if(!calendar.hidden&&!calendar.contains(event.target)&&!trigger.contains(event.target)){calendar.hidden=true;trigger.setAttribute('aria-expanded','false')}if(!timeMenu.hidden&&!timeField.contains(event.target)){timeMenu.hidden=true;timeTrigger.setAttribute('aria-expanded','false')}});sync();
});
</script>
@endsection
