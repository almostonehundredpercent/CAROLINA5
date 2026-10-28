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
        .dss-results-count { margin: -3px 0 14px; color: #71645a; font-size: 12px; }
        .dss-results-count strong { color: #30251d; font-size: 14px; }
        .dss-settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .dss-settings-card { min-width: 0; padding: 16px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); }
        .dss-page .panel-heading { flex-wrap: wrap; gap: 12px; }
        .dss-form { display: grid; gap: 11px; }
        .dss-form label { display: grid; gap: 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .dss-form input { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); color: var(--ink); font: inherit; }
        .dss-form input[type="number"] { max-width: 170px; }
        .dss-primary, .dss-secondary { width: fit-content; padding: 10px 14px; border: 0; border-radius: 8px; background: var(--orange); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .dss-secondary { padding: 7px 10px; background: transparent; color: var(--orange-dark); }
        .dss-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .dss-card { min-width: 0; padding: 18px; border: 1px solid #eadbca; border-radius: 14px; background: #fffdf9; color: #30251d; box-shadow: 0 7px 20px rgba(48,31,14,.06); transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
        .dss-card:hover { transform: translateY(-2px); border-color: #d9b78f; box-shadow: 0 12px 26px rgba(48,31,14,.1); }
        .dss-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .dss-card h3 { margin: 0; color: #30251d; font-size: 17px; line-height: 1.3; }
        .dss-card-date { margin: 5px 0 0; color: #71645a; font-size: 13px; }
        .dss-tag { display: inline-flex; flex: 0 0 auto; padding: 6px 9px; border-radius: 999px; background: #fff0d8; color: #8d4b0b; font-size: 11px; font-weight: 800; }
        .dss-utilization { display: grid; gap: 7px; margin-top: 14px; }
        .dss-utilization-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
        .dss-utilization-head span { color: #71645a; font-size: 12px; font-weight: 650; }
        .dss-utilization-head strong { color: #30251d; font-size: 15px; }
        .dss-utilization-track { position: relative; height: 9px; border-radius: 99px; background: #eee5da; }
        .dss-utilization-track::after { position: absolute; top: -3px; left: var(--dss-threshold); width: 2px; height: 15px; border-radius: 2px; background: #8d4b0b; content: ''; }
        .dss-utilization-track > span { display: block; width: 0; height: 100%; border-radius: inherit; background: linear-gradient(90deg,#d98914,#b85b00); }
        .dss-utilization-note { color: #71645a; font-size: 11px; }
        .dss-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 14px 0 0; }
        .dss-metrics > div { display: grid; gap: 5px; padding: 12px; border: 1px solid #eee3d7; border-radius: 10px; background: #f8f4ee; }
        .dss-metrics small { color: #71645a; font-size: 11px; line-height: 1.4; }
        .dss-metrics strong { color: #30251d; font-size: 19px; line-height: 1.2; }
        .dss-card p { margin: 10px 0 0; line-height: 1.5; }
        .dss-card .dss-card-date { margin: 5px 0 0; }
        .dss-season-context { display: inline-flex; width: fit-content; padding: 6px 10px; border-radius: 999px; background: #fff4e1; color: #8d4b0b; font-size: 12px; font-weight: 700; }
        .dss-card small, .dss-note { color: #71645a; font-size: 12px; line-height: 1.5; }
        .dss-card > small { display: block; margin-top: 11px; }
        html.dark-mode .dss-card { border-color: rgba(255,220,182,.16); background: linear-gradient(145deg,#30231b,#241a15); color: #fff4e8; box-shadow: 0 10px 26px rgba(0,0,0,.2); }
        html.dark-mode .dss-card:hover { border-color: rgba(255,193,107,.4); box-shadow: 0 14px 30px rgba(0,0,0,.3); }
        html.dark-mode .dss-card h3, html.dark-mode .dss-metrics strong { color: #fff4e8; }
        html.dark-mode .dss-card-date, html.dark-mode .dss-metrics small, html.dark-mode .dss-card > small { color: #cdbbae; }
        html.dark-mode .dss-note { color: #cdbbae; }
        html.dark-mode .dss-tag, html.dark-mode .dss-season-context { background: rgba(215,132,25,.16); color: #ffc16b; }
        html.dark-mode .dss-utilization-head span, html.dark-mode .dss-utilization-note { color: #cdbbae; }
        html.dark-mode .dss-utilization-head strong { color: #fff4e8; }
        html.dark-mode .dss-results-count { color: #cdbbae; }
        html.dark-mode .dss-results-count strong { color: #fff4e8; }
        html.dark-mode .dss-utilization-track { background: rgba(255,220,182,.14); }
        html.dark-mode .dss-utilization-track::after { background: #ffc16b; }
        html.dark-mode .dss-metrics > div { border-color: rgba(255,220,182,.12); background: rgba(17,13,10,.38); }
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
            <div class="panel-heading"><div><p class="admin-kicker">NEXT 90 DAYS</p><h2>Rooms to review for promos</h2></div><a class="dss-primary" href="{{ route('admin.promos') }}">Review promo codes</a></div>
            <p class="dss-lead">Showing rooms with less than {{ $threshold }}% of their weekly bookable time confirmed and at least 6 hours still open. Figures use confirmed bookings and planned cleaning or maintenance; they are not forecasts. You decide whether to create a promo.</p>
            <p class="dss-results-count"><strong>{{ $opportunities->count() }}</strong> room{{ $opportunities->count() === 1 ? '' : 's' }} meet this rule.</p>
            @if($opportunities->isNotEmpty())
                <div class="dss-grid">
                    @foreach($opportunities as $opportunity)
                        @php($utilizationPercent = max(0, min(100, (int) $opportunity['utilization'])))
                        <article class="dss-card">
                            <div class="dss-card-top">
                                <div><h3>{{ $opportunity['room']->name }}</h3><p class="dss-card-date">{{ $opportunity['starts_at']->format('M j') }}–{{ $opportunity['ends_at']->format('M j, Y') }}</p></div>
                                <span class="dss-tag">Low occupancy</span>
                            </div>
                            @if($opportunity['season_names']->isNotEmpty())<p class="dss-season-context">Dates overlap: {{ $opportunity['season_names']->join(', ') }}</p>@endif
                            <div class="dss-utilization">
                                <div class="dss-utilization-head"><span>Weekly occupancy</span><strong>{{ $utilizationPercent }}%</strong></div>
                                <div class="dss-utilization-track" role="progressbar" aria-label="Weekly occupancy for {{ $opportunity['room']->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $utilizationPercent }}" aria-valuetext="{{ $utilizationPercent }}% booked; promo review threshold {{ $threshold }}%" style="--dss-threshold: {{ $threshold }}%"><span style="width: {{ $utilizationPercent }}%"></span></div>
                                <small class="dss-utilization-note">Promo review threshold: {{ $threshold }}%</small>
                            </div>
                            <div class="dss-metrics">
                                <div><small>Confirmed booking time</small><strong>{{ number_format($opportunity['confirmed_hours'], 1) }} h</strong></div>
                                <div><small>Still available to book</small><strong>{{ number_format($opportunity['available_hours'], 1) }} h</strong></div>
                            </div>
                            <small>{{ number_format($opportunity['sellable_hours'], 1) }} h bookable this week after cleaning and maintenance.</small>
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
