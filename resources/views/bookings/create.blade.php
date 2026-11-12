@extends('layouts.app')

@section('content')
<style>
.booking-page{width:min(calc(100% - 40px),1240px);max-width:1240px;grid-template-columns:minmax(0,1.45fr) minmax(300px,.8fr);grid-template-areas:"heading summary" "form summary";column-gap:clamp(28px,5vw,68px);row-gap:12px;align-items:start}
.booking-page-heading{grid-area:heading;min-width:0;margin:0}
.booking-page-heading .booking-heading-back{margin-bottom:16px}
.booking-page-heading .eyebrow{margin-bottom:8px}
.booking-page-heading h1{max-width:720px;margin:0;color:var(--ink);font-size:clamp(2.6rem,5vw,4.5rem);line-height:.98;letter-spacing:-.045em}
.booking-page-heading>p{margin:10px 0 0;color:var(--muted);font-size:.95rem}
.booking-form,.booking-summary{min-width:0}
.booking-form{grid-area:form;padding:0}
.booking-page .booking-form textarea{background:#fffaf2;border-color:#d8c5ab}
.booking-availability-status{margin-top:16px;padding:14px;border:1px solid #d8c5ab;border-radius:10px;background:#fffaf2;color:var(--ink)}
.dark-mode .booking-page .booking-form textarea,.dark-mode .booking-availability-status{background:#211a15;border-color:var(--line);color:var(--ink)}
.booking-page .booking-form{background:transparent;border:0;border-radius:0;box-shadow:none}
.booking-page .booking-form form{margin-top:4px}
.booking-page .hourly-booked-summary.is-clear{border-color:#e6d4bd;background:#f7efe4;color:#746558}
.booking-page .hourly-rental{border-color:#e5d4be;border-radius:14px;background:linear-gradient(145deg,#fffaf2,#f6ecdf);color:#34291f;box-shadow:0 10px 24px rgba(76,53,31,.07)}
.booking-page .hourly-rental #hourly-total{background:#f0dfc9;color:#773c12}
.dark-mode .booking-page .hourly-booked-summary.is-clear{border-color:var(--line);background:#211a15;color:var(--muted)}
.dark-mode .booking-page .hourly-rental{border-color:var(--line);background:#211a15;color:var(--ink);box-shadow:none}
.dark-mode .booking-page .hourly-rental #hourly-total{background:#3a2c22;color:#ffe2af}
.booking-summary{--summary-title:#34291f;--summary-copy:#65594e;--summary-muted:#75685c;--summary-accent:#995b0d;--summary-inset-bg:#f6f1e9;--summary-inset-line:#e8ded2;grid-area:summary;align-self:start;margin-top:0;position:relative;overflow:hidden;padding:0;border:1px solid #e8ded2;border-radius:12px;background:#fffdf9;box-shadow:0 12px 34px rgba(59,38,16,.08);color:var(--summary-title)}
.booking-summary-head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px 12px}
.booking-summary-kicker{color:var(--summary-accent);font-size:.68rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}
.booking-summary-image{position:relative;overflow:hidden;margin:0 18px;border-radius:8px;background:#17120f}
.booking-summary-image-link{position:relative;display:block;color:inherit;text-decoration:none;cursor:pointer}
.booking-summary-image:focus-within{outline:3px solid #f1ad4b;outline-offset:3px}
.booking-summary-image::after{content:"";position:absolute;inset:45% 0 0;background:linear-gradient(transparent,rgba(18,13,10,.48));pointer-events:none}
.booking-summary img{display:block;width:100%;height:clamp(150px,18vw,220px);object-fit:cover;object-position:center 55%;border-radius:0;transition:transform .45s ease}
.booking-summary-image-link:hover img,.booking-summary-image-link:focus-visible img{transform:scale(1.025)}
.booking-summary-image-label{position:absolute;z-index:1;bottom:12px;left:13px;display:inline-flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid rgba(255,255,255,.26);border-radius:999px;background:rgba(31,23,17,.76);backdrop-filter:blur(8px);color:#fff7ec;font-size:.68rem;font-weight:700;letter-spacing:.03em;pointer-events:none}
.booking-summary-image-label span{font-size:.82rem;line-height:1}
.booking-summary-content{position:relative;padding:14px 18px 16px}
.booking-summary h3{margin:0;color:var(--summary-title);font:700 1.5rem/1.2 'Playfair Display',serif;letter-spacing:-.02em}
.booking-summary p{margin:8px 0 0;color:var(--summary-copy);line-height:1.5}
.booking-summary-rate{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;padding:11px 0;border-top:1px solid var(--summary-inset-line);border-radius:0;background:transparent}
.booking-summary-rate-label{color:var(--summary-muted);font-size:.75rem;font-weight:600}
.booking-summary-rate strong{color:var(--summary-accent);font-size:1.02rem;text-align:right}
.booking-summary-rate strong small{color:var(--summary-muted);font-size:.75rem;font-weight:500}
.booking-summary-total{display:grid;grid-template-columns:1fr auto;align-items:center;gap:3px 12px;margin:0 18px 18px;padding:13px 14px;border-radius:8px;background:#f6f1e9}
.booking-summary-total span{color:var(--summary-muted);font-size:.8rem;font-weight:650}
.booking-summary-total strong{color:var(--summary-accent);font-size:1.22rem;text-align:right}
.booking-summary-total small{grid-column:1/-1;color:var(--summary-copy);font-size:.74rem}
.dark-mode .booking-summary{--summary-title:#fff5e8;--summary-copy:#d2c3b2;--summary-muted:#c8b5a0;--summary-accent:#ffd28e;--summary-inset-bg:rgba(255,255,255,.035);--summary-inset-line:rgba(205,139,54,.25);border-color:rgba(205,139,54,.48);background:#211914;box-shadow:0 16px 36px rgba(0,0,0,.22)}
.dark-mode .booking-summary-total{background:#30251f}
.hourly-time-legend{display:flex;align-items:center;gap:7px;margin:0 0 9px;color:var(--muted);font-size:.68rem;line-height:1.4}
.hourly-time-legend:before{content:"";flex:0 0 10px;width:10px;height:10px;border-radius:3px;background:#c63e36}
@media(max-width:1000px){.booking-page{grid-template-columns:minmax(0,1.25fr) minmax(270px,.85fr);column-gap:28px}}
</style><style>
.booking-review-panel{margin-top:4px;padding:clamp(14px,2vw,20px);border:1px solid #d5a553;border-radius:14px;background:linear-gradient(145deg,#fffaf1,#fffdf9);box-shadow:0 8px 20px rgba(85,57,22,.07)}
.booking-review-panel[hidden],#booking-entry-fields[hidden],#booking-previous[hidden]{display:none!important}
.booking-review-heading{margin-bottom:12px}.booking-review-kicker{display:block;margin:0 0 4px;color:#a05d00;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.booking-review-heading h2{margin:0 0 5px;color:var(--ink);font:700 1.35rem/1.2 'DM Sans',sans-serif}.booking-review-heading p{margin:0;color:var(--muted);font-size:.86rem;line-height:1.45}
.booking-review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 22px;margin:0}.booking-review-item{min-width:0;padding:9px 0;border-bottom:1px solid var(--line)}.booking-review-item--wide{grid-column:1/-1}.booking-review-item dt{margin-bottom:3px;color:var(--muted);font-size:.74rem}.booking-review-item dd{margin:0;color:var(--ink);font-size:.92rem;font-weight:700;line-height:1.4;overflow-wrap:anywhere}
.booking-review-note{margin:12px 0 0;padding:10px 12px;border:1px solid #ead7b5;border-radius:9px;background:#fff4df;color:#644716;font-size:.82rem;line-height:1.45}.booking-step-actions{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:12px}.booking-step-actions .button{min-height:44px}.booking-step-actions #booking-submit{margin-left:auto}.booking-step-actions #booking-previous,.booking-step-actions .booking-back-to-rooms{border:1px solid #d5c2a8;background:#f3eadf;color:#573718;box-shadow:none}.booking-step-actions #booking-previous:hover,.booking-step-actions .booking-back-to-rooms:hover{border-color:#bb8a4c;background:#eadcc9;color:#422c18}.booking-step-actions #booking-previous:focus-visible,.booking-step-actions .booking-back-to-rooms:focus-visible{outline:3px solid rgba(201,121,0,.45);outline-offset:2px}.dark-mode .booking-review-panel{border-color:#805e2f;background:linear-gradient(145deg,#30251a,#241c16);box-shadow:0 12px 26px rgba(0,0,0,.2)}.dark-mode .booking-review-kicker{color:#ffc16b}.dark-mode .booking-review-note{border-color:#805d2d;background:#382b1c;color:#f6dcae}.dark-mode .booking-step-actions #booking-previous,.dark-mode .booking-step-actions .booking-back-to-rooms{border-color:#76552e;background:#30251f;color:#f2dfc7}.dark-mode .booking-step-actions #booking-previous:hover,.dark-mode .booking-step-actions .booking-back-to-rooms:hover{border-color:#a77a40;background:#3c2e23;color:#fff2df}.dark-mode .booking-step-actions #booking-previous:focus-visible,.dark-mode .booking-step-actions .booking-back-to-rooms:focus-visible{outline-color:rgba(255,194,110,.55)}
@media(max-width:520px){.booking-review-panel{padding:14px;border-radius:12px}.booking-review-heading{margin-bottom:10px}.booking-review-grid{grid-template-columns:1fr}.booking-review-item{padding:8px 0}.booking-review-item--wide{grid-column:auto}.booking-step-actions{flex-direction:column;align-items:stretch}.booking-step-actions .button{width:100%;justify-content:center}}
</style>
<style>
.stay-date-fields{display:grid;grid-template-columns:1fr 1fr;gap:15px}.stay-date-fields input[readonly]{cursor:pointer;background:#fff}
.availability-calendar{width:min(330px,100%);max-width:330px;padding:12px;border:1px solid var(--line);border-radius:12px;background:#fff}
.calendar-heading{display:flex;justify-content:center;margin-bottom:8px}
.calendar-controls{position:relative;display:flex;width:100%;align-items:center;justify-content:center;gap:8px}
.calendar-controls b{flex:1;min-width:0;text-align:center;font-size:.82rem}
.calendar-controls button{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--deep);font-size:1.2rem;line-height:1;cursor:pointer}
.calendar-controls button:hover{background:var(--sand)}.calendar-controls .calendar-close{position:absolute;right:0;font-size:1rem}
.calendar-weekdays,.calendar-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px}
.calendar-weekdays{margin-bottom:5px}.calendar-weekdays span{text-align:center;font-size:.68rem;color:var(--muted);font-weight:700}
.calendar-day,.calendar-blank{display:grid;min-width:0;height:34px;place-items:center;border:0;border-radius:5px;background:transparent;color:var(--ink);font:600 .8rem 'DM Sans'}
.calendar-day{cursor:pointer}.calendar-day:hover:not([disabled]){background:#fff0d8;color:var(--deep)}
.calendar-day.booked{background:#f8dcd9;color:#8f332d;cursor:not-allowed;text-decoration:none}.calendar-day.booked-start,.calendar-day.booked-end{border-radius:5px;background:#c63e36;color:#fff}
.calendar-day.past{color:#c8c0b8;cursor:not-allowed}.calendar-day.selected{border-radius:5px;background:var(--gold);color:#fff}.calendar-day.selected.range{background:#f6d59b;color:var(--deep)}
.calendar-legend{display:flex;align-items:center;gap:6px;margin:8px 0 0;color:var(--muted);font-size:.69rem;line-height:1.3}.calendar-legend span{width:9px;height:9px;flex:0 0 9px;border-radius:3px;background:#c63e36}
.calendar-note{margin:5px 0 0;font-size:.73rem;color:var(--deep);line-height:1.4}.booking-form form .availability-calendar{margin-top:-4px}
@media(max-width:520px){.stay-date-fields{grid-template-columns:1fr}.availability-calendar{width:100%;max-width:330px;padding:11px}.calendar-weekdays,.calendar-days{gap:3px}.calendar-weekdays span{font-size:.66rem}.calendar-day,.calendar-blank{height:34px;font-size:.78rem}}
</style>
<style>.hourly-rental{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;padding:17px;border:1px solid var(--line);border-radius:9px;background:#fff}.hourly-rental>p{grid-column:1/-1;margin:0;line-height:1.55}.hourly-rental #hourly-total{padding:10px 12px;border-radius:6px;background:#fff1db;color:var(--deep);font-weight:700}@media(max-width:520px){.hourly-rental{grid-template-columns:1fr;padding:14px}}</style>
<style>.hourly-date-field{position:relative}.hourly-date-trigger{width:100%;min-height:42px;padding:10px 12px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--ink);font:600 .86rem 'DM Sans';text-align:left;cursor:pointer}.hourly-date-trigger::after{content:'⌄';float:right;color:var(--deep);font-size:1rem}.hourly-calendar{position:absolute;z-index:5;top:calc(100% + 7px);left:0;width:min(320px,calc(100vw - 52px));padding:14px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 14px 30px rgba(41,29,18,.16)}.hourly-calendar[hidden]{display:none}.hourly-calendar-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.hourly-calendar-head b{font-size:.82rem}.hourly-calendar-head button{width:28px;height:28px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--deep);font-size:1.25rem;cursor:pointer}.hourly-calendar-week,.hourly-calendar-days{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}.hourly-calendar-week{margin-bottom:6px}.hourly-calendar-week span{text-align:center;color:var(--muted);font-size:.64rem;font-weight:700}.hourly-calendar-day,.hourly-calendar-blank{min-height:32px;border:0;border-radius:5px;background:transparent;color:var(--ink);font:600 .78rem 'DM Sans';display:grid;place-items:center}.hourly-calendar-day{cursor:pointer}.hourly-calendar-day:hover:not(:disabled){background:#fff0d8}.hourly-calendar-day.selected{background:var(--gold);color:#fff}.hourly-calendar-day:disabled{color:#c9c1b8;cursor:not-allowed}@media(max-width:520px){.hourly-calendar{width:100%;min-width:250px}.hourly-calendar-day,.hourly-calendar-blank{min-height:30px}}</style>
<style>.hourly-time-field{position:relative}.hourly-time-trigger{width:100%;min-height:42px;padding:10px 12px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--ink);font:600 .86rem 'DM Sans';text-align:left;cursor:pointer}.hourly-time-trigger::after{content:'⌄';float:right;color:var(--deep);font-size:1rem}.hourly-time-menu{position:absolute;z-index:5;top:calc(100% + 7px);left:0;width:min(360px,calc(100vw - 52px));padding:12px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 14px 30px rgba(41,29,18,.16)}.hourly-time-menu[hidden]{display:none}.hourly-time-menu-head{display:flex;align-items:baseline;justify-content:space-between;margin:0 0 10px}.hourly-time-menu-head b{font-size:.82rem}.hourly-time-menu-head small{color:var(--muted);font-size:.68rem}.hourly-time-periods{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.hourly-time-period{display:grid;gap:4px}.hourly-time-period h4{margin:0 0 2px;color:var(--muted);font:700 .61rem 'DM Sans';letter-spacing:.06em;text-transform:uppercase}.hourly-time-option{display:flex;min-height:38px;flex-direction:column;align-items:center;justify-content:center;gap:1px;border:0;border-radius:5px;background:#fff;color:var(--ink);font:600 .74rem 'DM Sans';cursor:pointer}.hourly-time-option::after{content:attr(data-status-label);font-size:.55rem;font-weight:700;line-height:1.1}.hourly-time-option:disabled{cursor:not-allowed}.hourly-time-option:hover,.hourly-time-option.selected{background:var(--gold);color:#fff}@media(max-width:520px){.hourly-time-menu{width:100%;min-width:250px}.hourly-time-periods{gap:5px}.hourly-time-option{font-size:.7rem}}</style>
<style>.clean-select{position:relative}.clean-select-trigger,.selector-display{width:100%;min-height:42px;padding:10px 12px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--ink);font:600 .86rem 'DM Sans';text-align:left}.clean-select-trigger{cursor:pointer}.clean-select-trigger::after{content:'⌄';float:right;color:var(--deep);font-size:1rem}.clean-select-menu{position:absolute;z-index:6;top:calc(100% + 7px);left:0;width:100%;max-height:min(290px,calc(100dvh - 160px));padding:6px;overflow-y:auto;overscroll-behavior:contain;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 14px 30px rgba(41,29,18,.16);scrollbar-color:var(--gold) transparent;scrollbar-width:thin}.clean-select-menu[hidden]{display:none}.clean-select-option{display:block;width:100%;padding:10px 11px;border:0;border-radius:5px;background:#fff;color:var(--ink);font:600 .82rem 'DM Sans';text-align:left;cursor:pointer}.clean-select-option:hover,.clean-select-option.selected{background:#fff0d8;color:var(--deep)}</style>
<style>.stay-party-details{padding:15px;border:1px solid var(--line);border-radius:9px;background:#fff}.stay-party-details>strong{display:block;margin-bottom:3px;font-size:.92rem}.stay-party-details>small{display:block;color:var(--muted);font-size:.75rem;line-height:1.4}.party-detail-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:13px}.party-capacity{min-height:42px;padding:10px 12px;border:1px solid var(--line);border-radius:6px;background:var(--sand);font-size:.86rem}.party-capacity small{display:block;margin-top:2px;color:var(--muted);font-size:.7rem}.dark-mode .stay-party-details{background:#211a15;border-color:var(--line);color:var(--ink)}.dark-mode .stay-party-details>strong{color:var(--ink)}.dark-mode .stay-party-details>small,.dark-mode .party-capacity small{color:var(--muted)}.dark-mode .party-capacity{background:#30251f;border-color:var(--line);color:var(--ink)}.dark-mode .booking-form label.checkbox:has(input:checked){background:#3a2c22;border-color:var(--gold);color:var(--ink)}.dark-mode .booking-form label.checkbox:has(input:checked) small{color:#ddc9b6}.dark-mode .booking-form label.checkbox:has(input:checked) a{color:#ffd58b}@media(max-width:520px){.party-detail-fields{grid-template-columns:1fr;gap:10px}}</style>
<style>.booking-type-selector{padding:16px;border:1px solid var(--line);border-radius:9px;background:#fff}.booking-type-label{display:block;margin-bottom:10px;color:var(--muted);font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.booking-type-options{display:grid;grid-template-columns:1fr 1fr;gap:10px}.booking-type-options a{display:grid;gap:4px;padding:13px 14px;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--ink);transition:background .18s ease,border-color .18s ease,color .18s ease}.booking-type-options a:hover{border-color:var(--gold);background:#fff8ee}.booking-type-options strong{font-size:.92rem}.booking-type-options small{color:var(--muted);font-size:.75rem;line-height:1.35}.booking-type-options a.active{border-color:var(--gold);background:var(--gold);color:#fff}.booking-type-options a.active small{color:#fff6e7}@media(max-width:520px){.booking-type-options{grid-template-columns:1fr}}</style>
<style>.hourly-calendar-day.booked{background:#f8dcd9;color:#8f332d;cursor:pointer}.hourly-calendar-day.booked:disabled{color:#8f332d;opacity:1}</style>
<style>.hourly-time-option.booked,.hourly-time-option.booked:disabled{background:#f8dcd9;color:#8f332d;cursor:not-allowed;opacity:1}.hourly-time-option.booked::after{content:'Occupied';font-size:.54rem}.dark-mode .hourly-time-option.booked,.dark-mode .hourly-time-option.booked:disabled{background:#492522;color:#ffc1b9}</style>
<style>.hourly-booked-summary{grid-column:1/-1;padding:12px 14px;border:1px solid #efc4be;border-radius:8px;background:#fff3f1;color:#762e28;font-size:.9rem;line-height:1.5}.hourly-booked-summary strong{display:block;margin-bottom:5px;font-size:.96rem}.hourly-booked-summary p{margin:0}.hourly-booked-summary ul{display:flex;flex-wrap:wrap;gap:6px;margin:0;padding:0;list-style:none}.hourly-booked-summary li{padding:5px 9px;border:1px solid #efc4be;border-radius:999px;background:#fff;color:#762e28;font-weight:700}.hourly-booked-summary.is-clear{border-color:var(--line);background:var(--sand);color:var(--muted)}.hourly-booked-summary.is-clear li{border-color:var(--line);background:transparent;color:var(--ink);font-weight:500}.dark-mode .hourly-booked-summary{border-color:#653a33;background:#382320;color:#ffd5ce}.dark-mode .hourly-booked-summary li{border-color:#70443c;background:#492522;color:#ffd5ce}.dark-mode .hourly-booked-summary.is-clear{border-color:var(--line);background:#211a15;color:var(--muted)}.dark-mode .hourly-booked-summary.is-clear li{background:transparent;color:var(--ink)}.hourly-calendar-hint{display:block;margin-top:9px;color:var(--muted);font-size:.68rem;line-height:1.4}</style>
<style>#booking-entry-fields{display:grid;gap:12px}.availability-calendar[hidden]{display:none!important}.calendar-controls .calendar-close{font-size:1rem;line-height:1}</style>
<style>.promo-entry{display:block;padding:0;border:1px solid var(--line);border-radius:9px;background:var(--paper)}.promo-entry>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:46px;padding:11px 14px;color:var(--ink);font-weight:700;cursor:pointer;list-style:none}.promo-entry>summary::-webkit-details-marker{display:none}.promo-entry>summary::after{content:'＋';color:var(--deep);font-size:1.05rem}.promo-entry[open]>summary::after{content:'−'}.promo-entry>summary span{margin-left:auto;color:var(--muted);font-size:.76rem;font-weight:500}.promo-entry-body{display:grid;gap:10px;padding:0 14px 14px}.promo-entry-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}.promo-entry-row input{min-width:0;text-transform:uppercase}.promo-entry-row button{padding:0 16px;border:0;border-radius:7px;background:var(--gold);color:#fff;font-weight:800;cursor:pointer}.promo-message{margin:0;color:var(--muted);font-size:.78rem;line-height:1.45}.promo-message.success{color:#1f7a42;font-weight:700}.promo-message.error{color:#a23d2e;font-weight:700}.promo-offer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:7px;background:var(--sand)}.promo-offer b{color:var(--deep)}.promo-offer small{color:var(--muted)}.promo-offer code{color:var(--orange-dark);font-weight:800}.dark-mode .promo-entry{background:#30251f;border-color:#76552e}.dark-mode .promo-entry>summary{color:var(--ink)}.dark-mode .promo-offer{background:#211a15}@media(max-width:520px){.promo-entry-row{grid-template-columns:minmax(0,1fr) auto}.promo-entry-row button{min-height:42px}.promo-offer{align-items:flex-start;flex-direction:column}}</style>
<style>
.booking-validation-summary{margin:0 0 16px;padding:14px 16px;border:1px solid #d98c82;border-radius:10px;background:#fff1ef;color:#6e2d27;line-height:1.45}
.booking-validation-summary strong{display:block;margin-bottom:6px}
.booking-validation-summary ul{margin:0;padding-left:20px}
.booking-field-error{display:block;margin-top:5px;color:#a02d27;font-size:.76rem;font-weight:650;line-height:1.4}
.booking-form [aria-invalid="true"]{border-color:#b93832!important;box-shadow:0 0 0 2px rgba(185,56,50,.14)}
.dark-mode .booking-validation-summary{border-color:#74413c;background:#3b2421;color:#ffd8d2}
.dark-mode .booking-field-error{color:#ffb8ae}
</style>
<style>
@media(max-width:800px){.booking-page{width:min(calc(100% - 28px),680px);grid-template-columns:minmax(0,1fr);grid-template-areas:"heading" "summary" "form";gap:12px;margin:28px auto}.booking-page .booking-summary{position:relative;top:auto;display:grid;grid-template-columns:108px minmax(0,1fr);align-items:center;margin:0;padding:10px;border-radius:10px}.booking-summary-head{grid-column:1/-1;padding:0 0 9px}.booking-summary-image{grid-column:1;grid-row:2 / span 2;width:108px;height:88px;margin:0}.booking-summary img{width:100%;height:100%;object-fit:cover}.booking-summary-image::after,.booking-summary-image-label{display:none}.booking-summary-content{grid-column:2;grid-row:2;padding:0 0 0 12px}.booking-summary h3{font-size:1.08rem}.booking-summary-content>p{margin-top:3px;font-size:.78rem}.booking-summary-rate{margin-top:5px;padding:5px 0 0}.booking-summary-rate-label{font-size:.7rem}.booking-summary-rate strong{font-size:.9rem}.booking-summary-total{grid-column:1/-1;margin:9px 0 0;padding:9px 11px}.booking-summary-total strong{font-size:1rem}}
@media(max-width:520px){.booking-page{width:calc(100% - 24px);grid-template-columns:minmax(0,1fr);grid-template-areas:"heading" "summary" "form";gap:12px;margin:22px auto}.booking-page-heading h1{font-size:clamp(2.3rem,10vw,3.3rem)}.booking-page .booking-form{padding:0}.booking-page .booking-summary{grid-template-columns:88px minmax(0,1fr);padding:9px}.booking-summary-head{padding-bottom:7px}.booking-summary-image{width:88px;height:78px}.booking-summary-content{padding-left:10px}.booking-summary-content>p{font-size:.72rem}.booking-summary-rate{align-items:center;flex-direction:row;gap:4px}.booking-summary-rate strong{text-align:right}.booking-summary-total{padding:8px 10px}.booking-summary-total small{font-size:.7rem}.stay-date-fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.availability-calendar{padding:12px}.party-detail-fields{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.party-capacity{padding:9px 8px;font-size:.78rem}.booking-type-selector{padding:12px}.booking-type-options{grid-template-columns:1fr 1fr;gap:8px}.booking-type-options a{padding:10px}.booking-type-options strong{font-size:.82rem}.booking-type-options small{font-size:.68rem}.booking-contact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.booking-contact-fields>label:first-child{grid-column:1/-1}.promo-entry-row{grid-template-columns:minmax(0,1fr) auto}.promo-entry-row button{min-height:42px}.booking-page .booking-form form{gap:12px}}
@media(max-width:360px){.stay-date-fields,.booking-contact-fields,.party-detail-fields{grid-template-columns:1fr}.booking-contact-fields>label:first-child{grid-column:auto}.booking-type-options{grid-template-columns:1fr}.booking-page .booking-summary{grid-template-columns:76px minmax(0,1fr)}.booking-summary-image{width:76px;height:72px}}
.checkout-choice{display:flex;width:max-content;max-width:100%;gap:4px;margin:0 0 12px;padding:4px;border:1px solid var(--line);border-radius:9px;background:var(--sand)}
.checkout-choice a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:7px 12px;border:1px solid transparent;border-radius:6px;color:var(--muted);font-size:.82rem;font-weight:700}
.checkout-choice a.active{border-color:var(--line);background:var(--paper);color:var(--ink);box-shadow:0 2px 6px rgba(45,37,31,.06)}
.booking-contact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.booking-contact-fields>label:first-child{grid-column:1/-1}
#booking-submit.button{color:#f5f5f0}
.booking-step-actions #booking-submit{flex:1;min-height:48px;justify-content:center}
.dark-mode .checkout-choice{border-color:var(--line);background:#211a15}
.dark-mode .checkout-choice a.active{border-color:var(--line);background:#30251f;color:var(--ink);box-shadow:none}
@media(max-width:800px){.checkout-choice{width:100%}.checkout-choice a{flex:1}}
@media(max-width:520px){.booking-contact-fields{gap:10px}}
@media(max-width:360px){.booking-contact-fields{grid-template-columns:1fr}.booking-contact-fields>label:first-child{grid-column:auto}}
</style>
<style>
.stay-date-fields input[readonly]{width:100%;min-width:0;min-height:54px;padding:14px;border-radius:8px;font-size:.95rem;font-weight:650}
.booking-type-selector{padding:12px}
.booking-type-options a{padding:11px 12px}
.guest-contact-section{padding:18px;border:1px solid #e8d6bf;border-radius:12px;background:#fffaf2;box-shadow:0 8px 20px rgba(76,53,31,.05)}
.guest-contact-section h3{margin:0 0 14px;color:var(--ink);font-size:1.15rem}
.booking-contact-fields input{min-height:46px;background:#fffdf9;border-color:#d8c5ab}
.dark-mode .guest-contact-section{border-color:#76552e;background:#211a15;box-shadow:none}
.dark-mode .booking-contact-fields input{border-color:var(--line);background:#30251f;color:var(--ink)}
.dark-mode .booking-contact-fields input::placeholder{color:#c8b5a0;opacity:1}
@media(max-width:520px){.stay-date-fields input[readonly]{min-height:54px}.booking-type-selector{padding:10px}.booking-type-options a{padding:8px}.guest-contact-section{padding:14px}}
</style>
<style>
    .promo-entry>summary { background:var(--auth-canvas); border-radius:8px; transition:background-color .16s ease; }
    .promo-entry[open] { background:var(--auth-canvas); }
    .promo-entry[open]>summary { border-radius:8px 8px 0 0; }
    .promo-entry>summary:hover { background:var(--surface); }
    .promo-entry>summary:focus-visible { outline:3px solid var(--gold); outline-offset:2px; }
    .dark-mode .promo-entry>summary { background:var(--deep); }
    .dark-mode .promo-entry[open] { background:var(--deep); }
    .dark-mode .promo-entry>summary::after { color:#FFE6C2; }
    .dark-mode .promo-entry>summary:hover { background:#63300D; }
</style>
<section class="booking-page">
    <header class="booking-page-heading">
        <x-site-back-link :href="route('booking.start', $bookingSearch)" label="Back to previous page" :history="true" class="booking-heading-back" />
        <span class="eyebrow">RESERVATION DETAILS</span>
        <h1>Complete your booking.</h1>
        <p>Choose your stay details and guest information.</p>
    </header>
    <div class="booking-form">
        @guest
            @if(!$isGuest)
            <div class="checkout-choice">
                <a class="{{ !$isGuest ? 'active' : '' }}" href="{{ route('login', ['room' => $room->slug] + $bookingSearch) }}">Sign in</a>
                <a class="{{ $isGuest ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1] + $bookingSearch) }}">Continue as guest</a>
            </div>
                <p>Sign in to book with your account, or continue as a guest without creating one.</p>
                <a class="button" href="{{ route('bookings.create', ['room' => $room, 'guest' => 1] + $bookingSearch) }}">Continue as guest</a>
            @endif
        @endguest

        @auth
            <p>Book using your Carolina account.</p>
        @endauth

        @if(auth()->check() || $isGuest)
            @if($selectionNotice)<p class="alert" role="status">{{ $selectionNotice }}</p>@endif
            <form method="POST" action="{{ route('bookings.store', $room) }}" @if($isGuest) data-guest-draft-url="{{ route('bookings.guest-draft.save') }}" @endif>
                @csrf
                <section class="booking-validation-summary" id="booking-validation-summary" role="alert" tabindex="-1" aria-labelledby="booking-validation-title" @if(!$errors->any()) hidden @endif>
                    <strong id="booking-validation-title">Please correct these details before continuing.</strong>
                    <ul>@foreach($errors->getMessages() as $field => $messages)@foreach($messages as $message)<li data-validation-field="{{ $field }}">{{ $message }}</li>@endforeach @endforeach</ul>
                </section>
                <input type="hidden" name="checkout_type" value="{{ $isGuest ? 'guest' : 'account' }}">
                <input type="hidden" name="booking_type" value="{{ $bookingMode === 'hourly' ? 'hourly' : 'dates' }}">
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                <div id="booking-entry-fields">
                @if(!$room->rental_hours)
                <section class="booking-type-selector" aria-label="Choose booking type">
                    <span class="booking-type-label">Choose your stay type</span>
                    <div class="booking-type-options">
                        <a class="{{ $bookingMode !== 'hourly' ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => $isGuest ? 1 : null, 'mode' => 'dates'] + $bookingSearch) }}"><strong>Book by dates</strong><small>Overnight or multi-day stays</small></a>
                        <a class="{{ $bookingMode === 'hourly' ? 'active' : '' }}" href="{{ route('bookings.create', ['room' => $room, 'guest' => $isGuest ? 1 : null, 'mode' => 'hourly'] + $bookingSearch) }}"><strong>Rent by hours</strong><small>Short stays for 3, 12, or 24 hours</small></a>
                    </div>
                </section>
                @endif

                @if($bookingMode === 'hourly')
                    <div class="hourly-booked-summary {{ count($hourlyBookedWindows) ? '' : 'is-clear' }}" id="hourly-booked-summary" role="status" aria-live="polite"><strong>Booked times on {{ \Carbon\Carbon::parse($hourlyDate)->format('M j') }}</strong>@if(count($hourlyBookedWindows))<ul>@foreach($hourlyBookedWindows as $window)<li>{{ $window }}</li>@endforeach</ul>@else<p>No existing reservations block this date.</p>@endif</div>
                    <div class="hourly-rental"><p><strong>{{ $room->rental_hours ? 'Fixed short-stay package' : 'Short stay rental' }}</strong><br>@if($room->rental_hours) This room is offered as a {{ $room->rental_hours }}-hour stay for ₱{{ number_format($room->price_per_night) }}.@else Rate: ₱{{ number_format($room->price_per_night / 24, 2) }} per hour.@endif</p><label class="clean-select">Duration<input id="hourly-hours" name="hours" type="hidden" value="{{ old('hours', $selectedHours) }}">@if($room->rental_hours)<span class="selector-display">{{ $room->rental_hours }} hours</span>@else<button type="button" class="clean-select-trigger" data-select-trigger>3 hours</button><section class="clean-select-menu" hidden>@foreach([3,6,12,24,48,72,96,120,168,720] as $hours)<button type="button" class="clean-select-option" data-select-value="{{ $hours }}">{{ $hours }} hours</button>@endforeach</section>@endif</label><label class="hourly-date-field">Check-in date<input id="hourly-date" name="hourly_date" type="hidden" value="{{ $hourlyDate }}" required><button id="hourly-date-trigger" class="hourly-date-trigger" type="button"></button><section class="hourly-calendar" id="hourly-calendar" hidden aria-label="Choose check-in date"><div class="hourly-calendar-head"><button type="button" id="hourly-calendar-prev" aria-label="Previous month">‹</button><b id="hourly-calendar-month"></b><button type="button" id="hourly-calendar-next" aria-label="Next month">›</button></div><div class="hourly-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="hourly-calendar-days" id="hourly-calendar-days"></div></section></label><label class="hourly-time-field">Check-in time<input id="hourly-time" name="check_in_time" type="hidden" value="{{ old('check_in_time', $selectedTime) }}" required><button id="hourly-time-trigger" class="hourly-time-trigger" type="button"></button><section class="hourly-time-menu" id="hourly-time-menu" hidden aria-label="Choose check-in time"><div class="hourly-time-menu-head"><b>Choose a time</b><small>One-hour slots</small></div><p class="hourly-time-legend">Red times mean the room is occupied for your selected stay.</p><div class="hourly-time-periods"><div class="hourly-time-period"><h4>Morning</h4>@for($hour = 6; $hour < 12; $hour++)<button class="hourly-time-option" type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div><div class="hourly-time-period"><h4>Afternoon</h4>@for($hour = 12; $hour < 18; $hour++)<button class="hourly-time-option" type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div><div class="hourly-time-period"><h4>Evening</h4>@for($hour = 18; $hour < 24; $hour++)<button class="hourly-time-option" type="button" data-time="{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00">{{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}</button>@endfor</div></div></section></label><p id="hourly-total">Estimated total: ₱{{ number_format($room->rental_hours ? $room->price_per_night : ($room->price_per_night / 24) * 3, 2) }}</p></div>
                @else
                <div class="stay-date-fields">
                    <label>Check in<input id="check-in-display" type="text" placeholder="Select a date" readonly required aria-controls="availability-calendar" aria-expanded="false"><input id="check-in" name="check_in" type="hidden" value="{{ old('check_in', $bookingSearch['check_in'] ?? null) }}" required></label>
                    <label>Check out<input id="check-out-display" type="text" placeholder="Select a date" readonly required aria-controls="availability-calendar" aria-expanded="false"><input id="check-out" name="check_out" type="hidden" value="{{ old('check_out') }}" required></label>
                </div>
                <section class="availability-calendar" id="availability-calendar" hidden aria-label="Room availability calendar" data-availability-url="{{ route('rooms.availability', $room) }}">
                    <div class="calendar-heading"><div class="calendar-controls"><button type="button" id="previous-month" aria-label="Previous month">‹</button><b id="calendar-month"></b><button type="button" id="next-month" aria-label="Next month">›</button><button type="button" id="close-availability-calendar" class="calendar-close" aria-label="Close availability calendar">×</button></div></div>
                    <div class="calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                    <div class="calendar-days" id="calendar-days"></div>
                    <p class="calendar-legend"><span aria-hidden="true"></span>Red dates are occupied.</p>
                    <p class="calendar-note" id="calendar-note" aria-live="polite"></p>
                </section>
                @endif
                <noscript>
                    <section class="native-booking-fallback" aria-label="Booking details without JavaScript">
                        <strong>Choose your stay details</strong>
                        @if($bookingMode === 'hourly')
                            <label>Duration<select name="hours">@foreach($allowedHours as $hours)<option value="{{ $hours }}" @selected((int) old('hours', $selectedHours) === $hours)>{{ $hours === 720 ? '1 month' : ($hours < 24 ? $hours . ' hours' : ($hours / 24) . ' days') }}</option>@endforeach</select></label>
                            <label>Check-in date<input type="date" name="hourly_date" min="{{ $propertyToday }}" value="{{ $hourlyDate }}" required></label>
                            <label>Check-in time<input type="time" name="check_in_time" min="06:00" max="23:00" step="3600" value="{{ old('check_in_time', $selectedTime) }}" required></label>
                        @else
                            <label>Check-in date<input type="date" name="check_in" min="{{ $propertyToday }}" value="{{ old('check_in', $bookingSearch['check_in'] ?? null) }}" required></label>
                            <label>Check-out date<input type="date" name="check_out" min="{{ \Carbon\Carbon::parse($propertyToday)->addDay()->toDateString() }}" value="{{ old('check_out') }}" required></label>
                        @endif
                    </section>
                </noscript>
                <section class="stay-party-details" aria-labelledby="guest-details-title">
                    <strong id="guest-details-title">Guest details</strong>
                    <small>Total guests includes children and must stay within this room's {{ $room->guests }}-guest limit.</small>
                    <div class="party-detail-fields">
                        @if($room->guests === 1)
                            <input type="hidden" name="guests" value="1">
                            <div class="party-capacity"><b>1 guest</b><small>Room capacity</small></div>
                        @else
                            <label class="clean-select">Total guests<input name="guests" type="hidden" value="{{ old('guests', $selectedGuests) }}"><button type="button" class="clean-select-trigger" data-select-trigger>1 guest</button><section class="clean-select-menu" hidden>@for($i = 1; $i <= $room->guests; $i++)<button type="button" class="clean-select-option" data-select-value="{{ $i }}">{{ $i }} guest{{ $i > 1 ? 's' : '' }}</button>@endfor</section></label>
                        @endif
                        <label class="clean-select">Children<input name="children_count" type="hidden" value="{{ old('children_count', 0) }}"><button type="button" class="clean-select-trigger" data-select-trigger>None</button><section class="clean-select-menu" hidden>@for($i = 0; $i <= min(10, $room->guests); $i++)<button type="button" class="clean-select-option" data-select-value="{{ $i }}">{{ $i ? $i . ' child' . ($i > 1 ? 'ren' : '') : 'None' }}</button>@endfor</section></label>
                        <label class="clean-select">Pets<input name="pets_count" type="hidden" value="{{ old('pets_count', 0) }}"><button type="button" class="clean-select-trigger" data-select-trigger>None</button><section class="clean-select-menu" hidden>@for($i = 0; $i <= 5; $i++)<button type="button" class="clean-select-option" data-select-value="{{ $i }}">{{ $i ? $i . ' pet' . ($i > 1 ? 's' : '') : 'None' }}</button>@endfor</section></label>
                    </div>
                </section>

                @if($isGuest)
                    <section class="guest-contact-section" aria-labelledby="guest-contact-heading">
                    <h3 id="guest-contact-heading">Guest contact information</h3>
                    <div class="booking-contact-fields">
                    <label>Full name<input id="guest-name" name="guest_name" value="{{ old('guest_name', $guestDraft['guest_name'] ?? '') }}" placeholder="e.g., Maria Santos" autocomplete="name" maxlength="255" required @error('guest_name') aria-invalid="true" aria-describedby="guest-name-error" @enderror>@error('guest_name')<small class="booking-field-error" id="guest-name-error">{{ $message }}</small>@enderror</label>
                    <label>Email address<input id="guest-email" type="email" name="guest_email" value="{{ old('guest_email', $guestDraft['guest_email'] ?? '') }}" placeholder="e.g., maria@example.com" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required @error('guest_email') aria-invalid="true" aria-describedby="guest-email-error" @enderror>@error('guest_email')<small class="booking-field-error" id="guest-email-error">{{ $message }}</small>@enderror</label>
                    <label>Phone number<input id="guest-phone" type="tel" name="guest_phone" value="{{ old('guest_phone', $guestDraft['guest_phone'] ?? '') }}" placeholder="09XX XXX XXXX" autocomplete="tel" inputmode="tel" maxlength="20" title="Enter a Philippine mobile number, such as 09XX XXX XXXX or +63 9XX XXX XXXX." required @error('guest_phone') aria-invalid="true" aria-describedby="guest-phone-error" @enderror>@error('guest_phone')<small class="booking-field-error" id="guest-phone-error">{{ $message }}</small>@enderror</label>
                    </div>
                    </section>
                @endif
                <details class="promo-entry" data-promo-quote-url="{{ route('rooms.promos.quote', $room) }}" @if(old('promo_code')) open @endif>
                    <summary>Promo code <span>Optional</span></summary>
                    <div class="promo-entry-body">
                    @foreach($roomPromos as $availablePromo)
                        <div class="promo-offer"><span><b>{{ $availablePromo->name }}</b><br><small>{{ $availablePromo->description }}</small></span><code>{{ $availablePromo->code }}</code></div>
                    @endforeach
                    <div class="promo-entry-row"><input id="promo-code" name="promo_code" value="{{ old('promo_code') }}" maxlength="40" placeholder="Enter promo code" autocomplete="off"><button id="apply-promo" type="button">Apply</button></div>
                    <p class="promo-message" id="promo-message" role="status" aria-live="polite">Choose your stay, enter a code, then tap Apply to see the discounted total.</p>
                    </div>
                </details>
                <label class="checkbox"><input name="terms_accepted" type="checkbox" value="1" @checked(old('terms_accepted')) required><span><strong>Accept reservation terms</strong><small>I understand this is a reservation request, subject to staff confirmation. Online cancellation and refund eligibility require at least 3 days before check-in. <a href="{{ route('terms') }}" target="_blank">Read terms</a>.</small></span></label>

                <label>Special request<textarea name="special_request" rows="3" placeholder="e.g., Extra pillow, if available.">{{ old('special_request') }}</textarea></label>
                <x-contact-card class="booking-contact-help" />
                </div>
                <section class="booking-review-panel" id="booking-review-panel" hidden aria-labelledby="booking-review-title">
                    <div class="booking-review-heading">
                        <span class="booking-review-kicker">CHECK BEFORE YOU CONTINUE</span>
                        <h2 id="booking-review-title">Review your reservation</h2>
                        <p>This is a summary only. Use Previous if you want to change any details.</p>
                    </div>
                    <dl class="booking-review-grid" id="booking-review-grid"></dl>
                    <p class="booking-review-note">This is not a confirmed booking. Your request will be submitted when you choose Proceed to payment. The final amount and payment choices will appear on the next page.</p>
                </section>
                <div id="booking-availability-status" class="booking-availability-status" role="status" aria-live="polite">Choose your stay dates to check availability. Unpaid requests hold their slot for {{ \App\Models\Booking::holdMinutes() }} minutes; paid or confirmed reservations remain reserved.</div>
                <div class="booking-step-actions">
                    <button class="button button-outline" id="booking-previous" type="button" hidden>Previous</button>
                    <button class="button" id="booking-submit" type="submit">Continue to review</button>
                </div>
            </form>
        @endif
    </div>

    <aside class="booking-summary" aria-label="Selected room summary">
        <div class="booking-summary-head">
            <span class="booking-summary-kicker">Your selected room</span>
        </div>
        <div class="booking-summary-image">
            <a class="booking-summary-image-link" href="{{ route('rooms.show', $room) }}" aria-label="View {{ $room->name }} photos and details">
                <picture>
                    @if($roomWebpSrcset = $room->responsiveWebpSrcset())<source type="image/webp" srcset="{{ $roomWebpSrcset }}" sizes="(max-width: 520px) 88px, (max-width: 800px) 108px, 380px">@endif
                    @if($roomSrcset = $room->responsiveImageSrcset())<img src="{{ $room->image_url }}" srcset="{{ $roomSrcset }}" sizes="(max-width: 520px) 88px, (max-width: 800px) 108px, 380px" alt="{{ $room->name }} room photo" fetchpriority="high" decoding="async">@else<img src="{{ $room->image_url }}" alt="{{ $room->name }} room photo" fetchpriority="high" decoding="async">@endif
                </picture>
                <span class="booking-summary-image-label">View room photos <span aria-hidden="true">↗</span></span>
            </a>
        </div>
        <div class="booking-summary-content">
            <h3>{{ $room->name }}</h3>
            <p>{{ $room->room_type }} · Up to {{ $room->guests }} guests</p>
            <div class="booking-summary-rate">
                <span class="booking-summary-rate-label">Room rate</span>
                @if($bookingMode === 'hourly')
                    <strong>₱{{ number_format($room->rental_hours ? $room->price_per_night : $room->price_per_night / 24, 2) }} <small>{{ $room->rental_hours ? $room->rate_label : '/ hour' }}</small></strong>
                @else
                    <strong>₱{{ number_format($room->price_per_night) }} <small>{{ $room->rate_label }}</small></strong>
                @endif
            </div>
        </div>
        <div class="booking-summary-total" aria-live="polite">
            <span>Estimated total</span>
            <strong id="booking-summary-total" data-night-rate="{{ $room->price_per_night }}" data-hourly-rate="{{ $room->price_per_night / 24 }}" data-package-rate="{{ $room->rental_hours ? $room->price_per_night : '' }}" data-package-hours="{{ $room->rental_hours ?: '' }}">Choose dates</strong>
            <small id="booking-summary-period">Choose your stay to see an estimate.</small>
        </div>
    </aside>
</section>
@if(auth()->check() || $isGuest)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const closeOpenPickers = (except = null) => document.querySelectorAll('.clean-select-menu, #hourly-calendar, #hourly-time-menu').forEach(menu => { if (menu !== except) menu.hidden = true; });
    let hourlyBlockedSlots = @json($hourlyBlockedSlots);
    document.querySelectorAll('.clean-select').forEach(field => { const input = field.querySelector('input[type="hidden"]'), trigger = field.querySelector('[data-select-trigger]'), menu = field.querySelector('.clean-select-menu'); if (!trigger || !menu || !input) return; const select = value => { const option = menu.querySelector(`[data-select-value="${value}"]`); input.value = value; trigger.textContent = option?.textContent ?? value; menu.querySelectorAll('.clean-select-option').forEach(button => button.classList.toggle('selected', button.dataset.selectValue === value)); }; select(input.value); trigger.addEventListener('click', () => { const opening = menu.hidden; closeOpenPickers(menu); menu.hidden = !opening; }); menu.querySelectorAll('.clean-select-option').forEach(button => button.addEventListener('click', () => { select(button.dataset.selectValue); menu.hidden = true; input.dispatchEvent(new Event('change')); })); });
    const hourlyHours = document.getElementById('hourly-hours');
    if (hourlyHours) {
        const total = document.getElementById('hourly-total'); const packageRate = {{ $room->rental_hours ? $room->price_per_night : 'null' }}; const packageHours = {{ $room->rental_hours ?: 'null' }}; const rate = {{ $room->price_per_night / 24 }};
        if (packageRate) { const field = hourlyHours.closest('.clean-select'), display = field.querySelector('.selector-display'), options = [[packageHours, `${packageHours} hours`], [48, '2 days'], [72, '3 days'], [96, '4 days'], [120, '5 days'], [168, '1 week'], [720, '1 month']]; if (display) { const trigger = document.createElement('button'), menu = document.createElement('section'); trigger.type = 'button'; trigger.className = 'clean-select-trigger'; menu.className = 'clean-select-menu'; menu.hidden = true; options.forEach(([value, label]) => { const option = document.createElement('button'); option.type = 'button'; option.className = 'clean-select-option'; option.dataset.selectValue = value; option.textContent = label; menu.append(option); }); const select = value => { hourlyHours.value = value; trigger.textContent = options.find(([option]) => Number(option) === Number(value))?.[1] ?? `${value} hours`; menu.querySelectorAll('button').forEach(option => option.classList.toggle('selected', Number(option.dataset.selectValue) === Number(value))); hourlyHours.dispatchEvent(new Event('change')); }; display.replaceWith(trigger); field.append(menu); select(hourlyHours.value); trigger.addEventListener('click', () => { const opening = menu.hidden; closeOpenPickers(menu); menu.hidden = !opening; }); menu.querySelectorAll('button').forEach(option => option.addEventListener('click', () => { select(option.dataset.selectValue); menu.hidden = true; })); } }
        const refresh = () => { const hours = Number(hourlyHours.value); const multiplier = ({48:2,72:3,96:4,120:5,168:7,720:30})[hours] ?? 1; total.textContent = `Estimated total: ₱${(packageRate ? packageRate * multiplier : rate * hours).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`; };
        hourlyHours.addEventListener('change', refresh); refresh();
        const value = document.getElementById('hourly-date'), trigger = document.getElementById('hourly-date-trigger'), picker = document.getElementById('hourly-calendar'), days = document.getElementById('hourly-calendar-days'), month = document.getElementById('hourly-calendar-month');
        const propertyClock = () => { const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date()); const part = type => parts.find(item => item.type === type).value; return { date: `${part('year')}-${part('month')}-${part('day')}`, time: `${part('hour')}:${part('minute')}` }; };
        let propertyNow = propertyClock(), todayValue = propertyNow.date;
        let today = new Date(`${todayValue}T00:00:00`); today.setHours(0, 0, 0, 0); let cursor = new Date(today.getFullYear(), today.getMonth(), 1);
        const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const pretty = date => date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        const bookedSummary = document.getElementById('hourly-booked-summary');
        const bookedWindowsForDate = dateValue => {
            const dayStart = dateAtBookingTime(dateValue, '00:00').getTime(), dayEnd = dayStart + 86400000;
            const formatTime = timestamp => new Intl.DateTimeFormat('en-PH', { timeZone: 'UTC', hour: 'numeric', minute: '2-digit' }).format(new Date(timestamp)).replace(':00', '');
            return [...new Set(hourlyBlockedSlots.flatMap(slot => {
                const start = Date.parse(slot.start), end = Date.parse(slot.end), clippedStart = Math.max(start, dayStart), clippedEnd = Math.min(end, dayEnd);
                if (clippedStart >= clippedEnd) return [];
                return [clippedStart === dayStart && clippedEnd === dayEnd ? 'All day' : `${formatTime(clippedStart)} – ${formatTime(clippedEnd)}`];
            }))];
        };
        const renderBookedSummary = () => {
            if (!bookedSummary) return;
            const windows = value.value ? bookedWindowsForDate(value.value) : [];
            const heading = document.createElement('strong');
            heading.textContent = value.value ? `Booked times on ${pretty(new Date(`${value.value}T00:00:00`))}` : 'Choose a check-in date';
            bookedSummary.classList.toggle('is-clear', windows.length === 0);
            bookedSummary.replaceChildren(heading);
            if (windows.length) {
                const list = document.createElement('ul');
                windows.forEach(window => { const item = document.createElement('li'); item.textContent = window; list.append(item); });
                bookedSummary.append(list);
            } else {
                const copy = document.createElement('p');
                copy.textContent = 'No existing reservations block this date.';
                bookedSummary.append(copy);
            }
            if (value.value && !hasAvailableStartTime(value.value)) {
                const warning = document.createElement('p');
                warning.textContent = 'No check-in times are available for this date and stay length. Choose another date or a shorter stay.';
                bookedSummary.append(warning);
            }
        };
        const candidateTimes = [...document.querySelectorAll('#hourly-time-menu [data-time]')];
        const dateAtBookingTime = (dateValue, time) => {
            const [year, month, day] = dateValue.split('-').map(Number);
            const [hour, minute] = time.split(':').map(Number);
            return new Date(Date.UTC(year, month - 1, day, hour, minute));
        };
        const isTimeBooked = (dateValue, time, duration) => { const start = dateAtBookingTime(dateValue, time), end = new Date(start.getTime() + duration * 60 * 60 * 1000); return hourlyBlockedSlots.some(slot => start < new Date(slot.end) && end > new Date(slot.start)); };
        const hasAvailableStartTime = dateValue => candidateTimes.some(button => {
            const past = dateValue < todayValue || (dateValue === todayValue && button.dataset.time <= propertyNow.time);
            return !past && !isTimeBooked(dateValue, button.dataset.time, Number(hourlyHours.value));
        });
        const renderHourlyCalendar = () => {
            days.innerHTML = '';
            month.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1), last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
            for (let i = 0; i < first.getDay(); i++) days.insertAdjacentHTML('beforeend', '<span class="hourly-calendar-blank"></span>');
            for (let day = 1; day <= last.getDate(); day++) {
                const date = new Date(cursor.getFullYear(), cursor.getMonth(), day), dateValue = iso(date);
                const dayStart = dateAtBookingTime(dateValue, '00:00'), dayEnd = dateAtBookingTime(iso(new Date(date.getFullYear(), date.getMonth(), day + 1)), '00:00');
                const booked = hourlyBlockedSlots.some(slot => dayStart < new Date(slot.end) && dayEnd > new Date(slot.start));
                const noAvailableTime = !hasAvailableStartTime(dateValue), disabled = dateValue < todayValue || noAvailableTime;
                const title = noAvailableTime ? 'No check-in time fits this stay length' : booked ? 'Some times unavailable — select the date to see available hours' : '';
                days.insertAdjacentHTML('beforeend', `<button class="hourly-calendar-day${booked ? ' booked' : ''}${dateValue === value.value ? ' selected' : ''}" type="button" data-date="${dateValue}" ${disabled ? 'disabled' : ''} ${title ? `title="${title}"` : ''}>${day}</button>`);
            }
            days.querySelectorAll('.hourly-calendar-day:not([disabled])').forEach(button => button.addEventListener('click', () => {
                value.value = button.dataset.date;
                value.dispatchEvent(new Event('change', { bubbles: true }));
                trigger.textContent = pretty(new Date(`${value.value}T00:00:00`));
                picker.hidden = true;
                syncTimeOptions();
            }));
        };
        trigger.textContent = value.value ? pretty(new Date(`${value.value}T00:00:00`)) : 'Choose date'; trigger.addEventListener('click', () => { const opening = picker.hidden; closeOpenPickers(picker); picker.hidden = !opening; if (!picker.hidden) renderHourlyCalendar(); }); document.getElementById('hourly-calendar-prev').addEventListener('click', () => { const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; renderHourlyCalendar(); } }); document.getElementById('hourly-calendar-next').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); renderHourlyCalendar(); });
        const timeValue = document.getElementById('hourly-time'), timeTrigger = document.getElementById('hourly-time-trigger'), timeMenu = document.getElementById('hourly-time-menu');
        if (!/^(0[6-9]|1[0-9]|2[0-3]):00$/.test(timeValue.value)) timeValue.value = '06:00';
        const prettyTime = time => new Date(`2000-01-01T${time}`).toLocaleTimeString('en-PH', { hour: 'numeric', hour12: true });
        const conflictingSlots = (date, time, duration) => {
            const start = dateAtBookingTime(date, time);
            const end = new Date(start.getTime() + duration * 60 * 60 * 1000);
            return hourlyBlockedSlots.filter(slot => start < new Date(slot.end) && end > new Date(slot.start));
        };
        const describeSlot = slot => {
            const start = new Date(slot.start), end = new Date(slot.end);
            const localClock = { timeZone: 'UTC', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
            const startLabel = start.toLocaleString('en-PH', localClock);
            const endLabel = end.toLocaleString('en-PH', localClock);
            return `${startLabel}–${endLabel}`;
        };
        const updatePropertyClock = () => {
            propertyNow = propertyClock();
            if (propertyNow.date !== todayValue) {
                todayValue = propertyNow.date;
                today = new Date(`${todayValue}T00:00:00`); today.setHours(0, 0, 0, 0);
                if (cursor < new Date(today.getFullYear(), today.getMonth(), 1)) cursor = new Date(today.getFullYear(), today.getMonth(), 1);
                if (value.value && value.value < todayValue) { value.value = ''; trigger.textContent = 'Choose date'; }
            }
            return propertyNow;
        };
        const syncTimeOptions = () => {
            updatePropertyClock();
            const selectedDate = value.value, duration = Number(hourlyHours.value);
            timeMenu.querySelectorAll('[data-time]').forEach(button => {
                const conflicts = conflictingSlots(selectedDate, button.dataset.time, duration);
                const booked = conflicts.length > 0;
                const past = selectedDate < todayValue || (selectedDate === todayValue && button.dataset.time <= propertyNow.time);
                button.disabled = booked || past;
                button.dataset.statusLabel = booked ? 'Overlaps stay' : past ? 'Time passed' : '';
                button.title = booked ? `Unavailable — overlaps ${conflicts.map(describeSlot).join('; ')}` : past ? 'This check-in time has already passed.' : '';
                button.setAttribute('aria-label', booked ? `${button.textContent.trim()}, unavailable because the stay overlaps ${conflicts.map(describeSlot).join('; ')}` : past ? `${button.textContent.trim()}, unavailable because this time has passed` : button.textContent.trim());
                button.classList.toggle('booked', booked);
                button.classList.toggle('selected', !button.disabled && button.dataset.time === timeValue.value);
            });
            if (timeValue.value && timeMenu.querySelector(`[data-time="${timeValue.value}"]`)?.disabled) {
                timeValue.value = '';
                timeTrigger.textContent = 'Choose time';
            }
            if (!picker.hidden) renderHourlyCalendar();
            renderBookedSummary();
        };
        hourlyHours.addEventListener('change', syncTimeOptions);
        timeTrigger.textContent = prettyTime(timeValue.value);
        timeTrigger.addEventListener('click', () => {
            const opening = timeMenu.hidden;
            closeOpenPickers(timeMenu);
            timeMenu.hidden = !opening;
            if (opening) {
                syncTimeOptions();
                refreshHourlyAvailability();
            }
        });
        timeMenu.querySelectorAll('[data-time]').forEach(button => button.addEventListener('click', () => {
            timeValue.value = button.dataset.time;
            timeValue.dispatchEvent(new Event('change', { bubbles: true }));
            timeTrigger.textContent = prettyTime(timeValue.value);
            syncTimeOptions();
            timeMenu.hidden = true;
        }));
        const refreshHourlyAvailability = async () => {
            syncTimeOptions();
            try {
                const response = await fetch(@json(route('rooms.availability', $room)), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                hourlyBlockedSlots = data.slots ?? [];
                if (!picker.hidden) renderHourlyCalendar();
                syncTimeOptions();
                if (timeMenu.querySelector(`[data-time="${timeValue.value}"]`)?.disabled) {
                    timeValue.value = '';
                    timeTrigger.textContent = 'Choose time';
                }
            } catch (error) {
                syncTimeOptions();
                /* Keep the current availability if the connection is unavailable. */
            }
        };
        syncTimeOptions();
        setInterval(refreshHourlyAvailability, 15000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshHourlyAvailability(); });
        return;
    }
    const ranges = @json($blockedRanges);
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    const checkInDisplay = document.getElementById('check-in-display');
    const checkOutDisplay = document.getElementById('check-out-display');
    const availabilityCalendar = document.getElementById('availability-calendar');
    const days = document.getElementById('calendar-days');
    const monthLabel = document.getElementById('calendar-month');
    const note = document.getElementById('calendar-note');
    const propertyDate = () => { const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date()); const part = type => parts.find(item => item.type === type).value; return `${part('year')}-${part('month')}-${part('day')}`; };
    let todayValue = propertyDate();
    let today = new Date(`${todayValue}T00:00:00`); today.setHours(0, 0, 0, 0);
    let cursor = new Date(today.getFullYear(), today.getMonth(), 1);
    const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const pretty = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const occupied = (value) => ranges.some(range => value >= range.start && value < range.end);
    const overlap = (start, end) => ranges.some(range => start < range.end && end > range.start);
    const nextAvailable = () => { const date = new Date(today); while (occupied(iso(date))) date.setDate(date.getDate() + 1); return iso(date); };
    const refreshInputs = () => { checkInDisplay.value = pretty(checkIn.value); checkOutDisplay.value = pretty(checkOut.value); };
    const choose = (value) => {
        if (!checkIn.value || checkOut.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in selected. Now select your check-out date.'; }
        else if (value <= checkIn.value) { checkIn.value = value; checkOut.value = ''; note.textContent = 'Check-in updated. Now select your check-out date.'; }
        else if (overlap(checkIn.value, value)) { note.textContent = 'Those dates include an unavailable stay. Please choose dates outside the red period.'; return; }
        else { checkOut.value = value; note.textContent = 'Your selected dates are available.'; }
        refreshInputs(); render();
        if (checkIn.value && checkOut.value) setAvailabilityCalendarOpen(false);
        checkIn.dispatchEvent(new Event('change', { bubbles: true }));
        checkOut.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const render = () => {
        days.innerHTML = '';
        monthLabel.textContent = cursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const last = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
        for (let blank = 0; blank < first.getDay(); blank++) days.insertAdjacentHTML('beforeend', '<span class="calendar-blank"></span>');
        for (let number = 1; number <= last.getDate(); number++) {
            const date = new Date(cursor.getFullYear(), cursor.getMonth(), number);
            const value = iso(date); const isPast = value < todayValue; const isBooked = occupied(value);
            const previous = new Date(date); previous.setDate(previous.getDate() - 1);
            const next = new Date(date); next.setDate(next.getDate() + 1);
            const bookedRange = isBooked ? ` booked${occupied(iso(previous)) ? '' : ' booked-start'}${occupied(iso(next)) ? '' : ' booked-end'}` : '';
            const selected = value === checkIn.value ? ' selected start' : value === checkOut.value ? ' selected end' : checkIn.value && checkOut.value && value > checkIn.value && value < checkOut.value ? ' selected range' : '';
            const disabled = value < todayValue || isBooked ? ' disabled' : '';
            const state = isBooked ? bookedRange : isPast ? ' past' : '';
            days.insertAdjacentHTML('beforeend', `<button type="button" class="calendar-day${state}${selected}" data-date="${value}"${disabled}>${number}</button>`);
        }
        document.querySelectorAll('.calendar-day:not([disabled])').forEach(button => button.addEventListener('click', () => choose(button.dataset.date)));
        if (!checkIn.value && !note.textContent) note.textContent = `Next available date: ${pretty(nextAvailable())}. Select check-in, then check-out.`;
    };
    document.getElementById('previous-month').addEventListener('click', () => { const previous = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); if (previous >= new Date(today.getFullYear(), today.getMonth(), 1)) { cursor = previous; render(); } });
    document.getElementById('next-month').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
    const setAvailabilityCalendarOpen = open => {
        availabilityCalendar.hidden = !open;
        checkInDisplay.setAttribute('aria-expanded', String(open));
        checkOutDisplay.setAttribute('aria-expanded', String(open));
        if (open) availabilityCalendar.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    checkInDisplay.addEventListener('click', () => setAvailabilityCalendarOpen(true));
    checkOutDisplay.addEventListener('click', () => setAvailabilityCalendarOpen(true));
    document.getElementById('close-availability-calendar').addEventListener('click', () => setAvailabilityCalendarOpen(false));
    const updatePropertyDate = () => {
        const currentDate = propertyDate();
        const dayChanged = currentDate !== todayValue;
        let selectionChanged = false;
        if (dayChanged) {
            todayValue = currentDate;
            today = new Date(`${todayValue}T00:00:00`); today.setHours(0, 0, 0, 0);
            if (cursor < new Date(today.getFullYear(), today.getMonth(), 1)) cursor = new Date(today.getFullYear(), today.getMonth(), 1);
        }
        if (checkIn.value && checkIn.value < todayValue) {
            checkIn.value = ''; checkOut.value = '';
            note.textContent = 'The selected check-in date has passed. Please choose new dates.';
            refreshInputs();
            selectionChanged = true;
        } else if (checkOut.value && checkOut.value < todayValue) {
            checkOut.value = '';
            note.textContent = 'The selected check-out date has passed. Please choose a new check-out date.';
            refreshInputs();
            selectionChanged = true;
        }
        if (dayChanged || selectionChanged) render();
    };
    const refreshAvailability = async () => {
        updatePropertyDate();
        try {
            const response = await fetch(document.querySelector('.availability-calendar').dataset.availabilityUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            ranges.splice(0, ranges.length, ...data.ranges);
            if (checkIn.value && checkOut.value && overlap(checkIn.value, checkOut.value)) {
                checkOut.value = ''; refreshInputs(); note.textContent = 'Availability changed. Please select a new check-out date.';
            }
            render();
        } catch (error) { /* Keep the last known availability if the connection is unavailable. */ }
    };
    setInterval(refreshAvailability, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshAvailability(); });
    updatePropertyDate(); refreshInputs(); render();
});
</script>
<script src="{{ asset('js/booking-validation.js') }}"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const total = document.getElementById('booking-summary-total');
    const period = document.getElementById('booking-summary-period');
    const form = document.getElementById('booking-submit')?.form;
    if (!total || !period || !form) return;
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 });
    let quotedPromoTotal = null;
    const formatDate = value => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const update = () => {
        const data = new FormData(form), hourly = data.get('booking_type') === 'hourly';
        let amount = null, detail = 'Choose your stay to see an estimate.';
        if (hourly) {
            const hours = Number(data.get('hours') || 0), packageHours = Number(total.dataset.packageHours || 0), packageRate = Number(total.dataset.packageRate || 0);
            const multiplier = ({48:2,72:3,96:4,120:5,168:7,720:30})[hours] || 1;
            amount = packageRate ? packageRate * multiplier : Number(total.dataset.hourlyRate || 0) * hours;
            const length = hours === 720 ? '1 month' : hours >= 24 ? `${hours / 24} day${hours === 24 ? '' : 's'}` : `${hours} hours`;
            detail = `${length}${data.get('hourly_date') ? ` · ${formatDate(data.get('hourly_date'))}` : ''}${packageHours && hours === packageHours ? ' · fixed package' : ''}`;
        } else {
            const start = data.get('check_in'), end = data.get('check_out');
            if (start && end) {
                const nights = Math.round((Date.parse(`${end}T00:00:00Z`) - Date.parse(`${start}T00:00:00Z`)) / 86400000);
                if (nights > 0) {
                    amount = Number(total.dataset.nightRate || 0) * nights;
                    detail = `${nights} night${nights === 1 ? '' : 's'} · ${formatDate(start)} – ${formatDate(end)}`;
                }
            }
        }
        if (amount === null || !Number.isFinite(amount)) {
            total.textContent = 'Choose dates';
            period.textContent = detail;
            return;
        }
        total.textContent = currency.format(quotedPromoTotal ?? amount);
        period.textContent = quotedPromoTotal === null ? detail : `${detail} · promo applied`;
    };
    const refreshEstimate = event => {
        if (event.target.id === 'promo-code' || !event.target.closest('.promo-entry')) quotedPromoTotal = null;
        update();
    };
    form.addEventListener('input', refreshEstimate);
    form.addEventListener('change', refreshEstimate);
    window.addEventListener('booking:promo-quote', event => {
        const value = Number(event.detail?.total);
        quotedPromoTotal = event.detail?.total !== null && Number.isFinite(value) ? value : null;
        update();
    });
    update();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const section = document.querySelector('.promo-entry');
    if (!section) return;
    const form = section.closest('form'), input = document.getElementById('promo-code'), button = document.getElementById('apply-promo'), message = document.getElementById('promo-message');
    const money = value => Number(value).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const resetMessage = () => { message.className = 'promo-message'; message.textContent = input.value.trim() ? 'Stay details changed. Tap Apply to refresh this promo.' : 'Choose your stay, enter a code, then tap Apply to see the discounted total.'; };
    button.addEventListener('click', async () => {
        const code = input.value.trim().toUpperCase();
        input.value = code;
        if (!code) { message.className = 'promo-message error'; message.textContent = 'Enter a promo code first.'; return; }
        const formData = new FormData(form), payload = new FormData();
        ['_token', 'booking_type', 'hours', 'check_in', 'check_out'].forEach(key => { const value = formData.get(key); if (value) payload.append(key, value); });
        payload.append('promo_code', code);
        button.disabled = true; message.className = 'promo-message'; message.textContent = 'Checking promo…';
        try {
            const response = await fetch(section.dataset.promoQuoteUrl, { method: 'POST', body: payload, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.errors?.promo_code?.[0] || result.message || 'This promo could not be applied.');
            message.className = 'promo-message success';
            message.textContent = `${result.name}: ${money(result.original)} → ${money(result.total)}. You save ${money(result.discount)}. Downpayment (30% of original, up to final total): ${money(Math.min(result.original * 0.30, result.total))}.`;
            window.dispatchEvent(new CustomEvent('booking:promo-quote', { detail: { total: result.total } }));
        } catch (error) {
            message.className = 'promo-message error'; message.textContent = error.message;
        } finally { button.disabled = false; }
    });
    form.addEventListener('change', event => { if (event.target !== input && !event.target.closest('.promo-entry')) resetMessage(); });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const submit = document.getElementById('booking-submit');
    if (!submit) return;
    const form = submit.form, status = document.getElementById('booking-availability-status');
    const entryFields = document.getElementById('booking-entry-fields');
    const reviewPanel = document.getElementById('booking-review-panel');
    const reviewGrid = document.getElementById('booking-review-grid');
    const previous = document.getElementById('booking-previous');
    const guestDraftUrl = form.dataset.guestDraftUrl;
    const guestDraftFields = ['guest_name', 'guest_email', 'guest_phone'];
    let guestDraftTimer, guestDraftQueue = Promise.resolve();
    const saveGuestDraft = (keepalive = false) => {
        if (!guestDraftUrl) return Promise.resolve(false);
        clearTimeout(guestDraftTimer);
        const payload = Object.fromEntries(guestDraftFields.map(name => [name, form.elements.namedItem(name)?.value ?? '']));
        guestDraftQueue = guestDraftQueue.catch(() => false).then(() => fetch(guestDraftUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value ?? '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(response => response.ok).catch(() => false));
        return guestDraftQueue;
    };
    if (guestDraftUrl) {
        guestDraftFields.forEach(name => {
            const input = form.elements.namedItem(name);
            if (!input) return;
            input.addEventListener('input', () => {
                clearTimeout(guestDraftTimer);
                guestDraftTimer = setTimeout(() => { void saveGuestDraft(); }, 650);
            });
            input.addEventListener('change', () => { void saveGuestDraft(); });
        });
        window.addEventListener('pagehide', () => { clearTimeout(guestDraftTimer); void saveGuestDraft(true); });
    }
    let sequence = 0, allowed = false, reviewing = false, timer;
    const check = async () => {
        const run = ++sequence;
        const data = new FormData(form);
        let start, end;
        if (data.get('booking_type') === 'hourly') {
            if (!data.get('hourly_date') || !data.get('check_in_time')) { status.textContent = 'Choose a check-in date and time before continuing.'; return false; }
            start = `${data.get('hourly_date')}T${data.get('check_in_time')}:00Z`;
            end = new Date(Date.parse(start) + Number(data.get('hours')) * 3600000).toISOString();
        } else {
            if (!data.get('check_in') || !data.get('check_out')) { status.textContent = 'Choose both check-in and check-out dates before continuing.'; return false; }
            start = `${data.get('check_in')}T00:00:00Z`; end = `${data.get('check_out')}T00:00:00Z`;
        }
        status.textContent = 'Checking your selected stay…';
        try {
            const url = new URL(@json(route('rooms.availability', $room)));
            url.searchParams.set('start', start); url.searchParams.set('end', end); url.searchParams.set('booking_type', data.get('booking_type'));
            const response = await fetch(url, {headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) throw new Error('Availability unavailable');
            const result = await response.json();
            if (run !== sequence) return false;
            status.textContent = result.available ? '✓ Available now. We will reserve this time when you submit.' : result.reason === 'past' ? 'That check-in date or time has already passed. Choose a future date or time.' : 'This stay overlaps an existing reservation or room operation. Choose another date or time.';
            return result.available;
        } catch (error) {
            if (run === sequence) status.textContent = 'We could not refresh availability. Please try again.';
            return false;
        }
    };
    const dateLabel = value => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'}) : '';
    const timeLabel = value => value ? new Date(`2000-01-01T${value}`).toLocaleTimeString('en-PH', {hour:'numeric', minute:'2-digit'}) : '';
    const addReviewItem = (label, value, wide = false) => {
        if (!value) return;
        const item = document.createElement('div'); item.className = `booking-review-item${wide ? ' booking-review-item--wide' : ''}`;
        const term = document.createElement('dt'); term.textContent = label;
        const description = document.createElement('dd'); description.textContent = value;
        item.append(term, description); reviewGrid.append(item);
    };
    const buildReview = () => {
        const data = new FormData(form), hourly = data.get('booking_type') === 'hourly';
        reviewGrid.replaceChildren();
        addReviewItem('Room', @json($room->name));
        addReviewItem('Room type', @json($room->room_type));
        addReviewItem('Stay type', hourly ? 'Short stay by the hour' : 'Overnight stay');
        if (hourly) {
            addReviewItem('Check-in', `${dateLabel(data.get('hourly_date'))} · ${timeLabel(data.get('check_in_time'))}`);
            const hours = Number(data.get('hours'));
            addReviewItem('Duration', hours === 720 ? '1 month' : hours < 24 ? `${hours} hours` : `${hours / 24} day${hours === 24 ? '' : 's'}`);
        } else {
            const start = data.get('check_in'), end = data.get('check_out');
            const nights = Math.round((new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) / 86400000);
            addReviewItem('Check-in', dateLabel(start)); addReviewItem('Check-out', dateLabel(end));
            addReviewItem('Length of stay', `${nights} night${nights === 1 ? '' : 's'}`);
        }
        const guests = Number(data.get('guests') || 1), children = Number(data.get('children_count') || 0), pets = Number(data.get('pets_count') || 0);
        addReviewItem('Guests', `${guests} guest${guests === 1 ? '' : 's'}${children ? ` · ${children} child${children === 1 ? '' : 'ren'}` : ''}`);
        addReviewItem('Pets', pets ? `${pets} pet${pets === 1 ? '' : 's'}` : 'None');
        const name = data.get('guest_name') || @json(auth()->user()->name ?? '');
        const email = data.get('guest_email') || @json(auth()->user()->email ?? '');
        const phone = data.get('guest_phone');
        if (name) addReviewItem('Booked for', name);
        if (email) addReviewItem('Email', email);
        if (phone) addReviewItem('Phone', phone);
        const promo = String(data.get('promo_code') || '').trim();
        if (promo) addReviewItem('Promo code', promo);
        addReviewItem('Special request', String(data.get('special_request') || '').trim() || 'None', true);
        const estimate = document.getElementById(hourly ? 'hourly-total' : 'date-deposit-preview')?.textContent?.trim();
        if (estimate) addReviewItem('Current estimate', estimate, true);
    };
    const showEntryFields = () => {
        reviewing = false; entryFields.hidden = false; reviewPanel.hidden = true; previous.hidden = true;
        submit.textContent = 'Continue to review';
        entryFields.scrollIntoView({behavior:'smooth', block:'start'});
        entryFields.querySelector('input:not([type="hidden"]), button, a')?.focus({preventScroll:true});
    };
    form.addEventListener('click', event => { if (event.target.closest('#booking-submit, #booking-previous, #booking-review-panel')) return; clearTimeout(timer); timer = setTimeout(check, 100); });
    form.addEventListener('change', check);
    check();
    form.addEventListener('submit', async event => {
        if (allowed) return;
        event.preventDefault(); event.stopImmediatePropagation(); clearTimeout(timer); submit.disabled = true;
        if (await check()) {
            if (!reviewing) {
                buildReview(); reviewing = true; entryFields.hidden = true; reviewPanel.hidden = false; previous.hidden = false;
                submit.textContent = 'Proceed to payment';
                status.textContent = 'Availability checked. We will check again before submitting your reservation.';
                reviewPanel.scrollIntoView({behavior:'smooth', block:'start'});
                submit.disabled = false;
            } else {
                status.textContent = 'Availability confirmed. Submitting your reservation…';
                await saveGuestDraft();
                allowed = true; submit.disabled = false; form.requestSubmit(submit);
            }
        } else { submit.disabled = false; status.scrollIntoView({block:'center',behavior:'smooth'}); }
    }, true);
    previous.addEventListener('click', showEntryFields);
});
</script>
@endsection
