const DAY_MS = 86400000;
const monthFormatter = new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const dateFormatter = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const accessibleDateFormatter = new Intl.DateTimeFormat('en-PH', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' });

const utcDay = (value) => {
    if (!value) return NaN;
    const [year, month, day] = value.split('-').map(Number);
    return Date.UTC(year, month - 1, day);
};

const isoDate = (year, monthIndex, day) => `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

export function buildMonthCells(year, monthIndex) {
    const firstWeekday = new Date(Date.UTC(year, monthIndex, 1)).getUTCDay();
    const daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
    const cells = Array(firstWeekday).fill(null);
    for (let day = 1; day <= daysInMonth; day += 1) cells.push(isoDate(year, monthIndex, day));
    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
}

function shiftMonths(value, amount) {
    if (!value) return '';
    const [year, month, day] = value.split('-').map(Number);
    const targetMonth = month - 1 + amount;
    const targetYear = year + Math.floor(targetMonth / 12);
    const normalizedMonth = ((targetMonth % 12) + 12) % 12;
    const targetDay = Math.min(day, new Date(Date.UTC(targetYear, normalizedMonth + 1, 0)).getUTCDate());
    return isoDate(targetYear, normalizedMonth, targetDay);
}

function shiftDays(value, amount) {
    const shifted = new Date(utcDay(value) + amount * DAY_MS);
    return isoDate(shifted.getUTCFullYear(), shifted.getUTCMonth(), shifted.getUTCDate());
}

function earliestStartDay(endValue) {
    let earliest = shiftMonths(endValue, -3);
    while (utcDay(shiftMonths(earliest, 3)) < utcDay(endValue)) earliest = shiftDays(earliest, 1);
    return earliest;
}

function toUTCDate(value) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day));
}

function formattedDate(value) {
    return value ? dateFormatter.format(toUTCDate(value)) : 'Choose date';
}

function todayISO() {
    const today = new Date();
    return isoDate(today.getFullYear(), today.getMonth(), today.getDate());
}

export function mountReportDateCalendars(root) {
    if (!root) return;
    const form = root.closest('form');
    if (!form) return;

    const fields = {
        from: { input: form.querySelector('[name="from"]'), trigger: root.querySelector('[data-date-trigger="from"]'), calendar: root.querySelector('[data-date-calendar="from"]') },
        to: { input: form.querySelector('[name="to"]'), trigger: root.querySelector('[data-date-trigger="to"]'), calendar: root.querySelector('[data-date-calendar="to"]') },
    };
    if (Object.values(fields).some(({ input, trigger, calendar }) => !input || !trigger || !calendar)) return;
    for (const field of Object.values(fields)) field.container = field.calendar.parentElement;

    const submit = form.querySelector('button[type="submit"]');
    const error = form.querySelector('#report-range-error');
    let serverError = error?.textContent.trim() || '';
    let hasInteracted = false;
    let anchor = fields.from.input.value ? 'from' : 'to';
    let openField = null;
    const cursors = { from: null, to: null };

    const setSubmitDisabled = (disabled) => {
        if (submit) submit.disabled = disabled;
    };

    const validateRange = () => {
        const fromValue = fields.from.input.value;
        const toValue = fields.to.input.value;
        let message = '';
        if (!fromValue || !toValue) message = serverError || (hasInteracted ? 'Choose both dates to continue.' : '');
        else if (utcDay(toValue) < utcDay(fromValue)) message = 'The end date must be on or after the start date.';
        else if (utcDay(toValue) > utcDay(shiftMonths(fromValue, 3))) message = 'Choose a custom report range of three months or less.';

        if (error) {
            error.textContent = message;
            error.hidden = !message;
        }
        setSubmitDisabled(!fromValue || !toValue || Boolean(message));
    };

    const updateTriggers = () => {
        for (const key of ['from', 'to']) {
            fields[key].trigger.querySelector('[data-date-value]').textContent = formattedDate(fields[key].input.value);
        }
    };

    const close = (returnFocus = false) => {
        if (!openField) return;
        const key = openField;
        const calendar = fields[key].calendar;
        calendar.hidden = true;
        if (calendar.parentElement !== fields[key].container) fields[key].container.append(calendar);
        calendar.classList.remove('report-date-calendar-viewport');
        for (const property of ['position', 'top', 'left', 'right', 'width', 'max-width']) calendar.style.removeProperty(property);
        fields[key].trigger.setAttribute('aria-expanded', 'false');
        openField = null;
        if (returnFocus) fields[key].trigger.focus();
    };

    const positionOpenCalendar = () => {
        if (!openField) return;
        const field = fields[openField];
        const calendar = field.calendar;
        const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
        const isMobile = window.matchMedia('(max-width: 700px)').matches;
        if (calendar.parentElement !== document.body) document.body.append(calendar);
        calendar.classList.add('report-date-calendar-viewport');
        const triggerRect = field.trigger.getBoundingClientRect();
        const width = Math.max(0, Math.min(isMobile ? viewportWidth - 24 : 320, viewportWidth - 24));
        const preferredLeft = isMobile ? 12 : openField === 'to' ? triggerRect.right - width : triggerRect.left;
        const left = Math.max(12, Math.min(preferredLeft, viewportWidth - width - 12));
        calendar.style.position = 'fixed';
        calendar.style.left = `${left}px`;
        calendar.style.right = 'auto';
        calendar.style.width = `${width}px`;
        calendar.style.maxWidth = 'calc(100vw - 24px)';
        const calendarHeight = calendar.getBoundingClientRect().height;
        const below = triggerRect.bottom + 8;
        const top = below + calendarHeight <= window.innerHeight - 8
            ? below
            : Math.max(8, triggerRect.top - calendarHeight - 8);
        calendar.style.top = `${top}px`;
    };

    const boundsFor = (key) => {
        if (key === 'to' && fields.from.input.value) {
            return { min: fields.from.input.value, max: shiftMonths(fields.from.input.value, 3) };
        }
        if (key === 'from' && fields.to.input.value) {
            return { min: earliestStartDay(fields.to.input.value), max: fields.to.input.value };
        }
        return { min: '', max: '' };
    };

    const render = (key) => {
        const field = fields[key];
        const cursor = cursors[key] || toUTCDate(field.input.value || todayISO());
        const year = cursor.getUTCFullYear();
        const monthIndex = cursor.getUTCMonth();
        const monthLabel = monthFormatter.format(cursor);
        const { min, max } = boundsFor(key);
        const selected = field.input.value;
        const cells = buildMonthCells(year, monthIndex).map((date) => {
            if (!date) return '<span class="report-calendar-blank" aria-hidden="true"></span>';
            const disabled = (min && utcDay(date) < utcDay(min)) || (max && utcDay(date) > utcDay(max));
            const isSelected = date === selected;
            const today = date === todayISO();
            return `<button type="button" class="report-calendar-day${isSelected ? ' selected' : ''}${today ? ' today' : ''}" data-date-value="${date}" aria-label="${accessibleDateFormatter.format(toUTCDate(date))}" aria-pressed="${isSelected}"${disabled ? ' disabled' : ''}>${Number(date.slice(-2))}</button>`;
        }).join('');

        field.calendar.innerHTML = `<div class="report-calendar-head"><button type="button" data-month-shift="-1" aria-label="Previous month"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 4.5-5 5.5 5 5.5"/></svg></button><strong aria-live="polite">${monthLabel}</strong><button type="button" data-month-shift="1" aria-label="Next month"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m7.5 4.5 5 5.5-5 5.5"/></svg></button><button type="button" data-calendar-close aria-label="Close calendar"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15"/></svg></button></div><div class="report-calendar-week" aria-hidden="true">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => `<span>${day}</span>`).join('')}</div><div class="report-calendar-days">${cells}</div><div class="report-calendar-foot"><button type="button" data-select-today${((min && utcDay(todayISO()) < utcDay(min)) || (max && utcDay(todayISO()) > utcDay(max))) ? ' disabled' : ''}>Today</button><span>${min && max ? 'Up to 3 months' : 'Choose a date'}</span></div>`;
        field.calendar.setAttribute('aria-label', `Choose ${key === 'from' ? 'start' : 'end'} date`);
    };

    const open = (key) => {
        close();
        anchor = key;
        openField = key;
        cursors[key] = toUTCDate(fields[key].input.value || (key === 'to' && fields.from.input.value ? shiftMonths(fields.from.input.value, 3) : todayISO()));
        render(key);
        fields[key].calendar.hidden = false;
        fields[key].trigger.setAttribute('aria-expanded', 'true');
        positionOpenCalendar();
        const selectedDay = fields[key].calendar.querySelector('.report-calendar-day.selected:not(:disabled)');
        const firstEnabledDay = fields[key].calendar.querySelector('.report-calendar-day:not(:disabled)');
        (selectedDay || firstEnabledDay)?.focus();
    };

    const handleSelect = (key, value) => {
        serverError = '';
        hasInteracted = true;
        fields[key].input.value = value;
        if (key === 'from') {
            const latestTo = shiftMonths(value, 3);
            if (!fields.to.input.value || utcDay(fields.to.input.value) < utcDay(value) || utcDay(fields.to.input.value) > utcDay(latestTo)) {
                fields.to.input.value = latestTo;
            }
        } else if (!fields.from.input.value || utcDay(value) < utcDay(fields.from.input.value) || utcDay(value) > utcDay(shiftMonths(fields.from.input.value, 3))) {
            fields.from.input.value = earliestStartDay(value);
        }
        updateTriggers();
        validateRange();
        close(true);
    };

    for (const key of ['from', 'to']) {
        fields[key].trigger.addEventListener('click', () => open(key));
        fields[key].calendar.addEventListener('click', (event) => {
            if (event.target.closest('[data-calendar-close]')) {
                close(true);
                return;
            }
            const day = event.target.closest('[data-date-value]');
            if (day && !day.disabled) {
                handleSelect(key, day.dataset.dateValue);
                return;
            }
            const shift = event.target.closest('[data-month-shift]');
            if (shift) {
                const cursor = cursors[key];
                cursors[key] = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + Number(shift.dataset.monthShift), 1));
                render(key);
                fields[key].calendar.querySelector(`[data-month-shift="${shift.dataset.monthShift}"]`).focus();
                return;
            }
            if (event.target.closest('[data-select-today]')) handleSelect(key, todayISO());
        });
        fields[key].calendar.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            }
        });
    }

    document.addEventListener('pointerdown', (event) => {
        if (openField && !root.contains(event.target) && !fields[openField].calendar.contains(event.target)) close();
    });
    window.addEventListener('resize', positionOpenCalendar);
    window.addEventListener('scroll', positionOpenCalendar, true);
    form.addEventListener('submit', (event) => {
        validateRange();
        if (submit?.disabled) event.preventDefault();
    });

    updateTriggers();
    validateRange();
}

if (typeof window !== 'undefined') window.mountReportDateCalendars = mountReportDateCalendars;
