<dialog class="admin-confirm-dialog" id="admin-confirm-dialog" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message">
    <div class="admin-confirm-card">
        <span class="admin-confirm-mark" aria-hidden="true">✓</span>
        <p class="admin-confirm-kicker">CONFIRM CHECK-IN</p>
        <h2 id="admin-confirm-title">Check in this guest?</h2>
        <p id="admin-confirm-message"></p>
        <div class="admin-confirm-actions">
            <button class="admin-confirm-cancel" type="button" data-confirm-cancel>Cancel</button>
            <button class="admin-confirm-accept" type="button" data-confirm-accept>Check in guest</button>
        </div>
    </div>
</dialog>
<script src="{{ asset('js/admin-confirmations.js') }}?v={{ filemtime(public_path('js/admin-confirmations.js')) }}" defer></script>
