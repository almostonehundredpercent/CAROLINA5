<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Decision Support · Carolina Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .dss-page { display: grid; gap: 18px; }
        .dss-lead { max-width: 850px; color: var(--muted); line-height: 1.6; }
        .dss-settings-grid { display: grid; grid-template-columns: minmax(240px, .8fr) minmax(0, 1.2fr); gap: 18px; }
        .dss-form { display: grid; gap: 11px; }
        .dss-form label { display: grid; gap: 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .dss-form input { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); color: var(--ink); font: inherit; }
        .dss-form input[type="number"] { max-width: 170px; }
        .dss-primary, .dss-secondary { width: fit-content; padding: 10px 14px; border: 0; border-radius: 8px; background: var(--orange); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .dss-secondary { padding: 7px 10px; background: transparent; color: var(--orange-dark); }
        .dss-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .dss-card { min-width: 0; padding: 15px; border: 1px solid var(--line); border-radius: 10px; background: color-mix(in srgb, var(--paper) 72%, var(--white)); }
        .dss-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .dss-card h3 { margin: 0; font-size: 15px; }
        .dss-tag { display: inline-flex; flex: 0 0 auto; padding: 5px 8px; border-radius: 999px; background: color-mix(in srgb, var(--orange) 16%, var(--paper)); color: var(--orange-dark); font-size: 10px; font-weight: 800; }
        .dss-card p { margin: 8px 0; line-height: 1.55; }
        .dss-card small, .dss-note { color: var(--muted); font-size: 11px; line-height: 1.5; }
        .dss-action { display: inline-flex; margin-top: 10px; color: var(--orange-dark); font-size: 12px; font-weight: 700; text-decoration: none; }
        .dss-action:hover { text-decoration: underline; }
        .dss-season-list { display: grid; gap: 8px; margin-top: 14px; }
        .dss-season-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 9px 0; border-top: 1px solid var(--line); }
        .dss-season-row form { margin: 0; }
        .dss-table-wrap { overflow-x: auto; }
        .dss-table { width: 100%; min-width: 620px; border-collapse: collapse; }
        .dss-table th, .dss-table td { padding: 11px 9px; border-bottom: 1px solid var(--line); text-align: left; font-size: 12px; }
        .dss-table th { color: var(--muted); font-size: 10px; letter-spacing: .06em; text-transform: uppercase; }
        .dss-error { color: #b42318; font-size: 11px; }
        @media(max-width: 850px) { .dss-settings-grid, .dss-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main dss-page">
        <header class="admin-topbar">
            <div><p class="admin-kicker">ADMIN TOOLS</p><h1>Decision support</h1><p class="admin-subtitle">Rule-based suggestions from recorded reservations and room operations.</p></div>
            <div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Organization Admin</small></div></div>
        </header>
        @include('admin.partials.flash')
        @if($errors->any())<div class="admin-flash" role="alert">Please review the highlighted fields: {{ $errors->first() }}</div>@endif
        <p class="dss-lead">Promo opportunities are flagged when a room has low scheduled room-time utilization and at least six room-hours remain open. Room-time is measured across the 24-hour reservation schedule. Each suggestion shows the dates and recorded booking evidence. Seasonal dates add context only; they do not create a suggestion by themselves. Review every suggestion before creating or changing an offer.</p>

        <section class="dss-settings-grid" aria-label="Decision support settings">
            <article class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">RULE SETTING</p><h2>Low-utilization threshold</h2></div></div>
                <form class="dss-form" method="POST" action="{{ route('admin.decision-support.settings') }}">
                    @csrf @method('PATCH')
                    <label for="low_occupancy_threshold">Suggest a promo review below this scheduled utilization (%)</label>
                    <input id="low_occupancy_threshold" name="low_occupancy_threshold" type="number" min="5" max="95" step="1" required value="{{ old('low_occupancy_threshold', $threshold) }}">
                    @error('low_occupancy_threshold')<small class="dss-error">{{ $message }}</small>@enderror
                    <small class="dss-note">Current rule: below {{ $threshold }}% in a 7-day window, with at least 6 room-hours still open. Analysis covers the next 90 days.</small>
                    <button class="dss-primary" type="submit">Save rule</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><p class="admin-kicker">OPTIONAL CONTEXT</p><h2>Seasonal dates</h2></div></div>
                <form class="dss-form" method="POST" action="{{ route('admin.decision-support.seasons.store') }}">
                    @csrf
                    <label>Season or event name<input type="text" name="name" maxlength="100" placeholder="Christmas" value="{{ old('name') }}" required></label>
                    @error('name')<small class="dss-error">{{ $message }}</small>@enderror
                    <div class="dss-settings-grid">
                        <label>Starts<input type="date" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on') }}" required></label>
                        <label>Ends<input type="date" name="ends_on" min="{{ today()->toDateString() }}" value="{{ old('ends_on') }}" required></label>
                    </div>
                    @error('starts_on')<small class="dss-error">{{ $message }}</small>@enderror
                    @error('ends_on')<small class="dss-error">{{ $message }}</small>@enderror
                    <button class="dss-primary" type="submit">Add seasonal dates</button>
                </form>
                <div class="dss-season-list">
                    @forelse($seasons as $season)
                        <div class="dss-season-row"><span><b>{{ $season->name }}</b><small> · {{ $season->starts_on->format('M j, Y') }}–{{ $season->ends_on->format('M j, Y') }}</small></span><form method="POST" action="{{ route('admin.decision-support.seasons.destroy', $season) }}">@csrf @method('DELETE')<button class="dss-secondary" type="submit" aria-label="Remove {{ $season->name }} dates">Remove</button></form></div>
                    @empty
                        <small class="dss-note">No upcoming seasonal dates entered.</small>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading"><div><p class="admin-kicker">NEXT 90 DAYS · 7-DAY WINDOWS</p><h2>Promo opportunities to review</h2></div><a class="dss-primary" href="{{ route('admin.promos') }}">Open promo codes</a></div>
            <p class="dss-note">Utilization is confirmed room-hours divided by sellable room-hours after maintenance and cleaning blocks. Hourly and date-based reservations are counted separately. At most one highest-priority window is shown per room. This describes the current reservation schedule; it is not a forecast.</p>
            @if($opportunities->isNotEmpty())
                <div class="dss-grid">
                    @foreach($opportunities as $opportunity)
                        <article class="dss-card">
                            <div class="dss-card-head"><h3>{{ $opportunity['room']->name }}</h3><span class="dss-tag">{{ $opportunity['utilization'] }}% scheduled</span></div>
                            <p>{{ $opportunity['starts_at']->format('M j') }}–{{ $opportunity['ends_at']->format('M j, Y') }}
                                @if($opportunity['season_names']->isNotEmpty())<br><b>Seasonal context:</b> {{ $opportunity['season_names']->join(', ') }}@endif
                            </p>
                            <p><b>{{ number_format($opportunity['confirmed_hours'], 1) }}</b> confirmed room-hours of <b>{{ number_format($opportunity['sellable_hours'], 1) }}</b> sellable hours.<br><b>{{ number_format($opportunity['available_hours'], 1) }}</b> room-hours remain open.<br>{{ $opportunity['hourly_stays'] }} hourly and {{ $opportunity['date_stays'] }} date-based confirmed or held reservations overlap this window.</p>
                            <small>Rule: utilization below {{ $opportunity['threshold'] }}% and at least {{ $opportunity['minimum_available_hours'] }} available room-hours. Consider whether a promo is appropriate; the system does not choose a discount or activate an offer.</small>
                            <br><a class="dss-action" href="{{ route('admin.promos') }}">Review promo codes →</a>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="empty-copy">No upcoming 7-day room window currently meets both rules. Seasonal dates alone do not trigger a suggestion.</p>
            @endif
        </section>

        <section class="panel">
            <div class="panel-heading"><div><p class="admin-kicker">RECORDED REDEMPTIONS · LAST 90 DAYS</p><h2>Promo booking outcomes</h2></div></div>
            <p class="dss-note">These counts describe bookings created in the last 90 days that had a promo code saved. They do not prove that a promotion caused a booking or increased revenue.</p>
            <div class="dss-table-wrap"><table class="dss-table"><thead><tr><th>Promo code</th><th>Applied</th><th>Confirmed</th><th>Paid</th><th>Completed</th><th>Cancelled</th><th>Refunded</th></tr></thead><tbody>
                @forelse($promoOutcomes as $outcome)
                    <tr><td><b>{{ $outcome['code'] }}</b></td><td>{{ $outcome['applied'] }}</td><td>{{ $outcome['confirmed'] }}</td><td>{{ $outcome['paid'] }}</td><td>{{ $outcome['completed'] }}</td><td>{{ $outcome['cancelled'] }}</td><td>{{ $outcome['refunded'] }}</td></tr>
                @empty
                    <tr><td colspan="7" class="no-bookings">No promo-code bookings were recorded in the last 90 days.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
</div>
</body>
</html>
