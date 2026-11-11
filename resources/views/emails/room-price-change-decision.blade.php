<h1>Room rate approval {{ $change->status }}</h1>
<p>This is your copy of the rate decision for Carolina's records.</p>
<p><strong>Approval reference:</strong> {{ $change->reference }}<br>
<strong>Room:</strong> {{ $change->room->name }}<br>
<strong>Previous rate:</strong> ₱{{ number_format((float) $change->old_price, 2) }}<br>
<strong>Proposed rate:</strong> ₱{{ number_format((float) $change->proposed_price, 2) }}<br>
<strong>Decision:</strong> {{ ucfirst($change->status) }}<br>
<strong>Decision date:</strong> {{ ($change->approved_at ?? $change->rejected_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') }} Philippine time<br>
<strong>Reason:</strong> {{ $change->reason }}</p>
@if($change->status === 'approved')
<p>The new rate takes effect only after an authorized Carolina administrator applies this approval. Existing bookings retain their recorded booking totals and rates.</p>
@else
<p>The room's current rate remains unchanged.</p>
@endif
