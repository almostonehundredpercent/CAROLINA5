<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review room rate · Carolina</title>
    <style>
        :root{color-scheme:dark;--bg:#17120f;--panel:#241b16;--border:#66471f;--ink:#fff2e3;--muted:#cbb9a6;--accent:#d88910}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:32px 16px;background:radial-gradient(ellipse at 50% 0,#382719,var(--bg) 62%);color:var(--ink);font:16px/1.55 system-ui,sans-serif}
        main{width:min(100%,680px);margin:5vh auto;padding:clamp(22px,5vw,40px);border:1px solid var(--border);border-radius:22px;background:linear-gradient(145deg,#2b211a,var(--panel));box-shadow:0 24px 60px #0006}
        .kicker{margin:0 0 8px;color:#ffbd55;font-size:.75rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}h1{margin:0;font-family:Georgia,serif;font-size:clamp(2rem,7vw,3rem);line-height:1.05}p{color:var(--muted)}dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:24px 0}dl div,.reason{padding:14px;border:1px solid #594332;border-radius:12px;background:#1b1511}dt{color:var(--muted);font-size:.8rem}dd{margin:4px 0 0;font-weight:750;overflow-wrap:anywhere}.reason{grid-column:1/-1}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}button{min-height:48px;padding:0 20px;border:1px solid var(--border);border-radius:999px;font:700 1rem system-ui;cursor:pointer}.approve{background:var(--accent);color:#21160b}.reject{background:transparent;color:var(--ink)}.notice{padding:14px;border-radius:12px;background:#32271d;color:var(--muted)}@media(max-width:480px){body{padding:18px 12px}dl{grid-template-columns:1fr}.reason{grid-column:auto}.actions{display:grid}.actions button{width:100%}}
    </style>
</head>
<body><main>
    <p class="kicker">Carolina · Owner rate approval</p>
    <h1>{{ $change->status === 'approved' ? 'Rate approved' : ($change->status === 'rejected' ? 'Rate rejected' : 'Review room rate') }}</h1>
    <p>{{ $change->status === 'requested' ? 'Review this request. Your choice is recorded with the approval reference and date.' : 'This request has already been decided. This page is read-only.' }}</p>
    <dl>
        <div><dt>Approval reference</dt><dd>{{ $change->reference }}</dd></div>
        <div><dt>Room</dt><dd>{{ $change->room->name }}</dd></div>
        <div><dt>Current rate</dt><dd>₱{{ number_format((float) $change->old_price, 2) }}</dd></div>
        <div><dt>Proposed rate</dt><dd>₱{{ number_format((float) $change->proposed_price, 2) }}</dd></div>
        <div><dt>Requested by</dt><dd>{{ $change->requester?->name ?? 'Carolina administrator' }}</dd></div>
        <div><dt>Requested</dt><dd>{{ $change->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</dd></div>
        <div class="reason"><dt>Reason for change</dt><dd>{{ $change->reason }}</dd></div>
    </dl>
    @if($change->status === 'requested')
        <form class="actions" method="POST" action="{{ $decisionUrl }}">
            @csrf
            <button class="approve" type="submit" name="decision" value="approve">Approve rate</button>
            <button class="reject" type="submit" name="decision" value="reject">Reject request</button>
        </form>
    @elseif($change->status === 'approved')
        <p class="notice">Approval recorded{{ $change->approved_at ? ' on '.$change->approved_at->timezone('Asia/Manila')->format('M j, Y g:i A').' PHT' : '' }}. An authorized Carolina administrator must apply the rate in the room editor.</p>
    @elseif($change->status === 'rejected')
        <p class="notice">Rejection recorded{{ $change->rejected_at ? ' on '.$change->rejected_at->timezone('Asia/Manila')->format('M j, Y g:i A').' PHT' : '' }}. The existing room rate remains unchanged.</p>
    @else
        <p class="notice">This approval link is no longer active. Contact Carolina if you need a new request sent.</p>
    @endif
</main></body></html>
