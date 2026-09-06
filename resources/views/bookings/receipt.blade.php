@extends('layouts.app')

@section('content')
@php
    $deposit = (float) $booking->deposit_amount;
    $remaining = max(0, (float) $booking->total_amount - $deposit);
    $paymentComplete = $booking->deposit_status === 'verified';
@endphp
<style>.deposit-card{max-width:760px;margin:0 auto 38px;padding:24px;border:1px solid var(--line);border-radius:10px;background:#fff;text-align:left}.deposit-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:19px 0}.deposit-amounts div{padding:13px;border-radius:7px;background:var(--sand)}.deposit-amounts small{display:block;color:var(--muted);margin-bottom:5px}.deposit-amounts b{font-size:1.05rem}.deposit-instructions{padding:16px;border-radius:8px;background:#fff7e9;border:1px solid #f0d6a5}.deposit-instructions p{margin:0 0 7px}.deposit-instructions p:last-child{margin-bottom:0}.deposit-proof-form{display:grid;gap:14px;margin-top:20px}.deposit-proof-form label{display:grid;gap:7px;font-size:.8rem;font-weight:700;color:var(--muted)}.deposit-proof-form input{width:100%}.payment-status{display:inline-flex;padding:6px 10px;border-radius:20px;background:#fff0d4;color:#8d5700;font-size:.8rem;font-weight:700}.payment-status.submitted{background:#e5efff;color:#275cae}.payment-status.verified{background:#dff5e7;color:#1d6c3d}.deposit-hint{font-size:.8rem;margin:0}@media(max-width:600px){.deposit-card{padding:17px}.deposit-amounts{grid-template-columns:1fr}}</style>
<section class="confirmation receipt-page">
    <span class="eyebrow">RESERVATION DEPOSIT</span>
    <h1>{{ $paymentComplete ? 'Your booking is confirmed.' : 'Secure your reservation.' }}</h1>
    <p>{{ $paymentComplete ? 'Your GCash deposit was verified by Carolina.' : 'A reservation deposit is required before Carolina can confirm this booking.' }}</p>

    <div class="confirmation-card receipt-card">
        <div><small>Booking reference</small><b>{{ $booking->reference }}</b></div>
        <div><small>Stay type</small><b>{{ $booking->booking_type === 'hourly' ? $booking->hours . ' hours' : $booking->nights . ' night' . ($booking->nights === 1 ? '' : 's') }}</b></div>
        <div><small>Room</small><b>{{ $booking->room->name }}</b></div>
        <div><small>Stay</small><b>{{ $booking->check_in->format('M j, Y') }} – {{ $booking->check_out->format('M j, Y') }}</b></div>
    </div>

    <section class="deposit-card">
        <span class="payment-status {{ $booking->deposit_status }}">{{ match($booking->deposit_status) { 'verified' => 'Deposit verified', 'submitted' => 'Deposit awaiting review', default => 'Awaiting GCash deposit' } }}</span>
        <div class="deposit-amounts">
            <div><small>Booking total</small><b>₱{{ number_format($booking->total_amount, 2) }}</b></div>
            <div><small>GCash deposit due</small><b>₱{{ number_format($deposit, 2) }}</b></div>
            <div><small>Pay at property</small><b>₱{{ number_format($remaining, 2) }}</b></div>
        </div>

        @if(! $paymentComplete)
            <div class="deposit-instructions">
                <p><strong>Step 1 — Pay the deposit through GCash.</strong></p>
                @if(config('payments.gcash_qr_url'))<img src="{{ config('payments.gcash_qr_url') }}" alt="Carolina GCash payment QR" style="width:160px;max-width:100%;margin:8px 0;border-radius:7px">@endif
                <p>Recipient: <strong>{{ config('payments.gcash_recipient_name') ?: 'Carolina GCash Business account' }}</strong></p>
                @if(config('payments.gcash_number'))<p>GCash number: <strong>{{ config('payments.gcash_number') }}</strong></p>@endif
                <p>Amount: <strong>₱{{ number_format($deposit, 2) }}</strong> · Reference: <strong>{{ $booking->reference }}</strong></p>
                @if($booking->deposit_status === 'awaiting_deposit' && $booking->deposit_due_at)<p><strong>Deposit window:</strong> <span id="deposit-countdown" data-deadline="{{ $booking->deposit_due_at->toIso8601String() }}">30 minutes remaining</span></p>@endif
                <p class="deposit-hint">Do not send money to a different account. Carolina verifies the exact amount and transaction reference in its GCash records before confirming your stay.</p>
            </div>

            @if($booking->deposit_status === 'submitted')
                <p class="deposit-hint" style="margin-top:18px">Your proof was submitted {{ $booking->deposit_submitted_at?->diffForHumans() }}. Please wait for Carolina to verify it. You may replace it below only if you entered incorrect details.</p>
            @endif

            <form class="deposit-proof-form" method="POST" action="{{ route('bookings.deposit.submit', $booking) }}" enctype="multipart/form-data">
                @csrf
                <label>GCash transaction or QR reference<input name="payment_reference" value="{{ old('payment_reference', $booking->payment_reference) }}" placeholder="Enter the transaction reference" required></label>
                <label>Payment proof (JPG, PNG, or WEBP; max 2 MB)<input name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp" required></label>
                <button class="button" type="submit">{{ $booking->deposit_status === 'submitted' ? 'Replace payment proof' : 'Submit deposit for verification' }}</button>
            </form>
        @else
            <p class="deposit-hint">Keep this reference for your records. The remaining ₱{{ number_format($remaining, 2) }} is payable at the property.</p>
        @endif
    </section>
    <button class="button light" type="button" onclick="window.print()">Print / save receipt</button>
</section>
<script>document.addEventListener('DOMContentLoaded',()=>{const countdown=document.getElementById('deposit-countdown');if(!countdown)return;const deadline=new Date(countdown.dataset.deadline);const tick=()=>{const seconds=Math.max(0,Math.ceil((deadline-Date.now())/1000));if(!seconds){countdown.textContent='Expired — refreshing…';setTimeout(()=>location.reload(),800);return}countdown.textContent=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} remaining`};tick();setInterval(tick,1000)});</script>
@endsection
