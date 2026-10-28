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
        .dss-page { display: grid; gap: 18px; min-width: 0; }
        .dss-page > *, .dss-settings-grid > *, .dss-grid > * { min-width: 0; }
        .dss-lead { max-width: 850px; color: var(--muted); line-height: 1.6; }
        .dss-settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .dss-settings-card { min-width: 0; padding: 16px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); }
        .dss-page .panel-heading { flex-wrap: wrap; gap: 12px; }
        .dss-form { display: grid; gap: 11px; }
        .dss-form label { display: grid; gap: 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .dss-form input { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); color: var(--ink); font: inherit; }
        .dss-form input[type="number"] { max-width: 170px; }
        .dss-primary, .dss-secondary { width: fit-content; padding: 10px 14px; border: 0; border-radius: 8px; background: var(--orange); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .dss-secondary { padding: 7px 10px; background: transparent; color: var(--orange-dark); }
        .dss-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .dss-card { min-width: 0; padding: 15px; border: 1px solid var(--line); border-radius: 10px; background: color-mix(in srgb, var(--paper) 72%, var(--white)); }
        .dss-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .dss-card h3 { margin: 0; font-size: 16px; }
        .dss-card-date { margin: 4px 0 0; color: var(--muted); font-size: 12px; }
        .dss-tag { display: inline-flex; flex: 0 0 auto; padding: 5px 8px; border-radius: 999px; background: color-mix(in srgb, var(--orange) 16%, var(--paper)); color: var(--orange-dark); font-size: 10px; font-weight: 800; }
        .dss-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; margin: 14px 0 9px; }
        .dss-metrics > div { display: grid; gap: 4px; padding: 10px; border-radius: 8px; background: var(--paper); }
        .dss-metrics small { color: var(--muted); font-size: 10px; }
        .dss-metrics strong { font-size: 16px; }
        .dss-card p { margin: 8px 0; line-height: 1.55; }
        .dss-season-context { color: var(--orange-dark); font-size: 12px; font-weight: 700; }
        .dss-card small, .dss-note { color: var(--muted); font-size: 11px; line-height: 1.5; }
        .dss-action { display: inline-flex; margin-top: 10px; color: var(--orange-dark); font-size: 12px; font-weight: 700; text-decoration: none; }
        .dss-action:hover { text-decoration: underline; }
        .dss-season-list { display: grid; gap: 8px; margin-top: 14px; }
        .dss-season-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 9px 0; border-top: 1px solid var(--line); }
        .dss-season-row > span { min-width: 0; overflow-wrap: anywhere; }
        .dss-season-row form { margin: 0; }
        .dss-table-wrap { max-width: 100%; overflow-x: auto; overscroll-behavior-x: contain; -webkit-overflow-scrolling: touch; }
        .dss-table { width: 100%; min-width: 620px; border-collapse: collapse; }
        .dss-table th, .dss-table td { padding: 11px 9px; border-bottom: 1px solid var(--line); text-align: left; font-size: 12px; }
        .dss-table th { color: var(--muted); font-size: 10px; letter-spacing: .06em; text-transform: uppercase; }
        .dss-error { color: #b42318; font-size: 11px; }
        .dss-controls { padding: 0; overflow: hidden; }
        .dss-controls > summary { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 17px 18px; color: var(--ink); font-weight: 700; cursor: pointer; list-style: none; }
        .dss-controls > summary::-webkit-details-marker { display: none; }
        .dss-controls > summary span { display: grid; gap: 4px; }
        .dss-controls > summary small { color: var(--muted); font-size: 11px; font-weight: 400; }
        .dss-controls > summary::after { content: '+'; color: var(--orange-dark); font-size: 22px; line-height: 1; }
        .dss-controls[open] > summary::after { content: '−'; }
        .dss-controls-body { display: grid; gap: 12px; padding: 0 16px 16px; border-top: 1px solid var(--line); }
        .dss-how-it-works { color: var(--muted); font-size: 12px; line-height: 1.6; }
        .dss-how-it-works summary { width: fit-content; margin: 12px 0 6px; color: var(--orange-dark); font-weight: 700; cursor: pointer; }
        .dss-how-it-works p { max-width: 900px; margin: 6px 0 0; }
        @media(max-width: 850px) {
            .dss-settings-grid, .dss-grid { grid-template-columns: minmax(0, 1fr); }
        }
        @media(max-width: 600px) {
            .dss-page { gap: 14px; }
            .dss-page .panel { padding: 14px; }
            .dss-lead { font-size: 13px; }
            .dss-settings-grid { gap: 12px; }
            .dss-card { padding: 13px; }
            .dss-card-top { flex-wrap: wrap; align-items: center; }
            .dss-season-row { align-items: flex-start; }
            .dss-controls > summary { padding: 15px 14px; }
            .dss-controls-body { padding: 0 12px 12px; }
            .dss-page .panel-heading > .dss-primary { width: 100%; justify-content: center; text-align: center; }
            .dss-table-wrap::after { content: 'Swipe horizontally to see all promo results'; display: block; padding: 8px 2px 0; color: var(--muted); font-size: 10px; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main dss-page">
        <header class="admin-topbar">
            <div><p class="admin-kicker">ADMIN TOOLS</p><h1>Room suggestions</h1><p class="admin-subtitle">A quick way to spot rooms that may be worth promoting.</p></div>
            <div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Organization Admin</small></div></div>
        </header>
        @include('admin.partials.flash')
        <section class="panel">
            <div class="panel-heading"><div><p class="admin-kicker">NEXT 90 DAYS</p><h2>Rooms with time still open</h2></div><a class="dss-primary" href="{{ route('admin.promos') }}">Review promo codes</a></div>
            <p class="dss-lead">These are rooms with fewer than {{ $threshold }}% of bookable hours confirmed in a week and at least 6 hours still open. Suggestions use the current booking schedule; they are not forecasts. You decide whether to create a promotion.</p>
            @if($opportunities->isNotEmpty())
                <div class="dss-grid">
                    @foreach($opportunities as $opportunity)
                        <article class="dss-card">
                            <div class="dss-card-top">
                                <div><h3>{{ $opportunity['room']->name }}</h3><p class="dss-card-date">{{ $opportunity['starts_at']->format('M j') }}–{{ $opportunity['ends_at']->format('M j, Y') }}</p></div>
                                <span class="dss-tag">{{ $opportunity['utilization'] }}% booked</span>
                            </div>
                            @if($opportunity['season_names']->isNotEmpty())<p class="dss-season-context">Dates overlap: {{ $opportunity['season_names']->join(', ') }}</p>@endif
                            <div class="dss-metrics">
                                <div><small>Confirmed booking time</small><strong>{{ number_format($opportunity['confirmed_hours'], 1) }} h</strong></div>
                                <div><small>Room time still open</small><strong>{{ number_format($opportunity['available_hours'], 1) }} h</strong></div>
                            </div>
                            <small>Out of {{ number_format($opportunity['sellable_hours'], 1) }} hours available to book after scheduled cleaning and maintenance.</small>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="empty-copy">No rooms meet this rule right now. You can adjust the rule below to see more or fewer suggestions.</p>
            @endif
        </section>

        <details class="panel dss-controls" @if($errors->any()) open @endif>
            <summary><span>Adjust suggestions<small>Change what counts as a quiet room or add event dates</small></span></summary>
            <div class="dss-controls-body">
                <section class="dss-settings-grid" aria-label="Suggestion settings">
                    <article class="dss-settings-card">
                        <div class="panel-heading"><div><p class="admin-kicker">BOOKING RULE</p><h2>How quiet should a room be?</h2></div></div>
                        <form class="dss-form" method="POST" action="{{ route('admin.decision-support.settings') }}">
                            @csrf @method('PATCH')
                            <label for="low_occupancy_threshold">Show a suggestion when less than this percent of the room is booked in a week</label>
                            <input id="low_occupancy_threshold" name="low_occupancy_threshold" type="number" min="5" max="95" step="1" required value="{{ old('low_occupancy_threshold', $threshold) }}">
                            @error('low_occupancy_threshold')<small class="dss-error">{{ $message }}</small>@enderror
                            <small class="dss-note">Current rule: below {{ $threshold }}% booked, with at least 6 hours still open. A lower number shows fewer suggestions; a higher number shows more.</small>
                            <button class="dss-primary" type="submit">Save rule</button>
                        </form>
                    </article>
                    <article class="dss-settings-card">
                        <div class="panel-heading"><div><p class="admin-kicker">OPTIONAL</p><h2>Add event dates</h2></div></div>
                        <p class="dss-note">For example, add Christmas dates. An event only labels a matching room suggestion; it does not create one on its own.</p>
                        <form class="dss-form" method="POST" action="{{ route('admin.decision-support.seasons.store') }}">
                            @csrf
                            <label>Event name<input type="text" name="name" maxlength="100" placeholder="Christmas" value="{{ old('name') }}" required></label>
                            @error('name')<small class="dss-error">{{ $message }}</small>@enderror
                            <div class="dss-settings-grid">
                                <label>Starts<input type="date" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on') }}" required></label>
                                <label>Ends<input type="date" name="ends_on" min="{{ today()->toDateString() }}" value="{{ old('ends_on') }}" required></label>
                            </div>
                            @error('starts_on')<small class="dss-error">{{ $message }}</small>@enderror
                            @error('ends_on')<small class="dss-error">{{ $message }}</small>@enderror
                            <button class="dss-primary" type="submit">Save event dates</button>
                        </form>
                        <div class="dss-season-list">
                            @forelse($seasons as $season)
                                <div class="dss-season-row"><span><b>{{ $season->name }}</b><small> · {{ $season->starts_on->format('M j, Y') }}–{{ $season->ends_on->format('M j, Y') }}</small></span><form method="POST" action="{{ route('admin.decision-support.seasons.destroy', $season) }}">@csrf @method('DELETE')<button class="dss-secondary" type="submit" aria-label="Remove {{ $season->name }} dates">Remove</button></form></div>
                            @empty
                                <small class="dss-note">No event dates added.</small>
                            @endforelse
                        </div>
                    </article>
                </section>
                <details class="dss-how-it-works"><summary>How are suggestions chosen?</summary><p>Each room is checked week by week for the next 90 days. The system compares confirmed booking time with the room time available after scheduled cleaning and maintenance. It also sets aside pending bookings and other blocked time before showing how much remains open. A suggestion appears only when the booked share is below the rule above and at least 6 hours remain open. Event dates add a label when they overlap. The system does not predict demand, pick a discount, or activate a promo.</p></details>
            </div>
        </details>

        <section class="panel">
            <div class="panel-heading"><div><p class="admin-kicker">LAST 90 DAYS</p><h2>Promo code results</h2></div></div>
            <p class="dss-note">These counts show what happened to bookings that used a promo code. They do not show whether the promo caused a booking.</p>
            <div class="dss-table-wrap" role="region" aria-label="Promo results table. Swipe horizontally on small screens." tabindex="0"><table class="dss-table"><thead><tr><th>Promo code</th><th>Used</th><th>Confirmed</th><th>Paid</th><th>Completed</th><th>Cancelled</th><th>Refunded</th></tr></thead><tbody>
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
