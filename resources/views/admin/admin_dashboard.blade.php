<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Carolina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .metric-grid .metric-card {
            min-height: 150px;
            padding: 20px 78px 18px 20px;
        }

        .metric-grid .metric-card .metric-icon {
            position: absolute;
            top: 18px;
            right: 18px;
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: 13px;
            font-size: 20px;
            font-weight: 800;
            line-height: 1;
        }

        .metric-grid .metric-card .metric-icon svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .metric-grid .metric-card small {
            margin: 0 0 8px;
        }

        .metric-grid .metric-card em {
            margin-top: 10px;
        }

        .metric-card--payments .metric-icon {
            color: #a65000;
            background: #ffead3;
        }

        .metric-card--bookings .metric-icon {
            color: #38598f;
            background: #e7eef9;
        }

        .metric-card--confirmed .metric-icon {
            color: #247047;
            background: #e1f2e8;
        }

        .metric-card--available .metric-icon {
            color: #9a4336;
            background: #fbe4de;
        }

        html.dark-mode .metric-card--payments .metric-icon {
            color: #ffb56c;
            background: #4a2b12;
        }

        html.dark-mode .metric-card--bookings .metric-icon {
            color: #a9c7f6;
            background: #243550;
        }

        html.dark-mode .metric-card--confirmed .metric-icon {
            color: #8bd5aa;
            background: #203f2e;
        }

        html.dark-mode .metric-card--available .metric-icon {
            color: #f0a294;
            background: #4d2923;
        }

        @media (max-width: 700px) {
            .metric-grid .metric-card {
                min-height: 132px;
                padding: 16px 66px 16px 16px;
            }

            .metric-grid .metric-card .metric-icon {
                top: 15px;
                right: 15px;
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar"><div><p class="admin-kicker">OVERVIEW</p><h1>Dashboard</h1></div><div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div></div></header>
        @if(session('success'))<div class="admin-flash">{{ session('success') }}</div>@endif
        <section class="metric-grid" id="rooms">
            <article class="metric-card metric-card--payments"><span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6.5A3.5 3.5 0 0 1 6.5 3h10A2.5 2.5 0 0 1 19 5.5V7h1a2 2 0 0 1 2 2v8.5a3.5 3.5 0 0 1-3.5 3.5h-12A3.5 3.5 0 0 1 3 17.5v-11Zm16 3.5h-4a2 2 0 1 0 0 4h4v-4Zm-4 1.25a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5Z"/></svg></span><small>Payments recorded</small><strong>₱{{ number_format($revenue) }}</strong><em>Only staff-marked paid stays</em></article>
            <article class="metric-card metric-card--bookings"><span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 2a2 2 0 0 0-2 2H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1a2 2 0 0 0-2-2H8Zm0 2h8v2H8V4Zm-2 6h2v2H6v-2Zm4 0h8v2h-8v-2Zm-4 4h2v2H6v-2Zm4 0h8v2h-8v-2Zm-4 4h2v2H6v-2Zm4 0h8v2h-8v-2Z"/></svg></span><small>Total bookings</small><strong>{{ $bookingCount }}</strong><em>All reservations</em></article>
            <article class="metric-card metric-card--confirmed"><span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm4.78-12.88a1 1 0 0 0-1.56-1.24l-4.18 5.22-2.33-2.33a1 1 0 0 0-1.42 1.42l3.12 3.12a1 1 0 0 0 1.49-.08l4.88-6.11Z" clip-rule="evenodd"/></svg></span><small>Confirmed</small><strong>{{ $confirmedCount }}</strong><em>Ready for arrival</em></article>
            <article class="metric-card metric-card--available"><span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12.67 2.5a1 1 0 0 0-1.34 0l-9 8A1 1 0 0 0 3 12.25h1V20a2 2 0 0 0 2 2h4v-6h4v6h4a2 2 0 0 0 2-2v-7.75h1a1 1 0 0 0 .67-1.75l-9-8Z"/></svg></span><small>Available rooms</small><strong>{{ $roomsByStatus['available'] }}</strong><em>{{ $roomCount }} active room{{ $roomCount === 1 ? '' : 's' }}</em></article>
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
