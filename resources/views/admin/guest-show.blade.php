<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $guest->guest_name ?? $guest->user?->name ?? 'Guest' }} · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .guest-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.guest-grid .wide{grid-column:1/-1}.guest-note{border-left:3px solid var(--orange);padding:10px 12px;background:#fffaf3;margin:8px 0}.guest-note small{color:var(--muted)}.note-form textarea,.restriction-form input{width:100%;border:1px solid var(--line);border-radius:7px;padding:10px;font:inherit;box-sizing:border-box}.note-form button,.restriction-form button{margin-top:8px;border:0;border-radius:7px;padding:10px 13px;background:var(--orange);color:#fff;font-weight:700;cursor:pointer}.restriction-form button.danger{background:#a84336}@media(max-width:700px){.guest-grid{grid-template-columns:1fr}.guest-grid .wide{grid-column:auto}}
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-kicker">GUEST PROFILE</p>
                <h1>{{ $guest->guest_name ?? $guest->user?->name ?? 'Guest' }}</h1>
                <p class="admin-subtitle">{{ $guest->guest_email ?? $guest->user?->email ?? 'No email' }} · {{ $guest->guest_phone ?? 'No phone' }}</p>
            </div>
        </header>
        @include('admin.partials.flash')
        <p><a href="{{ route('admin.guests') }}">← Back to guests</a></p>
        <section class="guest-grid">
            <article class="panel">
                <p class="admin-kicker">STATS</p>
                <h2>{{ $bookings->count() }} booking{{ $bookings->count() === 1 ? '' : 's' }}</h2>
                <p>{{ $bookings->where('status','confirmed')->count() }} confirmed · {{ $bookings->where('status','cancelled')->count() }} cancelled · {{ $bookings->filter(fn($booking) => $booking->check_in_at?->isFuture())->count() }} upcoming</p>
            </article>
            <article class="panel">
                <p class="admin-kicker">BOOKING RESTRICTION</p>
                @if($restriction)
                    <h2>Restricted</h2><p>{{ $restriction->reason }}</p>
                    @if(auth()->user()->isAdmin())
                        <form class="restriction-form" method="POST" action="{{ route('admin.guests.restriction', $guestToken) }}" onsubmit="return confirm('Remove this booking restriction?')">@csrf @method('PATCH')<input type="hidden" name="action" value="remove"><button type="submit">Remove restriction</button></form>
                    @else
                        <p class="empty-copy">Only an administrator can change booking restrictions.</p>
                    @endif
                @elseif(auth()->user()->isAdmin())
                    <h2>Not restricted</h2><form class="restriction-form" method="POST" action="{{ route('admin.guests.restriction', $guestToken) }}" onsubmit="return confirm('Restrict future bookings for this guest?')">@csrf @method('PATCH')<input type="hidden" name="action" value="restrict"><label for="reason">Internal reason</label><input id="reason" name="reason" maxlength="500" required><button class="danger" type="submit">Restrict future bookings</button></form>
                @else
                    <p>Only an administrator can change booking restrictions.</p>
                @endif
            </article>
            <article class="panel wide">
                <p class="admin-kicker">BOOKING HISTORY</p>
                <div class="admin-table-wrap"><table><thead><tr><th>Reference</th><th>Room</th><th>Stay</th><th>Status</th><th>Payment</th><th>Total</th></tr></thead><tbody>@foreach($bookings as $booking)<tr><td><a href="{{ route('admin.bookings.show',$booking) }}">{{ $booking->reference }}</a></td><td>{{ $booking->room?->name ?? 'Room removed' }}</td><td>{{ $booking->check_in->format('M j, Y') }} – {{ $booking->check_out->format('M j, Y') }}</td><td>{{ ucfirst($booking->status) }}</td><td>{{ ucfirst($booking->payment_status) }}</td><td>₱{{ number_format($booking->total_amount,2) }}</td></tr>@endforeach</tbody></table></div>
            </article>
            <article class="panel wide">
                <p class="admin-kicker">INTERNAL NOTES</p>
                <form class="note-form" method="POST" action="{{ route('admin.guests.notes.store', $guestToken) }}">@csrf<label for="guest-note">Operational note</label><textarea id="guest-note" name="content" maxlength="2000" required placeholder="Keep notes factual and relevant to operations."></textarea><button type="submit">Save note</button></form>
                @forelse($notes as $note)<div class="guest-note">{{ $note->content }}<br><small>{{ $note->author?->name ?? 'Staff' }} · {{ $note->created_at->format('M j, Y g:i A') }}</small></div>@empty<p class="empty-copy">No internal notes have been added.</p>@endforelse
            </article>
        </section>
    </main>
</div>
</body>
</html>
