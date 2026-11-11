<h1>Approved room rate applied</h1>
<p>This is the Carolina record of the owner-approved room rate now in effect.</p>
<p><strong>Approval reference:</strong> {{ $change->reference }}<br>
<strong>Room:</strong> {{ $change->room->name }}<br>
<strong>Previous rate:</strong> ₱{{ number_format((float) $change->old_price, 2) }}<br>
<strong>New rate:</strong> ₱{{ number_format((float) $change->proposed_price, 2) }}<br>
<strong>Owner approval date:</strong> {{ $change->approved_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} Philippine time<br>
<strong>Applied date:</strong> {{ $change->applied_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} Philippine time<br>
<strong>Applied by:</strong> {{ $change->applier?->name ?? 'Authorized Carolina administrator' }}<br>
<strong>Reason:</strong> {{ $change->reason }}</p>
<p>Bookings created before this change keep their saved booking total and recorded rate.</p>
