(() => {
    const initialize = () => {
        const form = document.querySelector('.booking-form form');
        if (!form) return;

        form.noValidate = true;
        const summary = form.querySelector('#booking-validation-summary');
        const summaryList = summary?.querySelector('ul');
        const controls = [...form.querySelectorAll('[required]')].filter(control => !control.readOnly);

        const keyFor = control => control.name || control.id;
        const errorIdFor = control => `${keyFor(control).replaceAll('_', '-')}-error`;
        const containerFor = control => control.closest('label') || control.parentElement;
        const focusTargetFor = control => {
            if (control.type !== 'hidden') return control;
            const container = containerFor(control);
            return container?.querySelector('button, input:not([type="hidden"]), select') || control;
        };
        const messageFor = control => {
            const value = control.value.trim();
            if (control.type === 'checkbox') return control.checked ? '' : 'Accept the reservation terms to continue.';
            if (!value) {
                return ({
                    guest_name: 'Enter the guest’s full name.',
                    guest_email: 'Enter an email address.',
                    guest_phone: 'Enter a phone number.',
                    check_in: 'Choose a check-in date.',
                    check_out: 'Choose a check-out date.',
                    hourly_date: 'Choose a check-in date.',
                    check_in_time: 'Choose a check-in time.',
                })[control.name] || 'Complete this field to continue.';
            }
            if (control.name === 'guest_name' && value.length > 255) return 'Name must be 255 characters or fewer.';
            if (control.type === 'email' && control.validity.typeMismatch) return 'Enter a valid email address.';
            if (control.name === 'guest_phone') {
                const normalized = value.replace(/[\s()\-]/g, '');
                if (!/^(?:\+63|63|0)9\d{9}$/.test(normalized)) {
                    return 'Enter a valid Philippine mobile number (09XX XXX XXXX or +63 9XX XXX XXXX).';
                }
            }
            return '';
        };
        const updateSummary = (control, message) => {
            if (!summary || !summaryList) return;
            const key = keyFor(control);
            const item = [...summaryList.querySelectorAll('li')].find(row => row.dataset.validationField === key);
            if (message) {
                const row = item || document.createElement('li');
                row.dataset.validationField = key;
                row.textContent = message;
                if (!item) summaryList.append(row);
            } else item?.remove();
            summary.hidden = summaryList.children.length === 0;
        };
        const validate = control => {
            const message = messageFor(control);
            const container = containerFor(control);
            const focusTarget = focusTargetFor(control);
            let error = document.getElementById(errorIdFor(control));

            if (message) {
                if (!error) {
                    error = document.createElement('small');
                    error.className = 'booking-field-error';
                    error.id = errorIdFor(control);
                    error.setAttribute('aria-live', 'polite');
                    container?.append(error);
                }
                error.textContent = message;
                control.setAttribute('aria-invalid', 'true');
                focusTarget?.setAttribute('aria-invalid', 'true');
                const describedBy = new Set((focusTarget?.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                describedBy.add(error.id);
                focusTarget?.setAttribute('aria-describedby', [...describedBy].join(' '));
            } else {
                error?.remove();
                control.removeAttribute('aria-invalid');
                focusTarget?.removeAttribute('aria-invalid');
                const describedBy = (focusTarget?.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== errorIdFor(control));
                if (describedBy.length) focusTarget?.setAttribute('aria-describedby', describedBy.join(' '));
                else focusTarget?.removeAttribute('aria-describedby');
            }

            updateSummary(control, message);
            return !message;
        };

        form.addEventListener('input', event => {
            if (controls.includes(event.target)) validate(event.target);
        });
        form.addEventListener('change', event => {
            if (controls.includes(event.target)) validate(event.target);
        });
        form.addEventListener('submit', event => {
            const invalid = controls.filter(control => !validate(control));
            if (!invalid.length) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            focusTargetFor(invalid[0])?.focus();
            summary?.removeAttribute('hidden');
        }, true);
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
