<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookings - Carolina Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>.line-graph{position:relative}.line-chart-panel .line-graph{max-width:720px;margin-inline:auto}.line-chart-panel .line-graph svg{height:auto;aspect-ratio:600/220}.line-graph .graph-scale{position:absolute;top:3px;right:4px;color:var(--muted);font-size:10px}.line-graph .chart-empty{height:210px;display:grid;place-items:center;border-bottom:1px solid var(--line);color:var(--muted);font-size:13px;text-align:center;background:linear-gradient(#fff,#fcfbf9)}.booking-line,.booking-dot{vector-effect:non-scaling-stroke}@media(max-width:700px){.line-graph .chart-empty{height:165px}}</style>
</head>
<style>.booking-helper{margin:6px 0 0;color:var(--muted);font-size:12px}.booking-directory td{vertical-align:middle}.booking-directory code{font:600 11px 'DM Sans';color:var(--orange-dark);background:#fff6e6;border-radius:5px;padding:5px 7px}.booking-actions{display:flex;gap:7px;align-items:center}.booking-actions form{margin:0}.booking-actions .status-select{white-space:nowrap}@media(max-width:700px){.booking-directory .panel-heading{align-items:flex-start}.booking-helper{max-width:200px}.booking-actions{min-width:150px}}</style>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('home') }}"><span>c</span><b>Carolina</b><small>TRANSIENT & AIRBNB</small></a>
        <nav class="admin-nav"><a href="{{ route('admin.dashboard') }}"><i>▦</i> Dashboard</a><a href="{{ route('admin.rooms') }}"><i>⌂</i> Rooms</a><a class="active" href="{{ route('admin.bookings') }}"><i>▤</i> Bookings</a><a href="{{ route('admin.walk-ins.create') }}"><i>＋</i> Walk-ins</a><a href="{{ route('admin.reports') }}"><i>⌁</i> Reports</a></nav>
        <a href="{{ route('home') }}" class="admin-home" style="position:absolute;bottom:84px;left:16px;right:16px;border:1px solid rgba(255,255,255,.85);border-radius:8px;padding:12px 13px;color:#fff;text-decoration:none;font:600 14px 'DM Sans',sans-serif;display:flex;gap:12px;align-items:center;"><i>⌂</i> Go to home page</a>
        <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit"><i>⎋</i> Log out</button></form>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar"><div><p class="admin-kicker">RESERVATION MANAGEMENT</p><h1>Bookings</h1><p class="admin-subtitle">Live room status, booking volume, and new guests.</p></div><div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div></div></header>

        <section class="booking-stat-grid"><article class="occupied"><small>Rooms occupied</small><strong>{{ $occupiedRooms }}</strong><em>Current checked-in guests</em></article><article class="reserved"><small>Rooms reserved</small><strong>{{ $reservedRooms }}</strong><em>Upcoming stays</em></article><article class="available"><small>Rooms available</small><strong>{{ $availableRooms }}</strong><em>Ready to book</em></article><article class="maintenance"><small>Under maintenance</small><strong>{{ $maintenanceRooms }}</strong><em>Temporarily unavailable</em></article></section>

        <section class="booking-dashboard-grid">
            <article class="panel line-chart-panel">
                <div class="panel-heading"><div><p class="admin-kicker">BOOKING VOLUME</p><h2>{{ ucfirst($period) }} bookings</h2></div><form method="GET" class="period-form"><label>View period<select name="period" onchange="this.form.submit()"><option value="daily" @selected($period === 'daily')>Daily</option><option value="weekly" @selected($period === 'weekly')>Weekly</option><option value="monthly" @selected($period === 'monthly')>Monthly</option><option value="yearly" @selected($period === 'yearly')>Yearly</option></select></label></form></div>
                @php($maxValue = max(5, $chartValues->max()))
                <div class="line-graph">
                    @if($chartValues->sum() > 0)
                        <span class="graph-scale">0–{{ $maxValue }} bookings</span>
                        <svg viewBox="0 0 600 220" preserveAspectRatio="none" role="img" aria-label="{{ ucfirst($period) }} booking graph">
                            <line x1="0" y1="200" x2="600" y2="200" class="graph-axis"/><line x1="0" y1="100" x2="600" y2="100" class="graph-grid"/><line x1="0" y1="35" x2="600" y2="35" class="graph-grid"/>
                            <polyline class="booking-line" points="@foreach($chartValues as $index => $value){{ $chartValues->count() > 1 ? ($index / ($chartValues->count() - 1) * 600) : 300 }},{{ 200 - ($value / $maxValue * 160) }} @endforeach"/>
                            @foreach($chartValues as $index => $value)<circle cx="{{ $chartValues->count() > 1 ? ($index / ($chartValues->count() - 1) * 600) : 300 }}" cy="{{ 200 - ($value / $maxValue * 160) }}" r="5" class="booking-dot"><title>{{ $chartLabels[$index] }}: {{ $value }} bookings</title></circle>@endforeach
                        </svg>
                    @else
                        <div class="chart-empty">No bookings recorded for this period yet.</div>
                    @endif
                    <div class="chart-labels">@foreach($chartLabels as $label)<small>{{ $label }}</small>@endforeach</div>
                </div>
            </article>

            <article class="panel new-customer-panel"><div class="panel-heading"><div><p class="admin-kicker">LIVE UPDATES</p><h2>New customers</h2></div><span class="pill">Latest 5</span></div><div class="customer-list">@forelse($newCustomers as $booking)<div class="customer-item"><span class="customer-avatar">{{ strtoupper(substr($booking->guest_name ?? $booking->user?->name ?? 'G', 0, 1)) }}</span><div><b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->room?->name ?? 'Room' }} · {{ $booking->created_at->diffForHumans() }}</small></div><span class="mini-status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span></div>@empty<p class="empty-copy">New guest bookings will appear here automatically.</p>@endforelse</div></article>
        </section>

        <section class="panel bookings-panel booking-directory" style="margin-top:20px">
            <div class="panel-heading"><div><p class="admin-kicker">ALL RESERVATIONS</p><h2>Manage bookings</h2><p class="booking-helper">Guest details and reservation controls are together in one place.</p></div><span class="booking-count">{{ $individualBookings->total() }} total</span></div>
            <div class="admin-table-wrap"><table><thead><tr><th>Guest & contact</th><th>Reference</th><th>Room</th><th>Stay dates</th><th>Payment</th><th>Total</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($individualBookings as $booking)
                <tr>
                    <td><b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->guest_email ?? $booking->user?->email ?? 'No email provided' }}</small><small>{{ $booking->guest_phone ?? 'No phone number provided' }}</small></td>
                    <td><code>{{ $booking->reference }}</code></td><td>{{ $booking->room?->name ?? 'Room removed' }}</td><td>{{ $booking->check_in->format('M j, Y') }}<small>to {{ $booking->check_out->format('M j, Y') }}</small></td><td>{{ $booking->payment_method === 'cash' ? 'Pay at property' : 'GCash' }}</td><td><b>₱{{ number_format($booking->total_amount) }}</b></td><td><span class="mini-status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span></td>
                    <td><div class="booking-actions">@if($booking->status === 'pending')<form method="POST" action="{{ route('admin.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="status-select confirmed">Confirm</button></form>@endif @if($booking->status !== 'cancelled')<form method="POST" action="{{ route('admin.bookings.update', $booking) }}" onsubmit="return confirm('Cancel this booking? The room dates will become available again.');">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="status-select cancelled">Cancel</button></form>@else<form method="POST" action="{{ route('admin.bookings.update', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="pending"><button class="status-select">Reserve again</button></form>@endif</div></td>
                </tr>
            @empty<tr><td colspan="8" class="no-bookings">No bookings have been created yet.</td></tr>@endforelse
            </tbody></table></div>
            @if($individualBookings->hasPages())<div style="margin-top:16px">{{ $individualBookings->links() }}</div>@endif
        </section>
    </main>
</div>
</body>
</html>
