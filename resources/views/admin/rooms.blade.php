<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room operations · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        :root { --room-blue:#c65d00; --room-blue-soft:#fff1df; --room-surface:#fff; --room-subtle:#f5f5f7; --room-stroke:#e5e5ea; --room-text:#1d1d1f; --room-secondary:#6e6e73; --room-tile:#fff8f1; --room-tile-border:#e8d8c7; --room-tile-text:#2a1a10; --room-tile-muted:#9a8d82; --room-tile-selected:#c65d00; --room-tile-shadow:rgba(76,45,22,.10); }
        .room-operations-header { display:flex; align-items:center; justify-content:space-between; gap:24px; padding:8px 0 28px; border:0; }.room-operations-header .admin-kicker { margin:0 0 7px; color:var(--room-secondary); font-size:11px; letter-spacing:.08em; }.room-operations-header h1 { margin:0; color:var(--room-text); font:700 clamp(32px,4vw,42px)/1.05 var(--admin-font); letter-spacing:-.045em; }.room-operations-header .admin-subtitle { margin:9px 0 0; color:var(--room-secondary); font-size:15px; }.room-operations-actions { display:flex; flex:0 0 auto; }.room-create-button { display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 16px; border-radius:980px; background:var(--room-blue); color:#fff; font:600 14px var(--admin-font); text-decoration:none; box-shadow:0 2px 7px rgba(0,113,227,.18); transition:transform .18s ease,background .18s ease; }.room-create-button:hover { background:#0077ed; transform:scale(1.02); }
        .room-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:0 0 28px; }.room-summary-card { position:relative; min-height:110px; padding:18px 76px 18px 20px; border:0; border-radius:18px; background:var(--room-surface); box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.035); }.room-summary-card small { display:block; color:var(--room-secondary); font:600 12px var(--admin-font); text-transform:none; }.room-summary-card strong { display:block; margin-top:10px; color:var(--room-text); font:700 34px/1 var(--admin-font); letter-spacing:-.04em; }.room-summary-card.available strong { color:#3f8f5b; }.room-summary-card.reserved strong { color:#d97706; }.room-summary-card.occupied strong { color:#c94b3c; }.room-summary-card.unavailable strong { color:#b76500; }
        .room-summary-icon { position:absolute; top:20px; right:20px; display:grid; width:30px; height:30px; place-items:center; padding:0; border:0; background:transparent!important; }.room-summary-icon svg { display:block; width:25px; height:25px; fill:none!important; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }.room-summary-icon path,.room-summary-icon circle,.room-summary-icon rect,.room-summary-icon line,.room-summary-icon polyline { fill:none!important; stroke:currentColor; }.room-summary-card.available .room-summary-icon { color:#3f8f5b!important; }.room-summary-card.reserved .room-summary-icon { color:#d97706!important; }.room-summary-card.not-available .room-summary-icon { color:#c72b20!important; }
        .room-operations-note { display:flex; align-items:center; gap:9px; margin:0 0 18px; padding:12px 15px; border:0; border-radius:13px; background:var(--room-blue-soft); color:#23537a; font-size:13px; line-height:1.45; }.room-operations-note b { color:#154a75; }.room-operations-note span:first-child { display:grid; width:19px; height:19px; flex:0 0 auto; place-items:center; border-radius:50%; background:#9fcef9; color:#165d99; font-size:12px; }
        .room-card-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }.room-card { overflow:hidden; border:0; border-radius:20px; background:var(--room-surface); box-shadow:0 1px 2px rgba(0,0,0,.04),0 10px 28px rgba(0,0,0,.035); }.room-card::before { display:none; }.room-card-main { padding:22px; }.room-card-top { display:flex; justify-content:space-between; gap:16px; }.room-card-title { margin:0; color:var(--room-text); font:700 20px/1.2 var(--admin-font); letter-spacing:-.025em; }.room-card-meta { margin:6px 0 0; color:var(--room-secondary); font-size:13px; }.room-card-rate { display:block; margin-top:7px; color:var(--room-text); font-size:13px; font-weight:650; }.room-status { display:block; width:auto!important; height:auto!important; min-height:0!important; flex:0 0 auto; padding:0!important; border:0!important; border-radius:0!important; background:transparent!important; font:700 15px/1.2 var(--admin-font)!important; letter-spacing:-.015em; text-transform:capitalize; box-shadow:none!important; }.room-status.available { color:#3f8f5b!important; }.room-status.reserved { color:#d97706!important; }.room-status.occupied { color:#c94b3c!important; }.room-status.cleaning,.room-status.maintenance { color:#a65a00!important; }
        .room-card-schedule { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:22px; }.schedule-item { min-width:0; min-height:90px; padding:14px; border:1px solid var(--room-stroke); border-radius:14px; background:#fff; }.schedule-item.empty { background:var(--room-subtle); border-color:transparent; }.schedule-item small { display:block; margin-bottom:7px; color:var(--room-secondary); font:600 11px var(--admin-font); text-transform:none; }.schedule-item b { display:block; overflow:hidden; color:var(--room-text); text-overflow:ellipsis; white-space:nowrap; font-size:13px; }.schedule-item span { display:block; margin-top:4px; color:var(--room-secondary); font-size:11px; line-height:1.35; }.schedule-item.empty b { color:var(--room-secondary); font-weight:500; white-space:normal; }
        .room-card-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:18px; }.room-action-link,.room-archive-button { display:inline-flex; min-height:34px; align-items:center; justify-content:center; padding:0 12px; border:1px solid var(--room-stroke); border-radius:980px; background:#fff; color:var(--room-text); font:600 12px var(--admin-font); text-decoration:none; cursor:pointer; }.room-action-link:hover { border-color:#a8a8ad; }.room-archive-button { color:#b42318; }.room-archive-button:hover { border-color:#e8b4ae; background:#fff7f6; }
        .room-manage { border-top:1px solid var(--room-stroke); background:#fbfbfc; }.room-manage summary { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; color:#0066cc; font:600 13px var(--admin-font); cursor:pointer; list-style:none; }.room-manage summary::-webkit-details-marker { display:none; }.room-manage summary::after { content:'›'; color:#6e6e73; font-size:23px; font-weight:300; transition:transform .2s ease; }.room-manage[open] summary::after { transform:rotate(90deg); }.room-manage[open] { background:#f5f9ff; }
        .room-operation-form { padding:0 22px 22px; }.operation-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }.operation-field { display:grid; gap:6px; }.operation-field.full { grid-column:1 / -1; }.operation-field label { color:var(--room-secondary); font-size:11px; font-weight:600; text-transform:none; }.operation-field select,.operation-field input { width:100%; min-height:40px; padding:8px 10px; border:1px solid #d1d1d6; border-radius:10px; background:#fff; color:var(--room-text); font:500 13px var(--admin-font); }.operation-time-panel { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:15px 0; }.operation-moment { padding:14px; border:1px solid var(--room-stroke); border-radius:14px; background:#fff; }.operation-moment-title { margin:0 0 10px; color:var(--room-text); font:650 13px var(--admin-font); }.operation-moment-fields { display:grid; grid-template-columns:minmax(0,1.18fr) minmax(100px,.82fr); gap:8px; }.operation-moment input,.operation-moment select { width:100%; min-height:40px; padding:8px 9px; border:1px solid #d1d1d6; border-radius:10px; background:#fff; color:var(--room-text); font:500 13px var(--admin-font); }.schedule-timer { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:15px 0; padding:14px 15px; border-radius:14px; background:#f5f9ff; }.schedule-timer-label { color:var(--room-secondary); font-size:12px; }.schedule-timer-value { margin-top:3px; color:var(--room-text); font:700 18px/1.2 var(--admin-font); letter-spacing:-.025em; }.duration-presets { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }.duration-button { min-height:32px; padding:0 11px; border:1px solid #d1d1d6; border-radius:980px; background:#fff; color:var(--room-text); font:600 11px var(--admin-font); cursor:pointer; }.duration-button:hover,.duration-button.is-selected { border-color:var(--room-blue); background:var(--room-blue); color:#fff; }.operation-help { margin:11px 0 0; color:var(--room-secondary); font-size:12px; line-height:1.45; }.operation-footer { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:16px; }.override-note { display:flex; gap:7px; max-width:260px; color:#86510b; font-size:11px; line-height:1.35; }.override-note input { margin:2px 0 0; }.operation-save { min-height:38px; padding:0 14px; border:0; border-radius:980px; background:var(--room-blue); color:#fff; font:600 12px var(--admin-font); cursor:pointer; }.operation-save:hover { background:#0077ed; }.room-empty { grid-column:1 / -1; padding:52px 20px; border-radius:20px; background:#fff; text-align:center; color:var(--room-secondary); }
        @media (max-width:1050px) { .room-summary { grid-template-columns:repeat(2,1fr); }.room-card-grid { grid-template-columns:1fr; } } @media (max-width:700px) { .room-operations-header { display:block; padding-bottom:22px; }.room-operations-actions { margin-top:18px; }.room-create-button { width:100%; }.room-summary { gap:10px; margin-bottom:20px; }.room-summary-card { min-height:92px; padding:15px; border-radius:16px; }.room-summary-card strong { font-size:28px; }.room-operations-note { align-items:flex-start; }.room-card { border-radius:17px; }.room-card-main { padding:18px; }.room-card-title { font-size:18px; }.room-card-schedule { grid-template-columns:1fr; margin-top:18px; }.schedule-item { min-height:76px; }.room-manage summary { padding:14px 18px; }.room-operation-form { padding:0 18px 18px; }.operation-form-grid,.operation-time-panel { grid-template-columns:1fr; }.operation-field.full { grid-column:auto; }.operation-moment-fields { grid-template-columns:1fr; }.schedule-timer { align-items:flex-start; flex-direction:column; }.duration-presets { justify-content:flex-start; }.operation-footer { align-items:stretch; flex-direction:column; }.operation-save { width:100%; } }
        /* Final phone pass: room cards are read first, then expanded only when work is needed. */
        @media (max-width:700px) {
            .room-operations-header { padding:4px 0 20px; }
            .room-operations-header h1 { font-size:30px; }
            .room-operations-header .admin-subtitle { font-size:14px; line-height:1.45; }
            .room-summary-card { min-height:96px; padding:16px; }
            .room-summary-card small { font-size:12px; }
            .room-summary-card strong { font-size:29px; }
            .room-operations-note { padding:14px; font-size:13px; }
            .room-card-main { padding:18px; }
            .room-card-top { align-items:flex-start; flex-direction:column; gap:7px; }
            .room-status { font-size:15px!important; }
            .room-card-title { font-size:20px; }
            .room-card-meta,.room-card-rate { font-size:14px; }
            .schedule-item { min-height:78px; padding:14px; }
            .schedule-item small { font-size:12px; }
            .schedule-item b { font-size:14px; }
            .schedule-item span { font-size:12px; }
            .room-card-actions { gap:9px; }
            .room-action-link,.room-archive-button { min-height:42px; padding:0 14px; font-size:13px; }
            .room-manage summary { min-height:54px; padding:15px 18px; font-size:14px; }
            .room-operation-form { padding:0 18px 20px; }
            .operation-field label,.operation-moment-title { font-size:13px; }
            .operation-moment { padding:14px; }
            .operation-moment input,.operation-moment select,.operation-field select,.operation-field input { min-height:48px; font-size:15px; }
            .schedule-timer { gap:12px; padding:15px; }
            .schedule-timer-value { font-size:20px; }
            .duration-presets { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); width:100%; }
            .duration-button { min-height:40px; padding:0 8px; font-size:12px; }
            .operation-help { font-size:13px; }
            .override-note { max-width:none; font-size:12px; }
            .operation-save { min-height:46px; font-size:14px; }
        }
        .room-summary { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .room-summary-card.not-available strong { color:#c72b20; }
        .room-board-shell { display:grid; gap:20px; }
        .room-board-legend { display:flex; flex-wrap:wrap; gap:10px 18px; color:var(--room-secondary); font-size:12px; }
        .room-board-legend span { display:inline-flex; align-items:center; gap:7px; }
        .room-board-legend i { width:12px; height:12px; border-radius:4px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.08); }
        .room-board-legend .available { background:#3f8f5b; }
        .room-board-legend .arriving { background:#d97706; }
        .room-board-legend .not-available { background:#c94b3c; }
        .room-selector-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; }
        .room-slot { --slot-accent:#9a8d82; position:relative; display:grid; min-height:104px; padding:17px 14px 14px; overflow:hidden; border:1px solid var(--room-tile-border); border-radius:16px; background:linear-gradient(145deg,var(--room-tile),#fff); color:var(--room-tile-text); text-align:left; cursor:pointer; box-shadow:0 8px 20px var(--room-tile-shadow); transition:transform .17s ease,box-shadow .17s ease,border-color .17s ease,background .17s ease; }
        .room-slot::before { content:''; position:absolute; inset:0 0 auto; height:5px; background:var(--slot-accent); }
        .room-slot:hover { transform:translateY(-2px); border-color:#d9b896; box-shadow:0 12px 25px rgba(76,45,22,.15); }
        .room-slot:focus-visible { outline:4px solid rgba(198,93,0,.24); outline-offset:3px; }
        .room-slot[aria-pressed="true"] { border-color:var(--room-tile-selected); background:linear-gradient(145deg,#fff0df,#fffaf4); outline:3px solid var(--room-tile-selected); outline-offset:2px; }
        .room-slot.available { --slot-accent:#3f8f5b; }
        .room-slot.arriving { --slot-accent:#d97706; }
        .room-slot.not-available { --slot-accent:#c94b3c; }
        .room-slot.unassigned { border:1px dashed var(--room-tile-border); background:#f7f2ed; color:var(--room-tile-muted); box-shadow:none; cursor:default; }
        .room-slot.unassigned::before { background:#b9aa9d; }
        .room-slot.unassigned:hover { transform:none; box-shadow:none; }
        .room-slot-number { font:800 31px/1 var(--admin-font); letter-spacing:-.045em; }
        .room-slot-name { align-self:end; display:-webkit-box; margin-top:12px; overflow:hidden; font:700 12px/1.25 var(--admin-font); -webkit-box-orient:vertical; -webkit-line-clamp:2; }
        .room-slot-state { position:absolute; top:13px; right:13px; padding:4px 7px; border-radius:999px; font:700 9px/1 var(--admin-font); letter-spacing:.05em; text-transform:uppercase; }
        .room-slot.available .room-slot-state { background:#e4f2e8; color:#2f7047; }
        .room-slot.arriving .room-slot-state { background:#ffead0; color:#9a4e00; }
        .room-slot.not-available .room-slot-state { background:#fbe2de; color:#9f332a; }
        .room-slot.unassigned .room-slot-state { background:#e9e1da; color:#7e7065; }
        .room-detail-stage { min-height:190px; scroll-margin-top:20px; }
        .room-detail-placeholder { display:grid; min-height:190px; place-content:center; gap:6px; padding:28px; border:1px dashed #c8c8cd; border-radius:20px; background:#fafafa; color:var(--room-secondary); text-align:center; }
        .room-detail-placeholder[hidden] { display:none; }
        .room-detail-placeholder strong { color:var(--room-text); font-size:18px; }
        .room-detail-card { max-width:900px; margin:0 auto; border:1px solid var(--room-stroke); }
        .room-detail-card[hidden] { display:none; }
        .room-detail-heading { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; color:#fff; }
        .room-detail-card .room-detail-heading { background:#c65d00; }
        .room-detail-number { font:800 14px var(--admin-font); }
        .room-detail-close { width:32px; height:32px; border:0; border-radius:50%; background:rgba(255,255,255,.2); color:#fff; font:400 25px/1 var(--admin-font); cursor:pointer; }
        .room-detail-close:hover { background:rgba(255,255,255,.32); }
        html.dark-mode { --room-tile:#1c1713; --room-tile-border:#4a3325; --room-tile-text:#fff7ed; --room-tile-muted:#9f9186; --room-tile-selected:#f28c28; --room-tile-shadow:rgba(0,0,0,.32); }
        html.dark-mode .room-slot { background:linear-gradient(145deg,#1c1713,#241b15); }
        html.dark-mode .room-slot:hover { border-color:#765036; box-shadow:0 12px 27px rgba(0,0,0,.4); }
        html.dark-mode .room-slot[aria-pressed="true"] { border-color:#f28c28; background:linear-gradient(145deg,#352115,#241913); outline-color:#f28c28; }
        html.dark-mode .room-slot.available .room-slot-state { background:#183c29; color:#8ad5a4; }
        html.dark-mode .room-slot.arriving .room-slot-state { background:#4b2b10; color:#ffb762; }
        html.dark-mode .room-slot.not-available .room-slot-state { background:#47201d; color:#ff9b90; }
        html.dark-mode .room-slot.unassigned { background:#181411; }
        html.dark-mode .room-slot.unassigned .room-slot-state { background:#302721; color:#ad9e92; }
        html.dark-mode .room-detail-card .room-detail-heading { background:#a94f00; }
        @media (max-width:1050px) { .room-selector-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
        @media (max-width:700px) {
            .room-summary { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .room-summary-card { min-height:82px; padding:13px 58px 13px 13px; }
            .room-summary-card strong { font-size:25px; }
            .room-summary-icon { top:14px; right:14px; width:26px; height:26px; }
            .room-summary-icon svg { width:22px; height:22px; }
            .room-selector-grid { grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; }
            .room-slot { min-height:90px; padding:12px; border-radius:13px; }
            .room-slot-number { font-size:27px; }
            .room-slot-name { font-size:10px; }
            .room-slot-state { top:10px; right:9px; padding:3px 5px; font-size:7px; }
            .room-detail-card { border-radius:17px; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar room-operations-header"><div><p class="admin-kicker">PROPERTY MANAGEMENT</p><h1>Rooms</h1><p class="admin-subtitle">A live view of every room, stay, and task.</p></div>@if(auth()->user()->isAdmin())<div class="room-operations-actions"><a class="room-create-button" href="{{ route('admin.rooms.create') }}">Add room</a></div>@endif</header>
        @if(session('success'))<div class="admin-flash">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-flash" style="background:#fbe3e0;color:#a84336">{{ $errors->first() }}</div>@endif
        @php
            $availableRoomCount = $rooms->where('display_status', 'available')->count();
            $arrivingRoomCount = $rooms->where('display_status', 'reserved')->count();
            $notAvailableRoomCount = $rooms->count() - $availableRoomCount - $arrivingRoomCount;
            $roomSlots = $rooms->sortBy('id')->values()->take(18);
        @endphp
        <section class="room-summary" aria-label="Room status summary"><article class="room-summary-card available"><span class="room-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/></svg></span><small>Available</small><strong>{{ $availableRoomCount }}</strong></article><article class="room-summary-card reserved"><span class="room-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M15.5 14v2.5l1.7 1"/></svg></span><small>Arriving soon</small><strong>{{ $arrivingRoomCount }}</strong></article><article class="room-summary-card not-available"><span class="room-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg></span><small>Not available</small><strong>{{ $notAvailableRoomCount }}</strong></article></section>
        <p class="room-operations-note"><span aria-hidden="true">i</span><span><b>Availability is protected.</b> Cleaning and maintenance blocks stop online bookings while the work is scheduled.</span></p>
        <section class="room-board-shell" aria-label="Room operations">
            <div class="room-board-legend" aria-label="Room color guide"><span><i class="available"></i>Available</span><span><i class="arriving"></i>Arriving soon</span><span><i class="not-available"></i>Not available</span></div>
            <div class="room-selector-grid">
                @foreach(range(1, 18) as $slot)
                    @php
                        $room = $roomSlots->get($slot - 1);
                        $boardStatus = 'unassigned';
                        $boardLabel = 'Empty';
                        if ($room && $room->display_status === 'available') {
                            $boardStatus = 'available';
                            $boardLabel = 'Available';
                        } elseif ($room && $room->display_status === 'reserved') {
                            $boardStatus = 'arriving';
                            $boardLabel = 'Arriving';
                        } elseif ($room) {
                            $boardStatus = 'not-available';
                            $boardLabel = 'Unavailable';
                        }
                    @endphp
                    <button class="room-slot {{ $boardStatus }}" type="button" @if($room)data-room-target="{{ $slot }}" aria-controls="room-detail-{{ $slot }}" aria-pressed="false"@else disabled aria-label="Room {{ $slot }} is not assigned"@endif>
                        <span class="room-slot-number">{{ $slot }}</span>
                        <span class="room-slot-state">{{ $boardLabel }}</span>
                        <span class="room-slot-name">{{ $room ? $room->name : 'No room assigned' }}</span>
                    </button>
                @endforeach
            </div>
            <div class="room-detail-stage" id="room-detail-stage">
                <div class="room-detail-placeholder"><strong>Select a room</strong><span>Click a numbered room above to view its stay, status, and management controls.</span></div>
                @foreach(range(1, 18) as $slot)
                    @php
                        $room = $roomSlots->get($slot - 1);
                    @endphp
                    @if($room)
                        @include('admin.partials.room-detail-card', ['room' => $room, 'slot' => $slot])
                    @endif
                @endforeach
            </div>
        </section>
    </main>
</div>
<script>
    (() => {
        const roomButtons = [...document.querySelectorAll('[data-room-target]')];
        const roomDetails = [...document.querySelectorAll('[data-room-detail]')];
        const detailStage = document.getElementById('room-detail-stage');
        const detailPlaceholder = detailStage?.querySelector('.room-detail-placeholder');
        const closeDetails = () => {
            roomButtons.forEach((button) => button.setAttribute('aria-pressed', 'false'));
            roomDetails.forEach((detail) => detail.hidden = true);
            if (detailPlaceholder) detailPlaceholder.hidden = false;
        };
        roomButtons.forEach((button) => button.addEventListener('click', () => {
            const target = button.dataset.roomTarget;
            roomButtons.forEach((candidate) => candidate.setAttribute('aria-pressed', String(candidate === button)));
            roomDetails.forEach((detail) => detail.hidden = detail.dataset.roomDetail !== target);
            if (detailPlaceholder) detailPlaceholder.hidden = true;
            if (window.matchMedia('(max-width: 700px)').matches) detailStage?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));
        document.querySelectorAll('.room-detail-close').forEach((button) => button.addEventListener('click', closeDetails));

        const toDateValue = (date) => {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        };
        const toTimeValue = (date) => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes() < 30 ? 0 : 30).padStart(2, '0')}`;
        const durationLabel = (minutes) => {
            if (minutes < 60) return `${minutes} min`;
            const hours = Math.floor(minutes / 60), remaining = minutes % 60;
            return remaining ? `${hours} hr ${remaining} min` : `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
        };
        document.querySelectorAll('.room-operation-form').forEach((form) => {
            const startDateInput = form.querySelector('.operation-start-date');
            const startTimeInput = form.querySelector('.operation-start-time');
            const endDateInput = form.querySelector('.operation-end-date');
            const endTimeInput = form.querySelector('.operation-end-time');
            const output = form.querySelector('.schedule-timer-value');
            const buttons = [...form.querySelectorAll('.duration-button')];
            const dateFromFields = (dateInput, timeInput) => new Date(`${dateInput.value}T${timeInput.value}`);
            const refresh = () => {
                const startDate = dateFromFields(startDateInput, startTimeInput), endDate = dateFromFields(endDateInput, endTimeInput);
                const minutes = Math.round((endDate - startDate) / 60000);
                if (!startDateInput.value || !endDateInput.value || !Number.isFinite(minutes) || minutes <= 0) {
                    output.textContent = 'Choose a duration';
                    buttons.forEach((button) => button.classList.remove('is-selected'));
                    return;
                }
                output.textContent = durationLabel(minutes);
                buttons.forEach((button) => button.classList.toggle('is-selected', Number(button.dataset.minutes) === minutes));
            };
            buttons.forEach((button) => button.addEventListener('click', () => {
                const startDate = dateFromFields(startDateInput, startTimeInput);
                if (!startDateInput.value || Number.isNaN(startDate.getTime())) return;
                startDate.setMinutes(startDate.getMinutes() + Number(button.dataset.minutes));
                endDateInput.value = toDateValue(startDate);
                endTimeInput.value = toTimeValue(startDate);
                refresh();
            }));
            [startDateInput, startTimeInput, endDateInput, endTimeInput].forEach((input) => input.addEventListener('change', refresh));
            refresh();
        });
    })();
</script>
</body>
</html>
