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
        .frontdesk-carol-recommendation{border:1px solid var(--line);border-radius:10px;background:rgba(255,255,255,.24)}
        .frontdesk-carol-recommendation summary{display:block;padding:12px;list-style:none;cursor:pointer}
        .frontdesk-carol-recommendation summary::-webkit-details-marker{display:none}
        .frontdesk-carol-recommendation summary h3{margin:0 0 6px;color:var(--frontdesk-ink);font-size:1rem}
        .frontdesk-carol-recommendation summary p{margin:0;color:var(--frontdesk-muted);font-size:.84rem;line-height:1.45}
        .frontdesk-carol-recommendation summary span{display:inline-flex;margin-top:9px;color:#995006;font-size:.8rem;font-weight:800}
        .frontdesk-carol-detail{padding:0 12px 12px}
        .frontdesk-carol-detail p{margin:0;color:var(--frontdesk-ink);font-size:.84rem;line-height:1.5}
        .frontdesk-carol-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .frontdesk-carol-actions form{margin:0}
        .frontdesk-carol-button{min-height:38px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:var(--paper);color:var(--frontdesk-ink);font:inherit;font-size:.78rem;font-weight:800}
        .frontdesk-carol-button.is-primary{border-color:#a1500a;background:#a1500a;color:#fff}
        .frontdesk-carol-active{display:grid;gap:10px}
        .frontdesk-carol-active p{margin:0;color:var(--frontdesk-muted);font-size:.87rem;line-height:1.5}
        .frontdesk-carol-active form{margin:0}
        html.dark-mode .frontdesk-carol-recommendation{background:rgba(255,255,255,.035)}
        html.dark-mode .frontdesk-carol-recommendation summary span{color:#ffc17d}
        html.dark-mode .frontdesk-carol-button.is-primary{border-color:#a1500a;background:#a1500a}
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
            <a class="frontdesk-walkin" href="{{ route('admin.walk-ins.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>
                New walk-in
            </a>
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
                    <p><strong>{{ $pendingRequests }}</strong> booking{{ $pendingRequests === 1 ? '' : 's' }} not yet confirmed</p>
                    @if($pastPendingBookings->isNotEmpty())<p><strong>{{ $pastPendingBookings->count() }}</strong> past booking{{ $pastPendingBookings->count() === 1 ? '' : 's' }} still marked pending</p>@endif
                    @if($cancellationRequests->isNotEmpty())<p><strong>{{ $cancellationRequests->count() }}</strong> cancellation request{{ $cancellationRequests->count() === 1 ? '' : 's' }} from the previous approval workflow</p>@endif
                    @if($earlyCheckoutRequests->isNotEmpty())<p><strong>{{ $earlyCheckoutRequests->count() }}</strong> early check-out request{{ $earlyCheckoutRequests->count() === 1 ? '' : 's' }} waiting for confirmation</p>@endif
                    @if($pendingRequests || $pastPendingBookings->isNotEmpty() || $cancellationRequests->isNotEmpty() || $earlyCheckoutRequests->isNotEmpty())
                        <button class="frontdesk-primary-link" type="button" data-frontdesk-request-dialog-open>View request details <span aria-hidden="true">→</span></button>
                    @endif
                </section>
                <section class="frontdesk-feature frontdesk-status">
                    <div class="frontdesk-feature-title"><span class="frontdesk-feature-icon" aria-hidden="true">⌂</span><div><p class="admin-kicker">LIVE INVENTORY</p><h2>Room status</h2></div></div>
                    <div class="frontdesk-status-list">
                        <div><span><i class="is-available"></i>Available</span><strong>{{ $roomStatusCounts['available'] }}</strong></div>
                        <div><span><i class="is-occupied"></i>Occupied or held now</span><strong>{{ $roomStatusCounts['occupied'] }}</strong></div>
                        <div><span><i class="is-reserved"></i>Reserved later</span><strong>{{ $roomStatusCounts['reserved'] }}</strong></div>
                        <div><span><i class="is-cleaning"></i>Cleaning / maintenance</span><strong>{{ $roomStatusCounts['cleaning'] + $roomStatusCounts['maintenance'] }}</strong></div>
                    </div>
                    <p class="today-panel-note">Held arrivals count here before staff records the guest’s check-in.</p>
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
                    @include('admin.partials.carol-recommendation-deck', ['signals' => $carolSignals ?? collect(), 'paymentReminderEnabled' => $carolPaymentReminderEnabled])
                </section>
            </div>
            @endif
        </section>

        @if($pendingRequests || $pastPendingBookings->isNotEmpty() || $cancellationRequests->isNotEmpty() || $earlyCheckoutRequests->isNotEmpty())
            <dialog class="dashboard-detail-dialog" id="frontdesk-request-dialog" aria-labelledby="frontdesk-request-dialog-title">
                <div class="dashboard-detail-shell">
                    <header class="dashboard-detail-header">
                        <div><p class="admin-kicker">SHIFT PRIORITIES</p><h2 id="frontdesk-request-dialog-title">Requests needing action</h2><p>Pending bookings are not confirmed yet. Past stays are listed separately.</p></div>
                        <button class="dashboard-detail-close" type="button" data-frontdesk-request-dialog-close aria-label="Close request details">×</button>
                    </header>
                    <div class="dashboard-detail-body">
                        @if($pendingBookingRequests->isNotEmpty())
                        <section class="dashboard-detail-section">
                            <h3>Unconfirmed bookings · {{ $pendingBookingRequests->count() }}</h3>
                            <div class="dashboard-detail-table-wrap" role="region" aria-label="Booking requests waiting for review" tabindex="0">
                                <table class="dashboard-detail-table"><thead><tr><th>Guest / reference</th><th>Room</th><th>Stay</th><th>Guests</th><th>Payment</th><th>Action</th></tr></thead><tbody>
                                    @foreach($pendingBookingRequests as $requestBooking)
                                        <tr>
                                            <td><strong>{{ $requestBooking->guest_name ?? $requestBooking->user?->name ?? 'Guest' }}</strong><small>{{ $requestBooking->reference }}</small></td>
                                            <td>{{ $requestBooking->room?->name ?? 'Room removed' }}</td>
                                            <td>{{ $requestBooking->check_in_at?->format('M j, Y · g:i A') ?? $requestBooking->check_in?->format('M j, Y') }}<br>{{ $requestBooking->check_out_at?->format('M j, Y · g:i A') ?? $requestBooking->check_out?->format('M j, Y') }}</td>
                                            <td>{{ $requestBooking->guests ?? 1 }}</td>
                                            <td>₱{{ number_format((float) $requestBooking->total_amount, 2) }}<small>{{ $requestBooking->payment_method ? strtoupper($requestBooking->payment_method).' · ' : '' }}{{ ucfirst(str_replace('_', ' ', (string) $requestBooking->payment_status)) }}</small>
                                                @if((float) $requestBooking->deposit_amount > 0)<small>Downpayment ₱{{ number_format((float) $requestBooking->deposit_amount, 2) }} · {{ $requestBooking->deposit_status === 'verified' || $requestBooking->deposit_verified_at ? 'verified' : 'awaiting verification' }}</small>@endif
                                            </td>
                                            <td><div class="frontdesk-request-actions">
                                                @if((float) $requestBooking->deposit_amount <= 0 || $requestBooking->deposit_status === 'verified' || $requestBooking->deposit_verified_at)
                                                    <form method="POST" action="{{ route('admin.bookings.update', $requestBooking) }}" data-admin-confirm data-confirm-title="Confirm this booking?" data-confirm-message="Confirm {{ $requestBooking->guest_name ?? $requestBooking->user?->name ?? 'this guest' }}’s booking for {{ $requestBooking->room?->name ?? 'this room' }}? This reserves the room for the listed stay dates." data-confirm-action="Confirm booking">
                                                        @csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="status-select confirmed" type="submit">Confirm</button>
                                                    </form>
                                                @else
                                                    <button class="status-select" type="button" disabled title="Verify the required downpayment before confirming">Verify payment first</button>
                                                @endif
                                                <form method="POST" action="{{ route('admin.bookings.update', $requestBooking) }}" data-admin-confirm data-confirm-title="Cancel this booking?" data-confirm-message="Cancel {{ $requestBooking->reference }}? The room dates will be released." data-confirm-action="Cancel booking" data-confirm-style="cancellation">
                                                    @csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="status-select cancelled" type="submit">Cancel</button>
                                                </form>
                                            </div></td>
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            </div>
                        </section>
                        @endif

                        @if($pastPendingBookings->isNotEmpty())
                        <section class="dashboard-detail-section">
                            <h3>Past stays still marked pending · {{ $pastPendingBookings->count() }}</h3>
                            <p class="dashboard-detail-empty">These stay dates have ended, but the booking status was never finalized.</p>
                            <div class="dashboard-detail-table-wrap" role="region" aria-label="Past stays still marked pending" tabindex="0">
                                <table class="dashboard-detail-table"><thead><tr><th>Guest / reference</th><th>Room</th><th>Stay</th><th>Guests</th><th>Payment</th></tr></thead><tbody>
                                    @foreach($pastPendingBookings as $requestBooking)
                                        <tr>
                                            <td><strong>{{ $requestBooking->guest_name ?? $requestBooking->user?->name ?? 'Guest' }}</strong><small>{{ $requestBooking->reference }}</small></td>
                                            <td>{{ $requestBooking->room?->name ?? 'Room removed' }}</td>
                                            <td>{{ $requestBooking->check_in_at?->format('M j, Y · g:i A') ?? $requestBooking->check_in?->format('M j, Y') }}<br>{{ $requestBooking->check_out_at?->format('M j, Y · g:i A') ?? $requestBooking->check_out?->format('M j, Y') }}</td>
                                            <td>{{ $requestBooking->guests ?? 1 }}</td>
                                            <td>₱{{ number_format((float) $requestBooking->total_amount, 2) }}<small>{{ $requestBooking->payment_method ? strtoupper($requestBooking->payment_method).' · ' : '' }}{{ ucfirst(str_replace('_', ' ', (string) $requestBooking->payment_status)) }}</small>
                                                @if((float) $requestBooking->deposit_amount > 0)<small>Downpayment ₱{{ number_format((float) $requestBooking->deposit_amount, 2) }} · {{ $requestBooking->deposit_status === 'verified' || $requestBooking->deposit_verified_at ? 'verified' : 'awaiting verification' }}</small>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            </div>
                        </section>
                        @endif

                        @if($cancellationRequests->isNotEmpty())
                        <section class="dashboard-detail-section">
                            <h3>Cancellation requests · {{ $cancellationRequests->count() }}</h3>
                            <div class="dashboard-detail-table-wrap" role="region" aria-label="Cancellation requests from the previous approval workflow" tabindex="0">
                                <table class="dashboard-detail-table"><thead><tr><th>Guest / reference</th><th>Room</th><th>Check-in</th><th>Requested</th><th>Reason</th><th>Action</th></tr></thead><tbody>
                                    @foreach($cancellationRequests as $cancellationRequest)
                                        <tr>
                                            <td><strong>{{ $cancellationRequest->guest_name ?? $cancellationRequest->user?->name ?? 'Guest' }}</strong><small>{{ $cancellationRequest->reference }}</small></td><td>{{ $cancellationRequest->room?->name ?? 'Room removed' }}</td><td>{{ $cancellationRequest->check_in_at?->format('M j, Y · g:i A') ?? $cancellationRequest->check_in?->format('M j, Y') }}</td><td>{{ $cancellationRequest->cancellation_requested_at?->format('M j · g:i A') ?? '—' }}</td><td>{{ $cancellationRequest->cancellation_reason ?: 'No reason provided' }}</td>
                                            <td><div class="frontdesk-request-actions">
                                                <form method="POST" action="{{ route('admin.bookings.cancellation-request.resolve', $cancellationRequest) }}" data-admin-confirm data-confirm-title="Confirm cancellation?" data-confirm-message="Confirm {{ $cancellationRequest->guest_name ?? $cancellationRequest->user?->name ?? 'this guest' }}’s request? This cancels the booking and releases the room dates." data-confirm-action="Confirm cancellation" data-confirm-style="cancellation">
                                                    @csrf<input type="hidden" name="decision" value="confirm"><button class="status-select cancelled" type="submit">Confirm</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.bookings.cancellation-request.resolve', $cancellationRequest) }}" data-admin-confirm data-confirm-title="Decline cancellation?" data-confirm-message="Decline this request? The booking will remain active and the room will stay reserved." data-confirm-action="Decline request">
                                                    @csrf<input type="hidden" name="decision" value="decline"><button class="status-select" type="submit">Decline</button>
                                                </form>
                                            </div></td>
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            </div>
                        </section>
                        @endif

                        @if($earlyCheckoutRequests->isNotEmpty())
                        <section class="dashboard-detail-section">
                            <h3>Early check-out requests · {{ $earlyCheckoutRequests->count() }}</h3>
                            <div class="dashboard-detail-table-wrap" role="region" aria-label="Early check-out requests waiting for confirmation" tabindex="0">
                                <table class="dashboard-detail-table"><thead><tr><th>Guest / reference</th><th>Room</th><th>Scheduled check-out</th><th>Requested</th><th>Balance</th><th>Action</th></tr></thead><tbody>
                                    @foreach($earlyCheckoutRequests as $earlyCheckoutRequest)
                                        @php($earlyCheckoutBalance = \App\Support\FinancialSummary::forBooking($earlyCheckoutRequest)['outstanding'])
                                        <tr>
                                            <td><strong>{{ $earlyCheckoutRequest->guest_name ?? $earlyCheckoutRequest->user?->name ?? 'Guest' }}</strong><small>{{ $earlyCheckoutRequest->reference }}</small></td><td>{{ $earlyCheckoutRequest->room?->name ?? 'Room removed' }}</td><td>{{ $earlyCheckoutRequest->check_out_at?->format('M j, Y · g:i A') ?? $earlyCheckoutRequest->check_out?->format('M j, Y') }}</td><td>{{ $earlyCheckoutRequest->early_checkout_requested_at?->format('M j · g:i A') ?? '—' }}</td><td>₱{{ number_format($earlyCheckoutBalance, 2) }}@if($earlyCheckoutBalance > 0.001)<small>Pay before check-out</small>@endif</td>
                                            <td><div class="frontdesk-request-actions">
                                                <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $earlyCheckoutRequest) }}" data-admin-confirm data-confirm-title="Confirm early check-out?" data-confirm-message="Confirm {{ $earlyCheckoutRequest->guest_name ?? $earlyCheckoutRequest->user?->name ?? 'this guest' }}’s departure now? This marks the booking checked out and starts room cleaning. The booking total stays the same; refunds are handled separately." data-confirm-action="Confirm check-out">
                                                    @csrf<input type="hidden" name="decision" value="confirm"><button class="status-select confirmed" type="submit" @disabled($earlyCheckoutBalance > 0.001) title="{{ $earlyCheckoutBalance > 0.001 ? 'Pay the remaining balance before confirming' : 'Confirm early check-out' }}">Confirm check-out</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $earlyCheckoutRequest) }}" data-admin-confirm data-confirm-title="Decline early check-out?" data-confirm-message="Decline this request? The guest will remain checked in." data-confirm-action="Decline request" data-confirm-style="cancellation">
                                                    @csrf<input type="hidden" name="decision" value="decline"><button class="status-select cancelled" type="submit">Decline</button>
                                                </form>
                                            </div></td>
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            </div>
                        </section>
                        @endif
                    </div>
                </div>
            </dialog>
        @endif

        <div class="frontdesk-details-heading"><div><p class="admin-kicker">FRONT DESK</p><h2>Shift details</h2><p>Arrivals, active stays, departures, and room operations.</p></div></div>

        <section class="today-grid">
            @if($cancellationRequests->isNotEmpty())
                <section class="panel" aria-labelledby="cancellation-requests-title">
                    <div class="panel-heading"><div><p class="admin-kicker">LEGACY REQUESTS</p><h2 id="cancellation-requests-title">{{ $cancellationRequests->count() }} cancellation request{{ $cancellationRequests->count() === 1 ? '' : 's' }}</h2></div></div>
                    <p class="today-panel-note">The booking stays active and the room stays reserved until you confirm.</p>
                    <div class="today-list">
                        @foreach($cancellationRequests as $booking)
                            <article class="today-item">
                                <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b>
                                <small>{{ $booking->room?->name }} · Requested {{ $booking->cancellation_requested_at?->format('M j, g:i A') }}</small>
                                <div class="today-actions">
                                    <form method="POST" action="{{ route('admin.bookings.cancellation-request.resolve', $booking) }}" data-admin-confirm data-confirm-title="Confirm cancellation?" data-confirm-message="Confirm this guest’s request? This cancels the booking and releases the room dates." data-confirm-action="Confirm cancellation" data-confirm-style="cancellation">
                                        @csrf<input type="hidden" name="decision" value="confirm"><button class="status-select cancelled" type="submit">Confirm cancellation</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.cancellation-request.resolve', $booking) }}" data-admin-confirm data-confirm-title="Decline cancellation?" data-confirm-message="Decline this request? The booking will remain active and the room will stay reserved." data-confirm-action="Decline request">
                                        @csrf<input type="hidden" name="decision" value="decline"><button class="status-select" type="submit">Decline</button>
                                    </form>
                                    <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($earlyCheckoutRequests->isNotEmpty())
                <section class="panel" aria-labelledby="early-checkout-requests-title">
                    <div class="panel-heading"><div><p class="admin-kicker">GUEST REQUESTS</p><h2 id="early-checkout-requests-title">{{ $earlyCheckoutRequests->count() }} early check-out request{{ $earlyCheckoutRequests->count() === 1 ? '' : 's' }}</h2></div></div>
                    <p class="today-panel-note">The guest remains checked in until you confirm the departure.</p>
                    <div class="today-list">
                        @foreach($earlyCheckoutRequests as $booking)
                            <article class="today-item">
                                <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b>
                                <small>{{ $booking->room?->name }} · Requested {{ $booking->early_checkout_requested_at?->format('M j, g:i A') }}</small>
                                @php($earlyCheckoutBalance = \App\Support\FinancialSummary::forBooking($booking)['outstanding'])
                                <div class="today-actions">
                                    <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $booking) }}" data-admin-confirm data-confirm-title="Confirm early check-out?" data-confirm-message="Confirm {{ $booking->guest_name ?? $booking->user?->name ?? 'this guest' }}’s departure now? This marks the booking checked out and starts room cleaning. The booking total stays the same; refunds are handled separately." data-confirm-action="Confirm check-out">
                                        @csrf<input type="hidden" name="decision" value="confirm"><button class="checkout-action-button" type="submit" @disabled($earlyCheckoutBalance > 0.001)>Confirm check-out</button>
                                    </form>
                                    @if($earlyCheckoutBalance > 0.001)
                                        @include('admin.partials.balance-dialog', ['booking' => $booking, 'balance' => $earlyCheckoutBalance, 'dialogContext' => 'early-request'])
                                    @endif
                                    <form method="POST" action="{{ route('admin.bookings.early-checkout.resolve', $booking) }}" data-admin-confirm data-confirm-title="Decline this request?" data-confirm-message="Decline this early check-out request? The guest will remain checked in." data-confirm-action="Decline request" data-confirm-style="cancellation">
                                        @csrf<input type="hidden" name="decision" value="decline"><button class="status-select cancelled" type="submit">Decline</button>
                                    </form>
                                    <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

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
                            <small>{{ $booking->room?->name }} · Payment: {{ ucfirst((string) ($booking->payment_status ?: 'Not recorded')) }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions">
                                <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
                                @if($booking->cancellation_request_status !== 'requested' && $booking->check_in_at?->lte($propertyNow))
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
                            @php($activeStayBalance = \App\Support\FinancialSummary::forBooking($booking)['outstanding'])
                            <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->room?->name }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions">
                                <a class="status-select" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}">View booking</a>
                                <a class="status-select confirmed" href="{{ route('admin.bookings.show', ['booking' => $booking, 'from' => 'today']) }}#stay-extension">Extend hours</a>
                                <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" data-admin-confirm data-confirm-kicker="GUEST DEPARTURE" data-confirm-title="{{ $booking->early_checkout_status === 'requested' ? 'Confirm early check-out?' : 'Check out this guest?' }}" data-confirm-message="{{ $booking->early_checkout_status === 'requested' ? 'Confirm this guest’s requested departure now? This records check-out and starts room cleaning.' : 'Check out ' . ($booking->guest_name ?? $booking->user?->name ?? 'this guest') . ' now? This records the departure and starts room cleaning.' }}" data-confirm-action="Check out guest">
                                    @csrf<input type="hidden" name="action" value="check_out"><button class="checkout-action-button" type="submit" @disabled($activeStayBalance > 0.001)>Check out</button>
                                </form>
                                @if($activeStayBalance > 0.001)
                                    @include('admin.partials.balance-dialog', ['booking' => $booking, 'balance' => $activeStayBalance, 'dialogContext' => 'active-stay'])
                                @endif
                            </div>
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
                            @php($departureBalance = \App\Support\FinancialSummary::forBooking($booking)['outstanding'])
                            <b>{{ $booking->reference }} · {{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->room?->name }}</small>
                            <dl class="today-schedule">
                                <div><dt>Check-in</dt><dd><time datetime="{{ $booking->check_in_at?->toIso8601String() }}">{{ $booking->check_in_at?->format('M j, Y') ?? $booking->check_in?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_in_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                                <div><dt>Check-out</dt><dd><time datetime="{{ $booking->check_out_at?->toIso8601String() }}">{{ $booking->check_out_at?->format('M j, Y') ?? $booking->check_out?->format('M j, Y') }}</time></dd><dd>{{ $booking->check_out_at?->format('g:i A') ?? 'Time not recorded' }}</dd></div>
                            </dl>
                            <div class="today-actions">
                                <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" data-admin-confirm data-confirm-kicker="GUEST DEPARTURE" data-confirm-title="{{ $booking->early_checkout_status === 'requested' ? 'Confirm early check-out?' : 'Check out this guest?' }}" data-confirm-message="{{ $booking->early_checkout_status === 'requested' ? 'Confirm this guest’s requested departure now? This records check-out and starts room cleaning.' : 'Check out ' . ($booking->guest_name ?? $booking->user?->name ?? 'this guest') . ' now? This records the departure and marks the room for cleaning.' }}" data-confirm-action="Check out guest">
                                    @csrf<input type="hidden" name="action" value="check_out"><button class="checkout-action-button" type="submit" @disabled($departureBalance > 0.001)>{{ $booking->early_checkout_status === 'requested' ? 'Confirm request' : 'Check out' }}</button>
                                </form>
                                @if($departureBalance > 0.001)
                                    @include('admin.partials.balance-dialog', ['booking' => $booking, 'balance' => $departureBalance, 'dialogContext' => 'departure'])
                                @endif
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
<script>
    (() => {
        const trigger = document.querySelector('[data-frontdesk-request-dialog-open]');
        const dialog = document.getElementById('frontdesk-request-dialog');
        if (!trigger || !dialog || typeof dialog.showModal !== 'function') return;

        trigger.addEventListener('click', () => dialog.showModal());
        dialog.querySelector('[data-frontdesk-request-dialog-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => trigger.focus());
    })();
</script>
</body>
</html>
