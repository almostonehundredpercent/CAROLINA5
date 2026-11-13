const DAY_MS = 86400000;
const monthFormatter = new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const dateFormatter = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const accessibleDateFormatter = new Intl.DateTimeFormat('en-PH', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const isoDate = (date) => `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
const parseDate = (value) => {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day));
};
const shiftDate = (value, days) => isoDate(new Date(parseDate(value).getTime() + days * DAY_MS));
const shiftMonth = (date, amount) => new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + amount, 1));

function initializeDatePicker(root) {
    const input = root.querySelector('[data-home-date]');
    const trigger = root.querySelector('[data-home-date-trigger]');
    const valueLabel = root.querySelector('[data-home-date-value]');
    const calendar = root.querySelector('[data-home-date-calendar]');
    if (!input || !trigger || !valueLabel || !calendar) return null;

    let cursor = parseDate(input.value || input.min || isoDate(new Date()));
    const render = () => {
        const year = cursor.getUTCFullYear();
        const month = cursor.getUTCMonth();
        const firstWeekday = new Date(Date.UTC(year, month, 1)).getUTCDay();
        const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        const minDate = input.min || '';
        const today = minDate || isoDate(new Date());
        const cells = Array(firstWeekday).fill('<span class="home-calendar-blank" aria-hidden="true"></span>');

        for (let day = 1; day <= daysInMonth; day += 1) {
            const dateValue = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const selected = dateValue === input.value;
            const isToday = dateValue === today;
            const disabled = minDate && dateValue < minDate;
            cells.push(`<button type="button" class="home-calendar-day${selected ? ' is-selected' : ''}${isToday ? ' is-today' : ''}" data-calendar-date="${dateValue}" aria-label="${accessibleDateFormatter.format(parseDate(dateValue))}" aria-pressed="${selected}"${disabled ? ' disabled' : ''}>${day}</button>`);
        }
        while (cells.length % 7) cells.push('<span class="home-calendar-blank" aria-hidden="true"></span>');

        const previousMonth = shiftMonth(cursor, -1);
        const previousDisabled = minDate && isoDate(new Date(Date.UTC(previousMonth.getUTCFullYear(), previousMonth.getUTCMonth() + 1, 0))) < minDate;
        calendar.innerHTML = `<div class="home-calendar-head"><button type="button" data-calendar-month="-1" aria-label="Previous month"${previousDisabled ? ' disabled' : ''}><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 4.5-5 5.5 5 5.5"/></svg></button><strong aria-live="polite">${monthFormatter.format(cursor)}</strong><button type="button" data-calendar-month="1" aria-label="Next month"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m7.5 4.5 5 5.5-5 5.5"/></svg></button><button type="button" data-calendar-close aria-label="Close calendar"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15"/></svg></button></div><div class="home-calendar-week" aria-hidden="true">${weekdays.map(day => `<span>${day}</span>`).join('')}</div><div class="home-calendar-grid" role="grid" aria-label="${monthFormatter.format(cursor)}">${cells.join('')}</div><div class="home-calendar-foot"><button type="button" data-calendar-today${today < minDate ? ' disabled' : ''}>Today</button><span>Choose your arrival date</span></div>`;
    };

    const close = (returnFocus = false) => {
        calendar.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (returnFocus) trigger.focus();
    };
    const open = () => {
        cursor = parseDate(input.value || input.min || isoDate(new Date()));
        render();
        calendar.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const target = calendar.querySelector(`[data-calendar-date="${input.value || input.min}"]`) || calendar.querySelector('.home-calendar-day:not(:disabled)');
        target?.focus();
    };
    const choose = (value) => {
        if (input.min && value < input.min) return;
        input.value = value;
        valueLabel.textContent = dateFormatter.format(parseDate(value));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    };

    valueLabel.textContent = input.value ? dateFormatter.format(parseDate(input.value)) : 'Choose date';
    trigger.addEventListener('click', () => calendar.hidden ? open() : close());
    calendar.addEventListener('click', (event) => {
        const day = event.target.closest('[data-calendar-date]');
        if (day && !day.disabled) {
            choose(day.dataset.calendarDate);
            return;
        }
        const shift = event.target.closest('[data-calendar-month]');
        if (shift && !shift.disabled) {
            cursor = shiftMonth(cursor, Number(shift.dataset.calendarMonth));
            render();
            calendar.querySelector(`[data-calendar-month="${shift.dataset.calendarMonth}"]`)?.focus();
            return;
        }
        if (event.target.closest('[data-calendar-close]')) close(true);
        if (event.target.closest('[data-calendar-today]') && !event.target.closest('[data-calendar-today]').disabled) choose(input.min || isoDate(new Date()));
    });
    calendar.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
            return;
        }
        const day = event.target.closest('[data-calendar-date]');
        if (!day) return;
        const increments = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        if (increments[event.key]) {
            event.preventDefault();
            const next = shiftDate(day.dataset.calendarDate, increments[event.key]);
            let target = calendar.querySelector(`[data-calendar-date="${next}"]:not(:disabled)`);
            if (!target) {
                cursor = parseDate(next);
                render();
                target = calendar.querySelector(`[data-calendar-date="${next}"]:not(:disabled)`);
            }
            target?.focus();
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const weekday = parseDate(day.dataset.calendarDate).getUTCDay();
            const next = shiftDate(day.dataset.calendarDate, event.key === 'Home' ? -weekday : 6 - weekday);
            calendar.querySelector(`[data-calendar-date="${next}"]:not(:disabled)`)?.focus();
        }
    });
    return { root, calendar, close };
}

