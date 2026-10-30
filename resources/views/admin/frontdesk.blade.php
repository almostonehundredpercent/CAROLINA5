<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .today-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;align-items:start}
        .today-grid>.panel{height:max-content}
        .today-list{display:grid;gap:9px}
        .today-item{border:1px solid var(--line);border-radius:8px;padding:12px;display:grid;gap:8px}
        .today-item small{color:var(--muted)}
        .today-schedule{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:0}
        .today-schedule>div{min-width:0;padding:9px 10px;border:1px solid var(--line);border-radius:7px;background:rgba(255,255,255,.035)}
        .today-schedule dt{margin-bottom:4px;color:#efaa4c;font-size:.68rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
        .today-schedule dd{margin:0;color:var(--ink);font-size:.88rem;font-weight:700;line-height:1.35}
        .today-schedule dd+dd{margin-top:2px}
        .today-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:2px}
        .today-actions form{margin:0}
        .today-panel-note{margin:0 0 10px;color:var(--muted);font-size:.78rem;line-height:1.45}
        .today-arrival-status{display:inline-flex;width:max-content;padding:4px 7px;border-radius:99px;background:rgba(63,143,91,.14);color:#7bc293;font-size:.66rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .today-arrival-status.is-overdue{background:rgba(201,75,60,.14);color:#f08d80}
        .today-maintenance-item{border-left:3px solid #d78419}
        .today-maintenance-label{display:inline-flex;width:max-content;padding:4px 7px;border-radius:99px;background:rgba(215,132,25,.14);color:#efaa4c;font-size:.66rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .today-maintenance-note{margin:0;color:var(--muted);font-size:.82rem;line-height:1.4}
        @media(max-width:1250px){.today-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.today-grid{grid-template-columns:1fr;gap:12px}.today-schedule{gap:6px}.today-schedule>div{padding:8px}}
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-kicker">FRONT DESK</p>
                <h1>Today</h1>
                <p class="admin-subtitle">Arrivals, stays, departures, cleaning, and maintenance for today.</p>
            </div>
            <a class="frontdesk-walkin" href="{{ route('admin.walk-ins.create') }}">+ New walk-in</a>
        </header>
        @include('admin.partials.flash')

        <section class="today-grid">
            <section class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">ARRIVALS</p><h2>{{ $arrivals->count() }} awaiting check-in</h2></div></div>
                <p class="today-panel-note">A guest appears under In house after staff records their actual arrival.</p>
                <div class="today-list">
                    @forelse($arrivals as $booking)
                        <article class="today-item">
                            <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b>
                            @if($booking->check_in_at && $booking->check_in_at->lte($propertyNow))
                                <span class="today-arrival-status is-overdue">Check-in time passed · confirm arrival</span>
                            @else
                                <span class="today-arrival-status">Expected today</span>
                            @endif
                            <small>{{ $booking->room?->name }} · Payment: {{ ucfirst($booking->payment_status) }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions">
                                <a class="status-select" href="{{ route('admin.bookings.show', $booking) }}">View booking</a>
                                @if($booking->check_in_at?->lte($propertyNow))
                                    <form method="POST" action="{{ route('admin.bookings.check-in', $booking) }}" data-admin-confirm data-confirm-title="Check in this guest?" data-confirm-message="Check in {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }} now? This marks the reservation as checked in." data-confirm-action="Check in guest">
                                        @csrf<input type="hidden" name="action" value="check_in"><button class="status-select confirmed">Check in</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="empty-copy">No arrivals need check-in today.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">IN HOUSE</p><h2>{{ $activeStays->count() }} active stay{{ $activeStays->count() === 1 ? '' : 's' }}</h2></div></div>
                <div class="today-list">
                    @forelse($activeStays as $booking)
                        <article class="today-item">
                            <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->room?->name }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions"><a class="status-select" href="{{ route('admin.bookings.show', $booking) }}">View booking</a></div>
                        </article>
                    @empty
                        <p class="empty-copy">No guests are currently checked in.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">DEPARTURES</p><h2>{{ $departures->count() }} due today</h2></div></div>
                <div class="today-list">
                    @forelse($departures as $booking)
                        <article class="today-item">
                            <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->room?->name }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions">
                                <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" data-admin-confirm data-confirm-kicker="GUEST DEPARTURE" data-confirm-title="Check out this guest?" data-confirm-message="Check out {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }} now? This records the departure and marks the room for cleaning." data-confirm-action="Check out guest">
                                    @csrf<input type="hidden" name="action" value="check_out"><button class="status-select">Check out</button>
                                </form>
                                <a class="status-select" href="{{ route('admin.bookings.show', $booking) }}">View booking</a>
                            </div>
                        </article>
                    @empty
                        <p class="empty-copy">No departures are waiting today.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">ROOM OPERATIONS</p><h2>{{ $roomOperationBlocks->count() }} item{{ $roomOperationBlocks->count() === 1 ? '' : 's' }} today</h2></div></div>
                <div class="today-list">
                    @forelse($roomOperationBlocks as $block)
                        <article class="today-item today-maintenance-item">
                            <span class="today-maintenance-label">{{ ucfirst($block->status) }} · {{ $block->starts_at->lte(now()) && $block->ends_at->gt(now()) ? 'In progress' : 'Scheduled today' }}</span>
                            <b>{{ $block->room?->name ?? 'Room' }}</b>
                            <small>{{ $block->starts_at->format('M j · g:i A') }} – {{ $block->ends_at->format('M j · g:i A') }}</small>
                            @if($block->notes)<p class="today-maintenance-note">{{ $block->notes }}</p>@endif
                        </article>
                    @empty
                        <p class="empty-copy">No cleaning or maintenance items overlap today.</p>
                    @endforelse
                </div>
            </section>
        </section>
    </main>
</div>
@include('admin.partials.confirmation-dialog')
</body>
</html>
