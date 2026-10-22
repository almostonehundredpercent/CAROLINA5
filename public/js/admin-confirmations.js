(() => {
    const dialog = document.getElementById('admin-confirm-dialog');
    if (!dialog) return;

    const title = dialog.querySelector('#admin-confirm-title');
    const message = dialog.querySelector('#admin-confirm-message');
    const cancelButton = dialog.querySelector('[data-confirm-cancel]');
    const acceptButton = dialog.querySelector('[data-confirm-accept]');
    const approvedSubmissions = new WeakSet();
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-admin-confirm]')) return;
        if (approvedSubmissions.has(form)) {
            approvedSubmissions.delete(form);
            return;
        }

        event.preventDefault();
        pendingForm = form;
        pendingSubmitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        title.textContent = form.dataset.confirmTitle || 'Please confirm';
        message.textContent = form.dataset.confirmMessage || 'Continue with this action?';
        acceptButton.textContent = form.dataset.confirmAction || 'Continue';
        dialog.showModal();
    }, true);

    cancelButton.addEventListener('click', () => dialog.close());

    dialog.addEventListener('close', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });

    acceptButton.addEventListener('click', () => {
        const form = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;
        dialog.close();
        if (!form) return;

        if (typeof form.requestSubmit === 'function') {
            approvedSubmissions.add(form);
            window.setTimeout(() => approvedSubmissions.delete(form), 0);
            if (submitter?.form === form) form.requestSubmit(submitter);
            else form.requestSubmit();
        } else {
            form.submit();
        }
    });
})();
