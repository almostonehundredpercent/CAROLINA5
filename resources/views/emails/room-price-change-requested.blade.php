<h1>Carolina room rate approval</h1>
<p>A room rate change is waiting for your decision.</p>
<p><strong>Approval reference:</strong> {{ $change->reference }}<br>
<strong>Room:</strong> {{ $change->room->name }}<br>
<strong>Current rate:</strong> ₱{{ number_format((float) $change->old_price, 2) }}<br>
<strong>Proposed rate:</strong> ₱{{ number_format((float) $change->proposed_price, 2) }}<br>
<strong>Requested by:</strong> {{ $change->requester?->name ?? 'Carolina administrator' }}<br>
<strong>Reason:</strong> {{ $change->reason }}<br>
<strong>Requested:</strong> {{ $change->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} Philippine time</p>
<p><a href="{{ $reviewUrl }}">Review and approve or reject this rate</a></p>
<p>This private approval link expires in seven days. If you did not expect this request, you can ignore this message.</p>
