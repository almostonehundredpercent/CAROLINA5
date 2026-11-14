<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/frontdesk.css') }}?v={{ filemtime(public_path('css/frontdesk.css')) }}">
    <script src="{{ asset('js/cleaning-countdown.js') }}?v={{ filemtime(public_path('js/cleaning-countdown.js')) }}" defer></script>
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
        .today-maintenance-item{background:rgba(215,132,25,.07)}
        .today-maintenance-label{display:inline-flex;width:max-content;padding:4px 7px;border-radius:99px;background:rgba(215,132,25,.14);color:#efaa4c;font-size:.66rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .today-maintenance-note{margin:0;color:var(--muted);font-size:.82rem;line-height:1.4}
        @media(max-width:1250px){.today-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.today-grid{grid-template-columns:1fr;gap:12px}.today-schedule{gap:6px}.today-schedule>div{padding:8px}}
    </style>
</head>
<body class="frontdesk-page">
<header class="frontdesk-mobile-header">
    <a class="frontdesk-mobile-brand" href="{{ route('admin.frontdesk') }}"><img src="{{ asset('images/carolina-logo.jpg') }}" alt=""><span><b>Carolina</b><small>STAFF WORKSPACE</small></span></a>
    <details class="frontdesk-mobile-menu">
        <summary aria-label="Open staff menu"><span></span><span></span><span></span></summary>
        <div class="frontdesk-mobile-menu-content">
            <nav aria-label="All staff pages">
                @foreach(\App\Support\AdminPermissions::navigation(auth()->user()) as $item)
                    @if(\App\Support\AdminPermissions::allows(auth()->user(), $item['ability']))
                        <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>
            <button class="admin-theme-toggle frontdesk-mobile-theme" type="button" data-admin-theme-toggle role="switch" aria-label="Dark mode" aria-checked="false"><span data-admin-theme-label>Dark mode</span><span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-thumb"><svg viewBox="0 0 24 24"></svg></span></span></button>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
        </div>
    </details>
</header>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-kicker">FRONT DESK</p>
                <h1>Today</h1>
                <p class="admin-subtitle">Front desk overview · {{ $propertyNow->format('l, F j, Y') }}</p>
            </div>
            <a class="frontdesk-walkin" href="{{ route('admin.walk-ins.create') }}">+ New walk-in</a>
        </header>
        @include('admin.partials.flash')

        <section class="frontdesk-summary" aria-label="Today's overview">
            <div class="frontdesk-metrics">
                <article class="frontdesk-metric"><span class="frontdesk-metric-icon" aria-hidden="true">↗</span><p>Arrivals</p><strong>{{ $arrivals->count() }}</strong><small>Awaiting check-in</small></article>
                <article class="frontdesk-metric"><span class="frontdesk-metric-icon" aria-hidden="true">⌂</span><p>In house</p><strong>{{ $activeStays->count() }}</strong><small>Active stays</small></article>
                <article class="frontdesk-metric"><span class="frontdesk-metric-icon" aria-hidden="true">↗</span><p>Departures</p><strong>{{ $departures->count() }}</strong><small>Due today</small></article>
                <article class="frontdesk-metric"><span class="frontdesk-metric-icon" aria-hidden="true">✓</span><p>Rooms ready</p><strong>{{ $roomStatusCounts['available'] }}</strong><small>Available right now</small></article>
            </div>

            <div class="frontdesk-feature-grid">
                <section class="frontdesk-feature frontdesk-attention">
                    <div class="frontdesk-feature-title"><span class="frontdesk-feature-icon" aria-hidden="true">!</span><div><p class="admin-kicker">SHIFT PRIORITIES</p><h2>Needs attention</h2></div></div>
                    <p><strong>{{ $pendingRequests }}</strong> booking request{{ $pendingRequests === 1 ? '' : 's' }} waiting for review</p>
                    <a class="frontdesk-primary-link" href="{{ route('admin.bookings') }}">Review bookings <span aria-hidden="true">→</span></a>
                </section>
                <section class="frontdesk-feature frontdesk-status">
                    <div class="frontdesk-feature-title"><span class="frontdesk-feature-icon" aria-hidden="true">⌂</span><div><p class="admin-kicker">LIVE INVENTORY</p><h2>Room status</h2></div></div>
                    <div class="frontdesk-status-list">
                        <div><span><i class="is-available"></i>Available</span><strong>{{ $roomStatusCounts['available'] }}</strong></div>
                        <div><span><i class="is-occupied"></i>Occupied or held now</span><strong>{{ $roomStatusCounts['occupied'] }}</strong></div>
                        <div><span><i class="is-reserved"></i>Reserved later</span><strong>{{ $roomStatusCounts['reserved'] }}</strong></div>
                        <div><span><i class="is-cleaning"></i>Cleaning / maintenance</span><strong>{{ $roomStatusCounts['cleaning'] + $roomStatusCounts['maintenance'] }}</strong></div>
                    </div>
                    <a class="frontdesk-text-link" href="{{ route('admin.rooms') }}">View all rooms <span aria-hidden="true">→</span></a>
                </section>
            </div>

            @if($canSeeDecisionSupport)
            <div class="frontdesk-secondary-grid">
                <section class="frontdesk-feature frontdesk-activity">
                    <div class="frontdesk-feature-title"><span class="frontdesk-feature-icon" aria-hidden="true">◷</span><div><p class="admin-kicker">LATEST UPDATES</p><h2>Recent activity</h2></div></div>
                    @forelse($recentActivity as $activity)
                        <div class="frontdesk-activity-row"><span class="frontdesk-activity-dot" aria-hidden="true"></span><div><strong>{{ $activity->description ?: str_replace('_', ' ', ucfirst($activity->event)) }}</strong><small>{{ $activity->created_at?->diffForHumans() }}</small></div></div>
                    @empty
                        <p class="frontdesk-empty">No recent activity recorded.</p>
                    @endforelse
                </section>
                <section class="frontdesk-feature frontdesk-carol">
                    <div class="frontdesk-feature-title"><span class="frontdesk-feature-icon" aria-hidden="true">✦</span><div><p class="admin-kicker">CAROL · DECISION SUPPORT</p><h2>Recommendation</h2></div></div>
                    @if($carolSignal)
                        <h3>{{ $carolSignal['title'] }}</h3>
                        <p>{{ $carolSignal['evidence'] }}</p>
                        <a class="frontdesk-text-link" href="{{ $carolSignal['action_url'] }}">{{ $carolSignal['action_label'] }} <span aria-hidden="true">→</span></a>
                    @else
                        <p class="frontdesk-empty">No operational rules need attention right now.</p>
                        <a class="frontdesk-text-link" href="{{ route('admin.decision-support') }}">Open CAROL <span aria-hidden="true">→</span></a>
                    @endif
                </section>
            </div>
            @endif
        </section>

        <div class="frontdesk-details-heading"><div><p class="admin-kicker">FRONT DESK</p><h2>Shift details</h2><p>Arrivals, active stays, departures, and room operations.</p></div></div>

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
                                <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
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
                            <div class="today-actions"><a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a></div>
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
                                <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
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
                            <span class="today-maintenance-label">{{ ucfirst($block->status) }} · {{ $block->starts_at->lte($propertyNow) && $block->ends_at->gt($propertyNow) ? 'In progress' : 'Scheduled today' }}</span>
                            <b>{{ $block->room?->name ?? 'Room' }}</b>
                            <small>{{ $block->starts_at->format('M j · g:i A') }} – {{ $block->ends_at->format('M j · g:i A') }}</small>
                            @if($block->notes)<p class="today-maintenance-note">{{ $block->notes }}</p>@endif
                            @if($block->status === 'cleaning' && $block->starts_at->lte($propertyNow) && $block->ends_at->gt($propertyNow))
                                @include('admin.partials.cleaning-countdown', ['remainingSeconds' => max(0, $block->ends_at->getTimestamp() - $propertyNow->getTimestamp())])
                            @endif
                        </article>
                    @empty
                        <p class="empty-copy">No cleaning or maintenance items overlap today.</p>
                    @endforelse
                </div>
            </section>
        </section>
    </main>
</div>
<nav class="frontdesk-mobile-tabs" aria-label="Quick staff navigation">
    @foreach(\App\Support\AdminPermissions::navigation(auth()->user()) as $item)
        @if($item['group'] === 'primary' && \App\Support\AdminPermissions::allows(auth()->user(), $item['ability']))
            <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>@include('admin.partials.sidebar-icon', ['route' => $item['route']])<span>{{ $item['label'] }}</span></a>
        @endif
    @endforeach
</nav>
@include('admin.partials.confirmation-dialog')
</body>
</html>