function initializeCustomSelect(root) {
    const select = root.querySelector('select');
    const trigger = root.querySelector('[data-home-select-trigger]');
    const valueLabel = root.querySelector('[data-home-select-value]');
    const list = root.querySelector('[data-home-select-list]');
    if (!select || !trigger || !valueLabel || !list) return null;

    const render = () => {
        list.innerHTML = [...select.options].map((option, index) => {
            const display = option.dataset.display || option.textContent.trim();
            const detail = option.dataset.detail || '';
            const selected = option.value === select.value;
            return `<div class="home-select-option${selected ? ' is-selected' : ''}" role="option" tabindex="-1" data-value="${option.value}" aria-selected="${selected}" aria-label="${option.textContent.trim()}"><span>${display}</span>${detail ? `<small>${detail}</small>` : ''}</div>`;
        }).join('');
        valueLabel.textContent = select.selectedOptions[0]?.dataset.display || select.selectedOptions[0]?.textContent.trim() || '';
    };
    const close = (returnFocus = false) => {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (returnFocus) trigger.focus();
    };
    const open = (focusSelected = true) => {
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const selected = list.querySelector('[aria-selected="true"]') || list.querySelector('[role="option"]');
        if (focusSelected) selected?.focus();
    };
    const choose = (option) => {
        if (!option) return;
        select.value = option.dataset.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        render();
        close(true);
    };

    render();
    select.addEventListener('change', render);
    trigger.addEventListener('click', () => list.hidden ? open() : close());
    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });
    list.addEventListener('click', (event) => choose(event.target.closest('[role="option"]')));
    list.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
            return;
        }
        const options = [...list.querySelectorAll('[role="option"]')];
        const index = options.indexOf(event.target.closest('[role="option"]'));
        let next = null;
        if (event.key === 'ArrowDown') next = options[Math.min(options.length - 1, index + 1)];
        else if (event.key === 'ArrowUp') next = options[Math.max(0, index - 1)];
        else if (event.key === 'Home') next = options[0];
        else if (event.key === 'End') next = options.at(-1);
        else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            choose(options[index]);
            return;
        }
        if (next) {
            event.preventDefault();
            next.focus();
        }
    });
    return { root, list, close };
}

const initializeHomeBookingSearch = () => {
    const openPopovers = [];
    document.querySelectorAll('.home-booking-search').forEach(form => {
        const stay = form.querySelector('[data-home-stay]');
        const mode = form.querySelector('[name="mode"]');
        if (stay && mode) {
            const syncMode = () => { mode.value = stay.selectedOptions[0]?.dataset.mode || 'dates'; };
            stay.addEventListener('change', syncMode);
            syncMode();
        }
        form.querySelectorAll('[data-home-date-picker]').forEach(root => {
            const picker = initializeDatePicker(root);
            if (picker) openPopovers.push(picker);
        });
        form.querySelectorAll('[data-home-select]').forEach(root => {
            const picker = initializeCustomSelect(root);
            if (picker) openPopovers.push(picker);
        });
    });
    document.addEventListener('pointerdown', (event) => {
        openPopovers.forEach(({ root, calendar, list, close }) => {
            if (root.contains(event.target)) return;
            if (calendar?.contains(event.target) || list?.contains(event.target)) return;
            if (calendar && !calendar.hidden || list && !list.hidden) close();
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomeBookingSearch, { once: true });
} else {
    initializeHomeBookingSearch();
}
