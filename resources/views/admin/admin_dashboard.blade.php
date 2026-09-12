<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Carolina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('home') }}"><img src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><b>Carolina</b><small>TRANSIENT & AIRBNB</small></a>
        <nav class="admin-nav">
            <a class="active" href="{{ route('admin.dashboard') }}"><i>▦</i> Dashboard</a>
            <a href="{{ route('admin.rooms') }}"><i>⌂</i> Rooms</a>
            <a href="{{ route('admin.bookings') }}"><i>▤</i> Bookings</a>
            <a href="{{ route('admin.walk-ins.create') }}"><i>＋</i> Walk-ins</a>
            <a href="{{ route('admin.reports') }}"><i>⌁</i> Reports</a>
            <a href="{{ route('admin.activity') }}"><i>◷</i> Activity log</a>
            @if(auth()->user()->isAdmin())<a href="{{ route('admin.staff') }}"><i>♙</i> Staff access</a>@endif
        </nav>
        <a href="{{ route('home') }}" class="admin-home" style="position:absolute;bottom:84px;left:16px;right:16px;border:1px solid rgba(255,255,255,.85);border-radius:8px;padding:12px 13px;color:#fff;text-decoration:none;font:600 14px 'DM Sans',sans-serif;display:flex;gap:12px;align-items:center;"><i>⌂</i> Go to home page</a>
        <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit"><i>⎋</i> Log out</button></form>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><p class="admin-kicker">OVERVIEW</p><h1>Dashboard</h1></div><div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div></div></header>
        @if(session('success'))<div class="admin-flash">{{ session('success') }}</div>@endif
        <section class="metric-grid" id="rooms">
            <article><span>₱</span><small>Payments recorded</small><strong>₱{{ number_format($revenue) }}</strong><em>Only staff-marked paid stays</em></article>
            <article><span>▤</span><small>Total bookings</small><strong>{{ $bookingCount }}</strong><em>All reservations</em></article>
            <article><span>✓</span><small>Confirmed</small><strong>{{ $confirmedCount }}</strong><em>Ready for arrival</em></article>
            <article><span>⌂</span><small>Available rooms</small><strong>{{ $roomsByStatus['available'] }}</strong><em>{{ $roomCount }} active room{{ $roomCount === 1 ? '' : 's' }}</em></article>
        </section>
        <section class="panel" style="margin-bottom:20px"><div class="panel-heading"><div><p class="admin-kicker">SHIFT ALERTS</p><h2>Needs attention</h2></div></div><div class="status-row"><span>Arrivals ready to check in</span><b>{{ $alerts['arrivals'] }}</b></div><div class="status-row"><span>Guests due for check-out</span><b>{{ $alerts['departures'] }}</b></div><div class="status-row"><span>Requests waiting for review</span><b>{{ $alerts['pending'] }}</b></div><div class="status-row"><span>Cleaning or maintenance in the next 24 hours</span><b>{{ $alerts['roomBlocks'] }}</b></div></section>
        <section class="dashboard-grid" style="margin-bottom:20px"><article class="panel status-panel"><div class="panel-heading"><div><p class="admin-kicker">LIVE INVENTORY</p><h2>Rooms today</h2></div></div><div class="status-row"><span>Occupied</span><b>{{ $roomsByStatus['occupied'] }}</b></div><div class="status-row"><span>Maintenance / cleaning</span><b>{{ $roomsByStatus['maintenance'] }}</b></div><div class="status-row"><span>Checked-in guests</span><b>{{ $checkedInCount }}</b></div><div class="status-row"><span>Checked-out stays</span><b>{{ $checkedOutCount }}</b></div></article><article class="panel status-panel"><div class="panel-heading"><div><p class="admin-kicker">PAYMENT & CANCELLATION</p><h2>Requires review</h2></div></div><div class="status-row"><span>Payment records pending</span><b>{{ $paymentPending }}</b></div><div class="status-row"><span>Cancelled reservations</span><b>{{ $cancelledCount }}</b></div><p class="empty-copy">Payment totals only include staff-recorded payments. This site does not claim online payment collection.</p></article></section>
        <section class="dashboard-grid" id="reports">
            <article class="panel chart-panel"><div class="panel-heading"><div><p class="admin-kicker">LAST 7 DAYS</p><h2>Booking activity</h2></div><span class="pill">Live data</span></div><div class="bar-chart">@foreach($chartValues as $index => $value)<div class="bar-item"><span class="bar" style="height: {{ max(8, $value * 22) }}px" title="{{ $value }} booking{{ $value === 1 ? '' : 's' }}"></span><b>{{ $value }}</b><small>{{ $chartLabels[$index] }}</small></div>@endforeach</div></article>
            <article class="panel status-panel"><div class="panel-heading"><div><p class="admin-kicker">AT A GLANCE</p><h2>Reservation status</h2></div></div><div class="status-row"><span>Pending review</span><b>{{ $pendingCount }}</b><i style="--value: {{ $bookingCount ? min(100, round($pendingCount / $bookingCount * 100)) : 0 }}%"></i></div><div class="status-row"><span>Confirmed stays</span><b>{{ $confirmedCount }}</b><i class="green" style="--value: {{ $bookingCount ? min(100, round($confirmedCount / $bookingCount * 100)) : 0 }}%"></i></div><div class="status-row"><span>Active rooms</span><b>{{ $roomCount }}</b><i class="blue" style="--value: 100%"></i></div></article>
        </section>
        <section class="dashboard-grid" style="margin-bottom:20px"><article class="panel"><div class="panel-heading"><div><p class="admin-kicker">DECISION SUPPORT</p><h2>Operational signals</h2></div></div>@forelse($recommendations as $recommendation)<p class="booking-note">• {{ $recommendation }}</p>@empty<p class="empty-copy">There is not enough operational data yet for a recommendation. Signals appear only when recorded data meets the threshold.</p>@endforelse</article><article class="panel"><div class="panel-heading"><div><p class="admin-kicker">DEMAND</p><h2>Most booked rooms</h2></div></div>@forelse($topRooms as $room)<div class="status-row"><span>{{ $room->room?->name ?? 'Archived room' }}</span><b>{{ $room->bookings_count }} stay{{ $room->bookings_count === 1 ? '' : 's' }}</b></div>@empty<p class="empty-copy">No confirmed stays have been recorded yet.</p>@endforelse</article></section>
        <section class="panel bookings-panel" id="bookings"><div class="panel-heading"><div><p class="admin-kicker">LATEST ACTIVITY</p><h2>Recent bookings</h2></div><span class="booking-count">{{ $bookingCount }} total</span></div><div class="admin-table-wrap"><table><thead><tr><th>Guest</th><th>Room</th><th>Stay dates</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($bookings as $booking)<tr><td><b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b><small>{{ $booking->guest_email ?? $booking->user?->email }} · {{ $booking->reference }}</small></td><td>{{ $booking->room?->name ?? 'Room removed' }}</td><td>{{ $booking->check_in->format('M j') }} - {{ $booking->check_out->format('M j, Y') }}</td><td>₱{{ number_format($booking->total_amount) }}</td><td><form method="POST" action="{{ route('admin.bookings.update', $booking) }}">@csrf @method('PATCH')<select class="status-select {{ $booking->status }}" name="status" onchange="this.form.submit()">@foreach(['pending','confirmed','cancelled'] as $status)<option value="{{ $status }}" @selected($booking->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></form></td></tr>@empty<tr><td colspan="5" class="no-bookings">No bookings have been placed yet.</td></tr>@endforelse</tbody></table></div></section>
    </main>
</div>
</body>
</html>
