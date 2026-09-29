<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promos · Carolina Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .promo-layout{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.5fr);gap:18px}.promo-form{display:grid;gap:12px}.promo-form label{display:grid;gap:5px;color:var(--muted);font-size:11px;font-weight:700}.promo-form input,.promo-form select,.promo-form textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:7px;background:var(--paper);color:var(--ink);font:inherit}.promo-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.room-checks{display:grid;grid-template-columns:1fr 1fr;gap:7px}.room-checks label{display:flex;align-items:center;gap:7px;padding:8px;border:1px solid var(--line);border-radius:7px;color:var(--ink);font-size:11px}.room-checks input{width:auto}.promo-submit{padding:10px 14px;border:0;border-radius:7px;background:var(--orange);color:#fff;font-weight:700;cursor:pointer}.promo-list{display:grid;gap:12px}.promo-card{padding:16px;border:1px solid var(--line);border-radius:10px;background:var(--paper)}.promo-card-head{display:flex;justify-content:space-between;gap:12px}.promo-code{display:inline-flex;padding:5px 8px;border-radius:5px;background:#fff0d8;color:var(--orange-dark);font-size:12px;font-weight:800;letter-spacing:.05em}.promo-card p{color:var(--muted);font-size:12px}.promo-meta{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.promo-meta span{padding:5px 8px;border-radius:999px;background:var(--sand);font-size:10px;font-weight:700}.promo-edit summary{color:var(--orange-dark);cursor:pointer;font-size:12px;font-weight:700}.promo-edit .promo-form{margin-top:12px}.promo-status{font-size:11px;font-weight:800}.promo-status.off{color:#a23d2e}@media(max-width:900px){.promo-layout{grid-template-columns:1fr}.promo-grid,.room-checks{grid-template-columns:1fr}}
    </style>
</head>
<body><div class="admin-shell">@include('admin.partials.sidebar')<main class="admin-main">
    <header class="admin-topbar"><div><p class="admin-kicker">PRICING</p><h1>Promo codes</h1><p class="admin-subtitle">Create discounts and restrict them to selected rooms, stay lengths, dates, or redemption counts.</p></div></header>
    @include('admin.partials.flash')
    <div class="promo-layout">
        <section class="panel"><div class="panel-heading"><div><p class="admin-kicker">NEW OFFER</p><h2>Create promo</h2></div></div>
            <form class="promo-form" method="POST" action="{{ route('admin.promos.store') }}">@csrf
                @include('admin.partials.promo-fields', ['promo' => new \App\Models\PromoCode(['discount_type' => 'fixed_total', 'minimum_hours' => 720, 'maximum_hours' => 720, 'is_active' => true]), 'rooms' => $rooms])
                <button class="promo-submit" type="submit">Create promo code</button>
            </form>
        </section>
        <section class="panel"><div class="panel-heading"><div><p class="admin-kicker">ACTIVE & PAST</p><h2>Offers</h2></div><span class="booking-count">{{ $promos->count() }} total</span></div>
            <div class="promo-list">@forelse($promos as $promo)
                <article class="promo-card"><div class="promo-card-head"><div><span class="promo-code">{{ $promo->code }}</span><h3>{{ $promo->name }}</h3></div><span class="promo-status {{ $promo->is_active ? '' : 'off' }}">{{ $promo->is_active ? 'Active' : 'Paused' }}</span></div>
                    <p>{{ $promo->description ?: 'No description' }}</p>
                    <div class="promo-meta"><span>{{ $promo->discount_type === 'percentage' ? rtrim(rtrim(number_format($promo->discount_value, 2), '0'), '.') . '% off' : ($promo->discount_type === 'fixed_amount' ? '₱'.number_format($promo->discount_value).' off' : '₱'.number_format($promo->discount_value).' total') }}</span><span>{{ $promo->minimum_hours ? $promo->minimum_hours.'h minimum' : 'No minimum' }}</span><span>{{ $promo->usage_limit ? $promo->times_used.'/'.$promo->usage_limit.' used' : $promo->times_used.' used' }}</span><span>{{ $promo->rooms->pluck('name')->join(', ') }}</span></div>
                    <details class="promo-edit"><summary>Edit offer</summary><form class="promo-form" method="POST" action="{{ route('admin.promos.update', $promo) }}">@csrf @method('PATCH')
                        @include('admin.partials.promo-fields', ['promo' => $promo, 'rooms' => $rooms])
                        <button class="promo-submit" type="submit">Save changes</button>
                    </form></details>
                </article>
            @empty<p class="empty-copy">No promo codes yet.</p>@endforelse</div>
        </section>
    </div>
</main></div></body></html>
