@php($balanceDialogId = 'remaining-balance-'.($dialogContext ?? 'booking').'-'.$booking->id)
<button class="balance-due-link" type="button" data-balance-dialog-open="{{ $balanceDialogId }}">Balance due</button>
<dialog class="balance-due-dialog" id="{{ $balanceDialogId }}" aria-labelledby="{{ $balanceDialogId }}-title">
    <section>
        <p class="admin-kicker">BALANCE DUE</p>
        <h2 id="{{ $balanceDialogId }}-title">Remaining balance</h2>
        <strong>₱{{ number_format($balance, 2) }}</strong>
        <form method="dialog"><button class="balance-dialog-close" type="submit">Close</button></form>
    </section>
</dialog>
