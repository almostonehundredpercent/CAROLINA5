<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room operations · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>
        .room-operations-header { align-items: flex-start; gap: 18px; }
        .room-operations-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .room-create-button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 14px; border-radius: 8px; background: var(--orange); color: #fff; font-weight: 700; text-decoration: none; }
        .room-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0 0 20px; }
        .room-summary-card { min-height: 92px; padding: 16px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .room-summary-card small { display: block; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .room-summary-card strong { display: block; margin-top: 7px; font-size: 28px; line-height: 1; }
        .room-summary-card.available strong { color: var(--available); }.room-summary-card.reserved strong { color: var(--reserved); }.room-summary-card.occupied strong { color: var(--occupied); }.room-summary-card.unavailable strong { color: var(--maintenance); }
        .room-operations-note { display: flex; gap: 10px; align-items: flex-start; margin: 0 0 20px; padding: 13px 15px; border: 1px solid #f0dcba; border-radius: 10px; background: #fff8ec; color: #765627; font-size: 12px; line-height: 1.5; }
        .room-operations-note b { color: var(--ink); }
        .room-card-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .room-card { position: relative; overflow: hidden; border: 1px solid var(--line); border-radius: 14px; background: #fff; box-shadow: 0 7px 24px rgba(52, 40, 25, .04); }
        .room-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: var(--room-status); }
        .room-card.available { --room-status: var(--available); }.room-card.reserved { --room-status: var(--reserved); }.room-card.occupied { --room-status: var(--occupied); }.room-card.cleaning { --room-status: var(--cleaning); }.room-card.maintenance { --room-status: var(--maintenance); }
        .room-card-main { padding: 20px 20px 16px 25px; }
        .room-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .room-card-title { margin: 0; font: 700 20px 'Playfair Display', serif; line-height: 1.15; }
        .room-card-meta { margin: 7px 0 0; color: var(--muted); font-size: 12px; }
        .room-card-rate { display: block; margin-top: 7px; color: var(--orange-dark); font-size: 12px; font-weight: 700; }
        .room-status { flex: 0 0 auto; padding: 6px 9px; }
        .room-card-schedule { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 18px; }
        .schedule-item { min-width: 0; min-height: 75px; padding: 11px; border: 1px solid var(--line); border-radius: 9px; background: #fcfbfa; }
        .schedule-item small { display: block; margin-bottom: 5px; color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .schedule-item b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
        .schedule-item span { display: block; margin-top: 3px; color: var(--muted); font-size: 11px; line-height: 1.3; }
        .schedule-item.empty b { color: var(--muted); font-weight: 500; }
        .room-card-actions { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 16px; }
        .room-action-link, .room-archive-button { display: inline-flex; align-items: center; justify-content: center; min-height: 35px; padding: 0 10px; border: 1px solid var(--line); border-radius: 7px; background: #fff; color: var(--ink); font: 700 11px 'DM Sans', sans-serif; text-decoration: none; cursor: pointer; }
        .room-archive-button { border-color: #f0d2cd; color: #aa453a; }.room-archive-button:hover { background: #fff4f2; }
        .room-manage { border-top: 1px solid var(--line); background: #fcfbf9; }
        .room-manage summary { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 20px 14px 25px; color: var(--orange-dark); font-size: 12px; font-weight: 800; cursor: pointer; list-style: none; }
        .room-manage summary::-webkit-details-marker { display: none; }
        .room-manage summary::after { content: '+'; display: grid; width: 22px; height: 22px; place-items: center; border: 1px solid #ebd7ba; border-radius: 50%; background: #fff; font-size: 17px; font-weight: 400; }
        .room-manage[open] summary::after { content: '−'; }
        .room-operation-form { padding: 0 20px 20px 25px; }
        .operation-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 11px; }
        .operation-field { display: grid; gap: 5px; }.operation-field.full { grid-column: 1 / -1; }
        .operation-field label { color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .operation-field select, .operation-field input { width: 100%; min-height: 39px; border: 1px solid #ded8d0; border-radius: 7px; background: #fff; color: var(--ink); padding: 8px 9px; font: 600 12px 'DM Sans', sans-serif; }
        .operation-help { margin: 11px 0 0; color: var(--muted); font-size: 11px; line-height: 1.45; }
        .operation-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; }
        .override-note { display: flex; gap: 7px; max-width: 240px; color: #8c611d; font-size: 11px; line-height: 1.35; }.override-note input { margin: 2px 0 0; }
        .operation-save { min-height: 39px; border: 0; border-radius: 7px; padding: 0 13px; background: var(--orange); color: #fff; font: 700 12px 'DM Sans', sans-serif; cursor: pointer; white-space: nowrap; }.operation-save:hover { background: var(--orange-dark); }
        .room-empty { grid-column: 1 / -1; padding: 42px 20px; text-align: center; color: var(--muted); }
        @media (max-width: 1050px) { .room-summary { grid-template-columns: repeat(2, 1fr); }.room-card-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .room-operations-header { display: block; }.room-operations-actions { justify-content: flex-start; margin-top: 14px; }.room-summary { gap: 9px; }.room-summary-card { min-height: 77px; padding: 13px; }.room-summary-card strong { font-size: 23px; }.room-card-grid { gap: 12px; }.room-card-main { padding: 17px 15px 14px 20px; }.room-card-title { font-size: 18px; }.room-card-schedule { gap: 8px; margin-top: 14px; }.schedule-item { min-height: 71px; padding: 10px; }.room-manage summary { padding: 13px 15px 13px 20px; }.room-operation-form { padding: 0 15px 16px 20px; }.operation-form-grid { grid-template-columns: 1fr; }.operation-field.full { grid-column: auto; }.operation-footer { align-items: flex-start; flex-direction: column; }.operation-save { width: 100%; }.override-note { max-width: none; } }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('home') }}"><span>c</span><b>Carolina</b><small>TRANSIENT & AIRBNB</small></a>
        <nav class="admin-nav"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a class="active" href="{{ route('admin.rooms') }}">Rooms</a><a href="{{ route('admin.bookings') }}">Bookings</a><a href="{{ route('admin.walk-ins.create') }}">Walk-ins</a><a href="{{ route('admin.reports') }}">Reports</a></nav>
        <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit">Log out</button></form>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar room-operations-header"><div><p class="admin-kicker">PROPERTY MANAGEMENT</p><h1>Room operations</h1><p class="admin-subtitle">See occupancy, upcoming stays, and operational work in one place.</p></div>@if(auth()->user()->isAdmin())<div class="room-operations-actions"><a class="room-create-button" href="{{ route('admin.rooms.create') }}">Add room</a></div>@endif</header>
        @if(session('success'))<div class="admin-flash">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-flash" style="background:#fbe3e0;color:#a84336">{{ $errors->first() }}</div>@endif
        <section class="room-summary" aria-label="Room status summary"><article class="room-summary-card available"><small>Available now</small><strong>{{ $rooms->where('display_status', 'available')->count() }}</strong></article><article class="room-summary-card reserved"><small>Upcoming stays</small><strong>{{ $rooms->where('display_status', 'reserved')->count() }}</strong></article><article class="room-summary-card occupied"><small>Occupied now</small><strong>{{ $rooms->where('display_status', 'occupied')->count() }}</strong></article><article class="room-summary-card unavailable"><small>Cleaning / maintenance</small><strong>{{ $rooms->whereIn('display_status', ['cleaning', 'maintenance'])->count() }}</strong></article></section>
        <p class="room-operations-note"><span aria-hidden="true">ⓘ</span><span><b>Schedule safely.</b> Cleaning and maintenance blocks prevent guests from booking the room for that time. Existing guest stays remain visible while you plan work.</span></p>
        <section class="room-card-grid" aria-label="Room operations">
            @forelse($rooms as $room)
                @php
                    $stay = $room->display_booking;
                    $block = $room->display_block;
                    $stayLabel = $stay ? ($stay->check_in_at?->lte(now()) && $stay->check_out_at?->gt(now()) ? 'Current guest stay' : 'Next guest stay') : 'Guest stay';
                    $blockLabel = $block ? ($block->starts_at->lte(now()) ? 'Current operation' : 'Next operation') : 'Room operation';
                @endphp
                <article class="room-card {{ $room->display_status }}">
                    <div class="room-card-main">
                        <div class="room-card-top"><div><h2 class="room-card-title">{{ $room->name }}</h2><p class="room-card-meta">{{ $room->room_type }} · Up to {{ $room->guests }} guest{{ $room->guests === 1 ? '' : 's' }}</p><span class="room-card-rate">₱{{ number_format($room->price_per_night) }} {{ $room->rate_label }}</span></div><span class="room-status {{ $room->display_status }}">{{ ucfirst($room->display_status) }}</span></div>
                        <div class="room-card-schedule"><div class="schedule-item {{ $stay ? '' : 'empty' }}"><small>{{ $stayLabel }}</small>@if($stay)<b>{{ $stay->guest_name ?? $stay->user?->name ?? 'Guest booking' }}</b><span>{{ $stay->check_in_at?->format('M j, g A') }} – {{ $stay->check_out_at?->format('M j, g A') }}</span>@else<b>No active or upcoming stay</b>@endif</div><div class="schedule-item {{ $block ? '' : 'empty' }}"><small>{{ $blockLabel }}</small>@if($block)<b>{{ ucfirst($block->status) }}</b><span>{{ $block->starts_at->format('M j, g A') }} – {{ $block->ends_at->format('M j, g A') }}</span>@else<b>No cleaning or maintenance scheduled</b>@endif</div></div>
                        @if(auth()->user()->isAdmin())<div class="room-card-actions"><a class="room-action-link" href="{{ route('admin.rooms.edit', $room) }}">Edit room details</a><form method="POST" action="{{ route('admin.rooms.archive',$room) }}" onsubmit="return confirm('Archive this room? Its booking history will be kept.')">@csrf @method('DELETE')<button class="room-archive-button" type="submit">Archive room</button></form></div>@endif
                    </div>
                    <details class="room-manage"><summary>Schedule cleaning or maintenance</summary><form method="POST" action="{{ route('admin.rooms.status',$room) }}" class="room-operation-form">@csrf @method('PATCH')<div class="operation-form-grid"><div class="operation-field full"><label for="status-{{ $room->id }}">Operation type</label><select id="status-{{ $room->id }}" name="operational_status"><option value="cleaning">Cleaning</option><option value="maintenance">Maintenance</option><option value="available">Clear an active block now</option></select></div><div class="operation-field"><label for="start-{{ $room->id }}">Starts</label><input id="start-{{ $room->id }}" name="operational_starts_at" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}"></div><div class="operation-field"><label for="end-{{ $room->id }}">Ends</label><input id="end-{{ $room->id }}" name="operational_until" type="datetime-local" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}"></div><div class="operation-field full"><label for="note-{{ $room->id }}">Staff note <span aria-hidden="true">(optional)</span></label><input id="note-{{ $room->id }}" name="notes" maxlength="255" placeholder="For example: deep clean after checkout"></div></div><p class="operation-help">Choose “Clear an active block now” to immediately return a room from cleaning or maintenance to availability.</p><div class="operation-footer">@if(auth()->user()->isAdmin())<label class="override-note"><input type="checkbox" name="force_override" value="1"> Manager override if this overlaps a confirmed guest stay.</label>@else<span class="override-note">Confirmed stays cannot be overridden by your role.</span>@endif<button class="operation-save" type="submit">Save operation</button></div></form></details>
                </article>
            @empty
                <div class="room-empty">No active rooms have been added yet.</div>
            @endforelse
        </section>
    </main>
</div>
</body>
</html>
