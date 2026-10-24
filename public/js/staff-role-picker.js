(() => {
    const pickers = [...document.querySelectorAll('[data-staff-role-picker]')];
    if (!pickers.length) return;

    const closePicker = (picker, { restoreFocus = false } = {}) => {
        const trigger = picker.querySelector('[data-staff-role-trigger]');
        const options = picker.querySelector('[data-staff-role-options]');
        if (!trigger || !options) return;

        options.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        picker.removeAttribute('data-open');
        if (restoreFocus) trigger.focus();
    };

    const openPicker = (picker, focusSelected = false) => {
        const trigger = picker.querySelector('[data-staff-role-trigger]');
        const options = picker.querySelector('[data-staff-role-options]');
        if (!trigger || !options) return;

        pickers.forEach(other => {
            if (other !== picker) closePicker(other);
        });

        options.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        picker.setAttribute('data-open', 'true');
        if (focusSelected) {
            (options.querySelector('[aria-selected="true"]') || options.querySelector('[role="option"]'))?.focus();
        }
    };

    pickers.forEach((picker, pickerIndex) => {
        const select = picker.querySelector('select[name="staff_role"]');
        const trigger = picker.querySelector('[data-staff-role-trigger]');
        const label = picker.querySelector('[data-staff-role-label]');
        const options = picker.querySelector('[data-staff-role-options]');
        if (!select || !trigger || !label || !options) return;

        if (!options.id) options.id = `staff-role-options-${pickerIndex + 1}`;
        trigger.setAttribute('aria-controls', options.id);

        const syncFromSelect = () => {
            const selected = select.selectedOptions[0];
            if (!selected) return;
            label.textContent = selected.textContent.trim();
            options.querySelectorAll('[role="option"]').forEach(option => {
                const isSelected = option.dataset.value === select.value;
                option.setAttribute('aria-selected', String(isSelected));
                option.tabIndex = isSelected ? 0 : -1;
            });
        };

        const choose = option => {
            const value = option?.dataset.value;
            if (!value || ![...select.options].some(candidate => candidate.value === value)) return;
            select.value = value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncFromSelect();
            closePicker(picker, { restoreFocus: true });
        };

        select.addEventListener('change', syncFromSelect);
        trigger.addEventListener('click', () => {
            if (trigger.getAttribute('aria-expanded') === 'true') closePicker(picker);
            else openPicker(picker);
        });
        trigger.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                openPicker(picker, true);
            }
        });
        options.querySelectorAll('[role="option"]').forEach(option => {
            option.addEventListener('click', () => choose(option));
            option.addEventListener('keydown', event => {
                const allOptions = [...options.querySelectorAll('[role="option"]')];
                const currentIndex = allOptions.indexOf(option);
                let nextIndex = null;

                if (event.key === 'ArrowDown') nextIndex = (currentIndex + 1) % allOptions.length;
                if (event.key === 'ArrowUp') nextIndex = (currentIndex - 1 + allOptions.length) % allOptions.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = allOptions.length - 1;
                if (nextIndex !== null) {
                    event.preventDefault();
                    allOptions[nextIndex]?.focus();
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    choose(option);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closePicker(picker, { restoreFocus: true });
                }
            });
        });

        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                event.preventDefault();
                closePicker(picker, { restoreFocus: true });
            }
        });

        syncFromSelect();
        picker.dataset.enhanced = 'true';
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
    });

    document.addEventListener('click', event => {
        pickers.forEach(picker => {
            if (!picker.contains(event.target)) closePicker(picker);
        });
    });
})();
