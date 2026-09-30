@php
    $locationName = 'Carolina Air BnB — San Juan';
    $locationEyebrow = $locationEyebrow ?? 'GETTING HERE';
    $locationAddress = 'Tomas Cabiles Street, Zone 6, San Juan, Tabaco City, Albay — near Tabaco College';
    // Exact Google Maps pin beside Tabaco College. Coordinates prevent Maps from
    // resolving the similarly named Panal branch instead.
    $mapDestination = '13.3567576,123.7260803';
    $mapQuery = rawurlencode($mapDestination);
    $streetViewUrl = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint='.$mapQuery.'&heading=0&pitch=0&fov=80';
@endphp
<style>
    .booking-location { max-width:760px; margin:22px auto 28px; overflow:hidden; border:1px solid var(--line); border-radius:16px; background:#fff; text-align:left; }
    .booking-location-copy { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:20px 22px; }
    .booking-location-copy h2 { margin:0 0 5px; font:700 1.25rem/1.25 'DM Sans',sans-serif; }
    .booking-location-copy p { margin:0; font-size:.9rem; }
    .booking-location-actions { flex:0 0 auto; display:flex; align-items:center; gap:9px; }
    .booking-location-link { flex:0 0 auto; display:inline-flex; align-items:center; gap:7px; padding:10px 14px; border-radius:999px; background:var(--gold); color:#fff; font-size:.86rem; font-weight:700; }
    .booking-location-link.is-secondary { border:1px solid var(--gold); background:transparent; color:var(--gold); }
    .booking-location-link svg { width:17px; height:17px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .booking-location iframe { display:block; width:100%; height:300px; border:0; background:var(--sand); }
    .dark-mode .booking-location { background:#211914; border-color:#4d3a2e; }
    @media(max-width:650px) { .booking-location-copy { align-items:flex-start; flex-direction:column; padding:18px; }.booking-location-actions { width:100%; flex-direction:column; }.booking-location-link { width:100%; justify-content:center; }.booking-location iframe { height:260px; } }
</style>
<section class="booking-location" aria-labelledby="booking-location-title">
    <div class="booking-location-copy">
        <div><span class="eyebrow">{{ $locationEyebrow }}</span><h2 id="booking-location-title">{{ $locationName }}</h2><p>{{ $locationAddress }}</p></div>
        <div class="booking-location-actions">
            <a class="booking-location-link is-secondary" href="{{ $streetViewUrl }}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>Street View</a>
            <a class="booking-location-link" href="https://www.google.com/maps/dir/?api=1&destination={{ $mapQuery }}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.25"/></svg>Get directions</a>
        </div>
    </div>
    <iframe title="Map to {{ $locationName }}" src="https://www.google.com/maps?q={{ $mapQuery }}&output=embed" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe>
</section>
