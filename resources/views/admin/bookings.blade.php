<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookings - Carolina Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .booking-actions { display: flex; flex-wrap: wrap; gap: 7px; }
        .booking-actions form { margin: 0; }
        .booking-note { color: var(--muted); font-size: 12px; margin: 5px 0; }
        .hold-expiry { display: block; color: #9d6100; font-size: 11px; margin-top: 5px; }
        .status-select { cursor: pointer; }
        .booking-filters { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
        .booking-filters input, .booking-filters select { border:1px solid var(--line); border-radius:7px; padding:9px; font:inherit; }
        .booking-filters button { border:0; border-radius:7px; padding:9px 13px; background:var(--orange); color:#fff; font-weight:700; cursor:pointer; }
    </style>
</head>
<body>
    <div class="admin-shell">
        @include('admin.partials.sidebar')

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-kicker">RESERVATION MANAGEMENT</p>
                    <h1>Bookings</h1>
                    <p class="admin-subtitle">Confirm requests, manage arrivals, and keep room inventory accurate.</p>
                </div>
            </header>

            @if(session('success'))
                <div class="admin-flash">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-flash" style="background:#fbe3e0;color:#a84336">{{ $errors->first() }}</div>
            @endif

            <section class="booking-stat-grid">
                <article class="occupied"><small>Rooms occupied</small><strong>{{ $occupiedRooms }}</strong></article>
                <article class="reserved"><small>Upcoming</small><strong>{{ $reservedRooms }}</strong></article>
                <article class="available"><small>Ready to book</small><strong>{{ $availableRooms }}</strong></article>
                <article class="maintenance"><small>Unavailable</small><strong>{{ $maintenanceRooms }}</strong></article>
            </section>

            <section class="panel" style="margin-top:20px">
                <div class="panel-heading">
                    <div>
                        <p class="admin-kicker">ALL RESERVATIONS</p>
                        <h2>Review requests</h2>
                        <p class="booking-note">No online payment is collected. Confirming rechecks the room before it becomes unavailable.</p>
                    </div>
                    <div><a class="status-select" href="{{ route('admin.bookings.export', request()->query()) }}">Export CSV</a> <span class="pill">{{ $individualBookings->total() }} total</span></div>
                </div>

                <form class="booking-filters" method="GET" aria-label="Filter bookings">
                    <input name="search" value="{{ request('search') }}" placeholder="Guest name, email, or reference">
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach(['pending', 'confirmed', 'cancelled'] as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected(request('status') === $filterStatus)>{{ ucfirst($filterStatus) }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="arrival" value="{{ request('arrival') }}" aria-label="Arrival or departure date">
                    <button type="submit">Filter</button>
                    @if(request()->hasAny(['search', 'status', 'arrival']))<a href="{{ route('admin.bookings') }}">Clear</a>@endif
                </form>

                <div class="admin-table-wrap">
                    <table>
                        <thead>
                            <tr><th>Guest</th><th>Reference</th><th>Room & stay</th><th>Total</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @forelse($individualBookings as $booking)
                                <tr>
                                    <td>
                                        <b>{{ $booking->guest_name ?? $booking->user?->name ?? 'Guest' }}</b>
                                        <small>{{ $booking->guest_email ?? $booking->user?->email ?? 'No email' }}</small>
                                        <small>{{ $booking->guest_phone ?? 'No phone' }}</small>
                                        @if($booking->children_count || $booking->pets_count)
                                            <small>{{ $booking->children_count ? $booking->children_count . ' child' . ($booking->children_count > 1 ? 'ren' : '') : 'No children' }} · {{ $booking->pets_count ? $booking->pets_count . ' pet' . ($booking->pets_count > 1 ? 's' : '') : 'No pets' }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $booking->reference }}</code></td>
                                    <td>
                                        <b>{{ $booking->room?->name ?? 'Room removed' }}</b>
                                        <small>{{ $booking->check_in_at?->format('M j, g A') ?? $booking->check_in->format('M j') }} to {{ $booking->check_out_at?->format('M j, g A') ?? $booking->check_out->format('M j, Y') }}</small>
                                    </td>
                                    <td>₱{{ number_format($booking->total_amount, 2) }}</td>
                                    <td>
                                        <span class="mini-status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                                        <small>Payment: {{ ucfirst($booking->payment_status ?? 'pending') }} · {{ strtoupper($booking->payment_method ?? 'cash') }}</small>
                                        @if($booking->status === 'pending')
                                            <span class="hold-expiry">Needs staff review</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="booking-actions">
                                            @if($booking->status !== 'cancelled')
                                                <form method="POST" action="{{ route('admin.bookings.payment', $booking) }}" onsubmit="return confirm('Update this payment record?')">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="payment_method" value="{{ in_array($booking->payment_method, ['cash', 'gcash']) ? $booking->payment_method : 'cash' }}">
                                                    <input type="hidden" name="payment_status" value="{{ $booking->payment_status === 'paid' ? 'pending' : 'paid' }}">
                                                    <button class="status-select {{ $booking->payment_status === 'paid' ? 'confirmed' : '' }}" type="submit">{{ $booking->payment_status === 'paid' ? 'Mark unpaid' : 'Mark paid' }}</button>
                                                </form>
                                            @endif
                                            @if($booking->status === 'pending')
                                                <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button class="status-select confirmed" type="submit">Confirm</button>
                                                </form>
                                            @endif

                                            @if($booking->status === 'confirmed' && ! $booking->checked_in_at && (! $booking->check_in_at || $booking->check_in_at->lte(now())))
                                                <form method="POST" action="{{ route('admin.bookings.check-in', $booking) }}" onsubmit="return confirm('Check this guest in now?')">
                                                    @csrf
                                                    <input type="hidden" name="action" value="check_in">
                                                    <button class="status-select confirmed" type="submit">Check in</button>
                                                </form>
                                            @endif

                                            @if($booking->checked_in_at && ! $booking->checked_out_at)
                                                <form method="POST" action="{{ route('admin.bookings.check-out', $booking) }}" onsubmit="return confirm('Check this guest out and start cleaning?')">
                                                    @csrf
                                                    <input type="hidden" name="action" value="check_out">
                                                    <button class="status-select" type="submit">Check out</button>
                                                </form>
                                            @endif

                                            @if($booking->status !== 'cancelled' && ! ($booking->checked_in_at && ! $booking->checked_out_at))
                                                <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" onsubmit="return confirm('Cancel this booking?')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button class="status-select cancelled" type="submit">Cancel</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="no-bookings">No reservations yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($individualBookings->hasPages())
                    <div style="margin-top:16px">{{ $individualBookings->links() }}</div>
                @endif
            </section>

            <section class="panel" style="margin-top:20px">
                <div class="panel-heading">
                    <div><p class="admin-kicker">GUEST REVIEWS</p><h2>Review moderation</h2></div>
                    <span class="pill">{{ $pendingReviews->count() }} pending</span>
                </div>
                <div class="customer-list">
                    @forelse($pendingReviews as $review)
                        <div class="customer-item">
                            <div>
                                <b>{{ $review->guest_name }} · {{ $review->room?->name }}</b>
                                <small>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} · {{ $review->comment ?: 'No written comment' }}</small>
                            </div>
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="approved">
                                <button class="status-select confirmed" type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="hidden">
                                <button class="status-select cancelled" type="submit">Hide</button>
                            </form>
                        </div>
                    @empty
                        <p class="empty-copy">No reviews waiting for moderation.</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</body>
</html>
