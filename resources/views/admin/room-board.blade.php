<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Housekeeping · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar housekeeping-header">
            <div>
                <p class="admin-kicker">HOUSEKEEPING</p>
                <h1>Room readiness</h1>
                <p class="admin-subtitle">Choose a room, start a task, and mark it ready when finished.</p>
            </div>
            <div class="housekeeping-counts" aria-label="Room status summary">
                <span><b>{{ $rooms->where('display_status', 'available')->count() }}</b> ready</span>
                <span><b>{{ $rooms->whereIn('display_status', ['cleaning', 'maintenance'])->count() }}</b> in progress</span>
            </div>
        </header>

        @include('admin.partials.flash')

        @php($workHours = collect(range(6, 22))->mapWithKeys(fn ($hour) => [sprintf('%02d:00', $hour) => \Carbon\Carbon::createFromTime($hour)->format('g A')]))
        <section class="room-board" aria-label="Room housekeeping board">
            @forelse($rooms as $room)
                @php($isInProgress = in_array($room->display_status, ['cleaning', 'maintenance'], true))
                <article class="housekeeping-card {{ $room->display_status }}">
                    <div class="housekeeping-card-top">
                        <span class="room-status {{ $room->display_status }}">{{ ucfirst($room->display_status) }}</span>
                        <span class="housekeeping-capacity">Up to {{ $room->guests }} guests</span>
                    </div>
                    <h2>{{ $room->name }}</h2>
                    <p class="housekeeping-room-type">{{ $room->room_type }}</p>

                    <div class="housekeeping-state">
                        @if($room->display_block)
                            <b>{{ ucfirst($room->display_block->status) }} scheduled</b>
                            <span>{{ $room->display_block->starts_at->format('M j, g:i A') }} – {{ $room->display_block->ends_at->format('g:i A') }}</span>
                        @elseif($room->display_booking)
                            <b>Guest stay in progress</b>
                            <span>Unavailable until {{ $room->display_booking->check_out_at?->format('M j, g:i A') ?? 'check-out' }}</span>
                        @else
                            <b>Ready for the next guest</b>
                            <span>No cleaning or maintenance task is scheduled.</span>
                        @endif
                    </div>

                    @if(\App\Support\AdminPermissions::allows(auth()->user(), 'room_operations'))
                        <div class="housekeeping-actions">
                            @if($isInProgress)
                                <form method="POST" action="{{ route('admin.rooms.status', $room) }}" onsubmit="return confirm('Mark this room ready for the next guest?')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="operational_status" value="available">
                                    <button class="housekeeping-button ready" type="submit">Mark room ready</button>
                                </form>
                            @elseif(! $room->display_booking)
                                <form method="POST" action="{{ route('admin.rooms.status', $room) }}" onsubmit="return confirm('Start a one-hour cleaning task now?')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="operational_status" value="cleaning">
                                    <input type="hidden" name="operational_starts_at" value="{{ now()->format('Y-m-d H:i') }}">
                                    <input type="hidden" name="operational_until" value="{{ now()->addHour()->format('Y-m-d H:i') }}">
                                    <button class="housekeeping-button" type="submit">Start cleaning</button>
                                </form>
                            @endif
                        </div>

                        <details class="housekeeping-plan">
                            <summary>{{ $isInProgress ? 'Change or extend task' : 'Plan a one-day task' }} <span aria-hidden="true">⌄</span></summary>
                            <form method="POST" action="{{ route('admin.rooms.status', $room) }}" class="housekeeping-form">
                                @csrf @method('PATCH')
                                <div class="housekeeping-choice" role="group" aria-label="Task type">
                                    <label><input type="radio" name="operational_status" value="cleaning" checked> Cleaning</label>
                                    <label><input type="radio" name="operational_status" value="maintenance"> Maintenance</label>
                                </div>
                                <div class="housekeeping-form-grid one-day">
                                    <label class="housekeeping-date">Work date
                                        <span class="housekeeping-date-picker" data-date-picker>
                                            <input name="operational_date" type="hidden" value="{{ now()->toDateString() }}">
                                            <button class="housekeeping-date-trigger" type="button" aria-expanded="false"><span>{{ now()->format('D, M j') }}</span><b aria-hidden="true">⌄</b></button>
                                            <div class="housekeeping-calendar" hidden></div>
                                        </span>
                                    </label>
                                    <label>Start hour<select name="operational_start_time" required>@foreach($workHours as $value => $label)<option value="{{ $value }}" @selected($value === '08:00')>{{ $label }}</option>@endforeach</select></label>
                                    <label>Finish hour<select name="operational_end_time" required>@foreach($workHours as $value => $label)<option value="{{ $value }}" @selected($value === '17:00')>{{ $label }}</option>@endforeach</select></label>
                                </div>
                                <label class="housekeeping-note">Note <input name="notes" maxlength="255" placeholder="Optional note for the next shift"></label>
                                <button class="housekeeping-button secondary" type="submit">Save one-day task</button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <p class="empty-copy">No active rooms are configured.</p>
            @endforelse
        </section>
    </main>
</div>
<script>
document.querySelectorAll('[data-date-picker]').forEach((picker) => {
    const input = picker.querySelector('input');
    const trigger = picker.querySelector('.housekeeping-date-trigger');
    const calendar = picker.querySelector('.housekeeping-calendar');
    let cursor = new Date(input.value + 'T12:00:00');
    const formatValue = (date) => date.toISOString().slice(0, 10);
    const label = (date) => new Intl.DateTimeFormat('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }).format(date);
    const render = () => {
        const year = cursor.getFullYear(), month = cursor.getMonth();
        const first = new Date(year, month, 1), last = new Date(year, month + 1, 0);
        const selected = input.value;
        const leading = first.getDay();
        let days = '';
        for (let blank = 0; blank < leading; blank++) days += '<span></span>';
        for (let day = 1; day <= last.getDate(); day++) {
            const date = new Date(year, month, day, 12);
            const value = formatValue(date);
            days += `<button type="button" class="${value === selected ? 'selected' : ''}" data-date="${value}" aria-label="${label(date)}">${day}</button>`;
        }
        calendar.innerHTML = `<div class="calendar-head"><button type="button" data-month="-1" aria-label="Previous month">‹</button><b>${new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric' }).format(cursor)}</b><button type="button" data-month="1" aria-label="Next month">›</button></div><div class="calendar-week">${['S','M','T','W','T','F','S'].map(day => `<span>${day}</span>`).join('')}</div><div class="calendar-days">${days}</div><button type="button" class="calendar-today">Today</button>`;
    };
    const close = () => { calendar.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
    trigger.addEventListener('click', () => { calendar.hidden = !calendar.hidden; trigger.setAttribute('aria-expanded', String(!calendar.hidden)); if (!calendar.hidden) render(); });
    calendar.addEventListener('click', (event) => {
        const monthButton = event.target.closest('[data-month]');
        if (monthButton) { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + Number(monthButton.dataset.month), 1); render(); return; }
        if (event.target.closest('.calendar-today')) { cursor = new Date(); input.value = formatValue(cursor); trigger.firstElementChild.textContent = label(cursor); close(); return; }
        const dayButton = event.target.closest('[data-date]');
        if (dayButton) { const date = new Date(dayButton.dataset.date + 'T12:00:00'); input.value = dayButton.dataset.date; trigger.firstElementChild.textContent = label(date); close(); }
    });
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
});
</script>
</body>
</html>
