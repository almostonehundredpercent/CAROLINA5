<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promos · Carolina Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .promo-layout { display: grid; grid-template-columns: minmax(360px, .9fr) minmax(0, 1.45fr); align-items: start; gap: 22px; }
        .promo-layout > .panel { min-width: 0; padding: 24px; }
        .promo-panel-note { margin: 7px 0 0; color: #d0bead; font-size: 13px; line-height: 1.5; }
        .promo-form { display: grid; gap: 14px; }
        .promo-form label { display: grid; align-content: start; gap: 7px; color: #f1e3d5; font-size: 13px; font-weight: 650; line-height: 1.4; }
        .promo-form label > span { display: block; }
        .promo-form label small { color: #cbbcaf; font-size: 11px; font-weight: 500; }
        .promo-form input:not([type="checkbox"]), .promo-form select, .promo-form textarea { width: 100%; min-height: 46px; padding: 11px 13px; border: 1px solid rgba(255,220,182,.24); border-radius: 10px; background: #17110e; color: #fff5ea; font: inherit; font-size: 14px; }
        .promo-form textarea { min-height: 88px; resize: vertical; }
        .promo-form input::placeholder, .promo-form textarea::placeholder { color: #b8a392; opacity: 1; }
        .promo-form input:focus-visible, .promo-form select:focus-visible, .promo-form textarea:focus-visible { outline: 3px solid rgba(255,180,77,.42); outline-offset: 2px; border-color: #ffb44d; }
        .promo-field-note { margin: -5px 0 0; color: #cbbcaf; font-size: 12px; line-height: 1.45; }
        .promo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .promo-form-section { display: grid; gap: 14px; padding: 16px; border: 1px solid rgba(255,220,182,.16); border-radius: 15px; background: linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.012)); }
        .promo-form-section-heading { display: grid; grid-template-columns: 34px minmax(0,1fr); align-items: start; gap: 11px; }
        .promo-step { display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid rgba(255,180,77,.42); border-radius: 50%; background: rgba(227,138,0,.12); color: #ffc16b; font-size: 12px; font-weight: 800; }
        .promo-form-section-heading h3 { margin: 0; color: #fff6ed; font-size: 15px; letter-spacing: -.01em; }
        .promo-form-section-heading p { margin: 4px 0 0; color: #cbbcaf; font-size: 12px; line-height: 1.45; }
        .room-fieldset { min-width: 0; margin: 0; padding: 14px; border: 1px solid rgba(255,220,182,.2); border-radius: 14px; }
        .room-fieldset legend { padding: 0 6px; color: #fff4e8; font-size: 13px; font-weight: 750; }
        .room-fieldset > p { margin: 0 0 11px; color: #cbbcaf; font-size: 12px; line-height: 1.45; }
        .room-checks { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; max-height: 275px; overflow-y: auto; padding: 2px; }
        .promo-form .room-checks label { display: flex; min-height: 46px; align-items: center; gap: 10px; padding: 9px 11px; border: 1px solid rgba(255,220,182,.2); border-radius: 10px; background: rgba(17,14,12,.42); color: #fff2e6; cursor: pointer; transition: border-color .16s ease, background .16s ease; }
        .promo-form .room-checks label:has(input:checked) { border-color: rgba(255,180,77,.7); background: rgba(227,138,0,.13); }
        .promo-form .room-checks input[type="checkbox"], .promo-active input[type="checkbox"] { width: 18px; height: 18px; flex: 0 0 18px; accent-color: #e38a00; }
        .promo-active { display: flex !important; min-height: 48px; align-items: center; gap: 10px !important; padding: 10px 13px; border: 1px solid rgba(255,220,182,.18); border-radius: 11px; background: rgba(255,255,255,.035); color: #fff0df !important; cursor: pointer; }
        .promo-submit { min-height: 50px; padding: 12px 16px; border: 1px solid rgba(255,205,128,.44); border-radius: 11px; background: linear-gradient(135deg,#e89413,#c66700); color: #fff; font: inherit; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(146,81,9,.2); transition: transform .16s ease, box-shadow .16s ease, filter .16s ease; }
        .promo-submit:hover { transform: translateY(-1px); filter: brightness(1.06); box-shadow: 0 11px 24px rgba(146,81,9,.28); }
        .promo-list { display: grid; gap: 14px; }
        .promo-card { min-width: 0; display: grid; gap: 13px; padding: 19px; border: 1px solid rgba(255,220,182,.18); border-radius: 15px; background: linear-gradient(145deg,rgba(31,23,19,.94),rgba(20,15,12,.98)); box-shadow: inset 0 1px rgba(255,241,221,.04), 0 10px 26px rgba(0,0,0,.14); }
        .promo-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .promo-card-head > div { min-width: 0; }
        .promo-code { display: inline-flex; min-height: 29px; align-items: center; padding: 5px 10px; border: 1px solid rgba(255,180,77,.3); border-radius: 999px; background: rgba(227,138,0,.13); color: #ffd18e; font-size: 12px; font-weight: 800; letter-spacing: .07em; }
        .promo-card h3 { margin: 10px 0 0; color: #fff8f1; font-size: 18px; line-height: 1.3; letter-spacing: -.02em; }
        .promo-card > p { margin: 0; color: #d6c5b6; font-size: 14px; line-height: 1.55; }
        .promo-meta { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; }
        .promo-meta span { display: inline-flex; min-height: 30px; align-items: center; padding: 6px 10px; border: 1px solid rgba(255,220,182,.14); border-radius: 999px; background: rgba(255,255,255,.045); color: #f5e5d5; font-size: 12px; font-weight: 650; }
        .promo-status { display: inline-flex; min-height: 30px; flex: 0 0 auto; align-items: center; padding: 5px 10px; border: 1px solid rgba(97,206,144,.35); border-radius: 999px; background: rgba(31,125,67,.18); color: #9de2b9; font-size: 12px; font-weight: 800; }
        .promo-status.off { border-color: rgba(255,139,122,.3); background: rgba(155,54,38,.16); color: #ffab9c; }
        .promo-room-scope { display: grid; gap: 8px; padding-top: 12px; border-top: 1px solid rgba(255,220,182,.13); }
        .promo-room-scope summary, .promo-edit summary { display: flex; min-height: 42px; align-items: center; gap: 8px; color: #ffc16b; font-size: 13px; font-weight: 750; cursor: pointer; list-style: none; }
        .promo-room-scope summary::-webkit-details-marker, .promo-edit summary::-webkit-details-marker { display: none; }
        .promo-room-scope summary::before, .promo-edit summary::before { content: '＋'; display: grid; width: 22px; height: 22px; place-items: center; border: 1px solid rgba(255,180,77,.26); border-radius: 50%; font-size: 12px; }
        .promo-room-scope[open] summary::before, .promo-edit[open] summary::before { content: '−'; }
        .promo-room-count { margin-left: auto; color: #d4c1af; font-size: 12px; font-weight: 600; }
        .promo-room-names { margin: 0; color: #d8c7b7; font-size: 13px; line-height: 1.6; overflow-wrap: anywhere; }
        .promo-edit { padding-top: 9px; border-top: 1px solid rgba(255,220,182,.13); }
        .promo-edit .promo-form { margin-top: 8px; }
        html:not(.dark-mode) .promo-panel-note, html:not(.dark-mode) .promo-form-section-heading p, html:not(.dark-mode) .room-fieldset > p, html:not(.dark-mode) .promo-field-note { color: #74685e; }
        html:not(.dark-mode) .promo-form label, html:not(.dark-mode) .room-fieldset legend { color: #3d3027; }
        html:not(.dark-mode) .promo-form label small { color: #74685e; }
        html:not(.dark-mode) .promo-form input:not([type="checkbox"]), html:not(.dark-mode) .promo-form select, html:not(.dark-mode) .promo-form textarea { border-color: #d8cbbb; background: #fffdf9; color: #2b211b; }
        html:not(.dark-mode) .promo-form input::placeholder, html:not(.dark-mode) .promo-form textarea::placeholder { color: #82756a; }
        html:not(.dark-mode) .promo-form-section { border-color: #e5ddd4; background: linear-gradient(145deg,#fffaf4,#f8f1e8); }
        html:not(.dark-mode) .promo-form-section-heading h3 { color: #2b211b; }
        html:not(.dark-mode) .room-fieldset { border-color: #d9ccbd; }
        html:not(.dark-mode) .promo-form .room-checks label { border-color: #e5ddd4; background: #fffdf9; color: #3d3027; }
        html:not(.dark-mode) .promo-form .room-checks label:has(input:checked) { border-color: #d18a36; background: #fff0dd; }
        html:not(.dark-mode) .promo-active { border-color: #e5ddd4; background: #fffaf4; color: #3d3027 !important; }
        html:not(.dark-mode) .promo-card { border-color: #e5ddd4; background: linear-gradient(145deg,#fffdf9,#f8f3ed); box-shadow: 0 9px 23px rgba(63,38,20,.055); }
        html:not(.dark-mode) .promo-card h3 { color: #2b211b; }
        html:not(.dark-mode) .promo-card > p { color: #5f5146; }
        html:not(.dark-mode) .promo-code { border-color: #f0d3aa; background: #fff0dd; color: #8d4b0b; }
        html:not(.dark-mode) .promo-meta span { border-color: #e5ddd4; background: #f5eee6; color: #4a3b31; }
        html:not(.dark-mode) .promo-status { border-color: #b7dfc5; background: #edf8f0; color: #17663b; }
        html:not(.dark-mode) .promo-status.off { border-color: #efc8c2; background: #fff1ef; color: #9f3027; }
        html:not(.dark-mode) .promo-room-scope, html:not(.dark-mode) .promo-edit { border-color: #e8ded3; }
        html:not(.dark-mode) .promo-room-scope summary, html:not(.dark-mode) .promo-edit summary { color: #9a5608; }
        html:not(.dark-mode) .promo-room-scope summary::before, html:not(.dark-mode) .promo-edit summary::before { border-color: #e5c394; }
        html:not(.dark-mode) .promo-room-count, html:not(.dark-mode) .promo-room-names { color: #5f5146; }
        @media (max-width: 1050px) { .promo-layout { grid-template-columns: minmax(0,1fr); } }
        @media (max-width: 620px) { .promo-layout > .panel { padding: 16px; }.promo-grid { grid-template-columns: minmax(0,1fr); }.promo-form-section { padding: 13px; }.promo-card { padding: 15px; }.promo-card-head { gap: 9px; }.room-checks { max-height: 235px; } }
        @media (max-width: 390px) { .room-checks { grid-template-columns: minmax(0,1fr); } }
        @media (prefers-reduced-motion: reduce) { .promo-submit, .room-checks label { transition: none; }.promo-submit:hover { transform: none; } }
    </style>
</head>
<body><div class="admin-shell">@include('admin.partials.sidebar')<main class="admin-main">
    <header class="admin-topbar"><div><p class="admin-kicker">PRICING</p><h1>Promo codes</h1><p class="admin-subtitle">Create discounts and restrict them to selected rooms, stay lengths, dates, or redemption counts.</p></div></header>
    @include('admin.partials.flash')
    <div class="promo-layout">
        <section class="panel promo-create-panel"><div class="panel-heading"><div><p class="admin-kicker">NEW OFFER</p><h2>Create promo</h2><p class="promo-panel-note">Set the discount, choose who can use it, and decide when it is available.</p></div></div>
            <form class="promo-form" method="POST" action="{{ route('admin.promos.store') }}">@csrf
                @include('admin.partials.promo-fields', ['promo' => new \App\Models\PromoCode(['discount_type' => 'fixed_total', 'minimum_hours' => 720, 'maximum_hours' => 720, 'is_active' => true]), 'rooms' => $rooms])
                <button class="promo-submit" type="submit">Create promo code</button>
            </form>
        </section>
        <section class="panel promo-offers-panel"><div class="panel-heading"><div><p class="admin-kicker">ACTIVE & PAST</p><h2>Offers</h2><p class="promo-panel-note">Review each offer's status, discount, and room access.</p></div><span class="booking-count">{{ $promos->count() }} total</span></div>
            <div class="promo-list">@forelse($promos as $promo)
                <article class="promo-card"><div class="promo-card-head"><div><span class="promo-code">{{ $promo->code }}</span><h3>{{ $promo->name }}</h3></div><span class="promo-status {{ $promo->is_active ? '' : 'off' }}">{{ $promo->is_active ? 'Active' : 'Paused' }}</span></div>
                    <p>{{ $promo->description ?: 'No description added.' }}</p>
                    <div class="promo-meta"><span>{{ $promo->discount_type === 'percentage' ? rtrim(rtrim(number_format($promo->discount_value, 2), '0'), '.') . '% off' : ($promo->discount_type === 'fixed_amount' ? '₱'.number_format($promo->discount_value).' off' : '₱'.number_format($promo->discount_value).' total') }}</span><span>{{ $promo->minimum_hours ? $promo->minimum_hours.'h minimum' : 'No minimum stay' }}</span><span>{{ $promo->usage_limit ? $promo->times_used.'/'.$promo->usage_limit.' redeemed' : $promo->times_used.' redeemed' }}</span></div>
                    <details class="promo-room-scope"><summary>View eligible rooms <span class="promo-room-count">{{ $promo->rooms->count() }} {{ $promo->rooms->count() === 1 ? 'room' : 'rooms' }}</span></summary><p class="promo-room-names">{{ $promo->rooms->pluck('name')->join(', ') ?: 'No rooms selected' }}</p></details>
                    <details class="promo-edit"><summary>Edit offer</summary><form class="promo-form" method="POST" action="{{ route('admin.promos.update', $promo) }}">@csrf @method('PATCH')
                        @include('admin.partials.promo-fields', ['promo' => $promo, 'rooms' => $rooms])
                        <button class="promo-submit" type="submit">Save changes</button>
                    </form></details>
                </article>
            @empty<p class="empty-copy">No promo codes yet.</p>@endforelse</div>
        </section>
    </div>
</main></div></body></html>
