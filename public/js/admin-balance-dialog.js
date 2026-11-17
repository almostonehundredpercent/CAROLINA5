(() => {
    document.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const opener = target.closest('[data-balance-dialog-open]');
        if (opener) {
            document.getElementById(opener.dataset.balanceDialogOpen)?.showModal();
            return;
        }

        if (target instanceof HTMLDialogElement && target.matches('.balance-due-dialog')) {
            target.close();
        }
    });
})();
