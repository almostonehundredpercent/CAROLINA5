(() => {
    const showNext = (deck, card) => {
        const cards = Array.from(deck.querySelectorAll('[data-carol-card]'));
        const currentIndex = cards.indexOf(card);
        card.hidden = true;

        const next = cards.slice(currentIndex + 1).find(candidate => candidate.hidden);
        if (next) {
            next.hidden = false;
            return;
        }

        const caughtUp = deck.querySelector('[data-carol-caught-up]');
        if (caughtUp) caughtUp.hidden = false;
    };

    const renderResult = (container, payload) => {
        container.replaceChildren();
        const message = document.createElement('p');
        message.textContent = payload.message || 'CAROL completed the suggestion.';
        container.append(message);

        if (Array.isArray(payload.rows) && payload.rows.length) {
            const list = document.createElement('ul');
            payload.rows.forEach(row => {
                const item = document.createElement('li');
                const label = document.createElement('strong');
                label.textContent = row.label || 'Record';
                const detail = document.createElement('span');
                detail.textContent = row.detail || '';
                item.append(label, detail);
                list.append(item);
            });
            container.append(list);
        }

        container.hidden = false;
    };

    document.querySelectorAll('[data-carol-deck]').forEach(deck => {
        const confirmationDialog = deck.querySelector('[data-carol-confirm-dialog]');
        const confirmationTitle = confirmationDialog?.querySelector('[data-carol-confirm-recommendation]');
        const confirmationMessage = confirmationDialog?.querySelector('[data-carol-confirm-message]');
        let pendingSuggestionForm = null;

        confirmationDialog?.addEventListener('cancel', () => {
            pendingSuggestionForm = null;
        });

        deck.addEventListener('click', event => {
            const cancelConfirmation = event.target.closest('[data-carol-confirm-cancel]');
            if (cancelConfirmation && confirmationDialog) {
                pendingSuggestionForm = null;
                confirmationDialog.close('cancel');
                return;
            }

            const continueConfirmation = event.target.closest('[data-carol-confirm-continue]');
            if (continueConfirmation && confirmationDialog) {
                const form = pendingSuggestionForm;
                pendingSuggestionForm = null;
                confirmationDialog.close('confirm');
                if (form) {
                    form.dataset.carolConfirmed = 'true';
                    form.requestSubmit(form.querySelector('button[type="submit"]'));
                }
                return;
            }

            const nextButton = event.target.closest('[data-carol-next]');
            if (!nextButton || !deck.contains(nextButton)) return;
            const card = nextButton.closest('[data-carol-card]');
            if (card) showNext(deck, card);
        });

        deck.addEventListener('submit', async event => {
            const form = event.target.closest('form[data-carol-action]');
            if (!form || !deck.contains(form)) return;

            if (form.hasAttribute('data-carol-confirm-suggestion') && form.dataset.carolConfirmed !== 'true') {
                event.preventDefault();
                const card = form.closest('[data-carol-card]');
                const title = card?.querySelector('h3')?.textContent.trim() || 'Confirm this recommendation';
                const message = form.dataset.carolConfirmMessage || 'CAROL will show matching records here for review.';

                if (!confirmationDialog?.showModal) {
                    if (window.confirm(`${title}\n\n${message}`)) {
                        form.dataset.carolConfirmed = 'true';
                        form.requestSubmit(form.querySelector('button[type="submit"]'));
                    }
                    return;
                }

                if (confirmationTitle) confirmationTitle.textContent = title;
                if (confirmationMessage) confirmationMessage.textContent = message;
                pendingSuggestionForm = form;
                confirmationDialog.showModal();
                return;
            }

            delete form.dataset.carolConfirmed;
            event.preventDefault();

            const submit = form.querySelector('button[type="submit"]');
            const card = form.closest('[data-carol-card]');
            const result = card?.querySelector('[data-carol-result]');
            const reminderStatus = form.closest('.carol-reminder-status');
            if (submit) submit.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'CAROL could not complete this suggestion. Refresh and try again.');

                if (reminderStatus) {
                    const copy = reminderStatus.querySelector('[data-carol-reminder-copy]');
                    if (copy) copy.textContent = payload.message;
                    form.hidden = true;
                    const state = payload.enabled ? 'on' : 'off';
                    const nextForm = reminderStatus.querySelector(`[data-carol-reminder-state="${state}"]`);
                    const nextButton = nextForm?.querySelector('button[type="submit"]');
                    if (nextButton) nextButton.disabled = false;
                    if (nextForm) nextForm.hidden = false;
                    return;
                }

                if (!card || !result) return;
                renderResult(result, payload);
                const actions = card.querySelector('[data-carol-actions]');
                if (actions) actions.hidden = true;
                const next = card.querySelector('.carol-next-button');
                if (next) next.hidden = false;
            } catch (error) {
                if (card && result) {
                    renderResult(result, { message: error.message });
                } else if (reminderStatus) {
                    const errorNote = document.createElement('span');
                    errorNote.className = 'carol-action-error';
                    errorNote.setAttribute('role', 'alert');
                    errorNote.textContent = error.message;
                    reminderStatus.append(errorNote);
                }
                if (submit) submit.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-carol-promo]').forEach(card => {
        card.addEventListener('click', event => {
            const nextButton = event.target.closest('[data-carol-promo-next]');
            if (!nextButton || !card.contains(nextButton)) return;
            const deck = card.closest('[data-carol-promo-deck]');
            if (!deck) return;
            const cards = Array.from(deck.querySelectorAll('[data-carol-promo]'));
            const currentIndex = cards.indexOf(card);
            card.hidden = true;
            const next = cards.slice(currentIndex + 1).find(candidate => candidate.hidden);
            if (next) next.hidden = false;
            else {
                const caughtUp = deck.querySelector('[data-carol-promo-caught-up]');
                if (caughtUp) caughtUp.hidden = false;
            }
        });

        card.addEventListener('submit', async event => {
            const form = event.target.closest('form[data-carol-promo-action]');
            if (!form || !card.contains(form)) return;
            event.preventDefault();

            const submit = form.querySelector('button[type="submit"]');
            const actions = card.querySelector('[data-carol-promo-actions]');
            const result = card.querySelector('[data-carol-promo-result]');
            if (submit) submit.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'CAROL could not update this promo draft.');
                if (result) {
                    result.textContent = payload.message || 'CAROL completed the suggestion.';
                    result.hidden = false;
                }
                if (actions) actions.hidden = true;
                const next = card.querySelector('[data-carol-promo-next]');
                if (next) next.hidden = false;
                const count = document.querySelector('[data-carol-promo-count]');
                if (count) {
                    const remaining = Math.max(0, Number.parseInt(count.textContent || '0', 10) - 1);
                    count.textContent = String(remaining);
                    if (remaining === 0) {
                        const heading = document.querySelector('[data-carol-promo-heading]');
                        const copy = document.querySelector('[data-carol-promo-copy]');
                        const link = document.querySelector('[data-carol-promo-link]');
                        if (heading) heading.textContent = 'You are all caught up';
                        if (copy) copy.textContent = 'No CAROL promo drafts are waiting for review.';
                        link?.remove();
                    }
                }
            } catch (error) {
                if (result) {
                    result.textContent = error.message;
                    result.hidden = false;
                }
                if (submit) submit.disabled = false;
            }
        });
    });
})();
