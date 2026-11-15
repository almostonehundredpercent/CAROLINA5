(() => {
    const dialog = document.querySelector('[data-booking-cancel-dialog]');
    if (!(dialog instanceof HTMLDialogElement)) return;

    const approvedSubmissions = new WeakSet();
    const closeButtons = dialog.querySelectorAll('[data-booking-cancel-close]');
    const acceptButton = dialog.querySelector('[data-booking-cancel-accept]');
    let pendingForm = null;

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-booking-cancel-confirm]')) return;
        if (approvedSubmissions.has(form)) {
            approvedSubmissions.delete(form);
            return;
        }

        event.preventDefault();
        pendingForm = form;

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return;
        }

        pendingForm = null;
        const policy = Array.from(dialog.querySelectorAll('.booking-cancel-policy p'), row => row.innerText.trim()).join('\n');
        const message = [
            dialog.querySelector('#booking-cancel-title')?.textContent.trim(),
            dialog.querySelector('#booking-cancel-copy')?.textContent.trim(),
            policy,
            dialog.querySelector('.booking-cancel-note')?.textContent.trim(),
        ].filter(Boolean).join('\n\n');
        if (window.confirm(message || 'Are you sure you want to cancel this booking?')) {
            if (typeof form.requestSubmit === 'function') {
                approvedSubmissions.add(form);
                form.requestSubmit(event.submitter instanceof HTMLElement ? event.submitter : undefined);
            } else {
                form.submit();
            }
        }
    }, true);

    closeButtons.forEach(button => button.addEventListener('click', () => dialog.close()));

    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('close', () => {
        pendingForm = null;
    });

    acceptButton?.addEventListener('click', () => {
        const form = pendingForm;
        pendingForm = null;
        dialog.close();
        if (!form) return;

        approvedSubmissions.add(form);
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });
})();
