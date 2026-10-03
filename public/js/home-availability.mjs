// Booking timestamps currently encode the property's wall-clock time in UTC.
// Match the booking picker; do not shift these existing values a second time.
const dateLabel = date => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const timeLabel = date => date.toLocaleTimeString('en-US', { hour: 'numeric', ...(date.getUTCMinutes() ? { minute: '2-digit' } : {}), timeZone: 'UTC' });
const dayKey = date => date.toISOString().slice(0, 10);

export function occupiedDate(date, slots) {
    const start = Date.parse(`${date}T00:00:00Z`);
    const end = start + 86400000;
    return slots.some(slot => Date.parse(slot.start) < end && Date.parse(slot.end) > start);
}

export function formatBookedSlot(slot) {
    const start = new Date(slot.start);
    const end = new Date(slot.end);
    if (!Number.isFinite(start.getTime()) || end <= start) return 'Reserved time';
    if (slot.booking_type === 'dates' && slot.nights > 0) {
        return `${dateLabel(start)}–${dateLabel(end)} · ${slot.nights} night${slot.nights === 1 ? '' : 's'}`;
    }
    const hours = Math.round((end - start) / 3600000 * 100) / 100;
    const range = dayKey(start) === dayKey(end)
        ? `${dateLabel(start)} · ${timeLabel(start)}–${timeLabel(end)}`
        : `${dateLabel(start)}, ${timeLabel(start)}–${dateLabel(end)}, ${timeLabel(end)}`;
    return `${range} · ${hours} hour${hours === 1 ? '' : 's'}`;
}

function initCalendar() {
    const select = document.getElementById('home-room-calendar');
    const calendar = document.getElementById('home-calendar');
    const days = document.getElementById('home-days');
    const month = document.getElementById('home-month');
    const list = document.getElementById('home-booked-list');
    if (!select || !calendar || !days || !month || !list) return;

    const propertyDate = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    let cursor = new Date(`${propertyDate}T00:00:00Z`);
    cursor.setUTCDate(1);
    let slots = [];
    let requestId = 0;
    let loadedRoom = '';
    let availabilityState = 'loading';

    const message = text => {
        const item = document.createElement('span');
        item.textContent = text;
        list.replaceChildren(item);
    };
    const render = () => {
        days.replaceChildren();
        month.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });
        if (availabilityState !== 'ready') {
            message(availabilityState === 'error' ? 'Availability could not be loaded. Select the room again to retry.' : 'Checking reserved dates and hours…');
            return;
        }
        for (let i = 0; i < cursor.getUTCDay(); i++) {
            const blank = document.createElement('span');
            blank.className = 'home-blank';
            days.append(blank);
        }
        const lastDay = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + 1, 0)).getUTCDate();
        for (let n = 1; n <= lastDay; n++) {
            const date = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth(), n));
            const booked = occupiedDate(dayKey(date), slots);
            const previous = new Date(date.getTime() - 86400000);
            const next = new Date(date.getTime() + 86400000);
            const day = document.createElement('span');
            day.className = `home-day${booked ? ` booked${occupiedDate(dayKey(previous), slots) ? '' : ' booked-start'}${occupiedDate(dayKey(next), slots) ? '' : ' booked-end'}` : ''}`;
            day.textContent = String(n);
            day.setAttribute('aria-label', `${dateLabel(date)}${booked ? ': reserved hours; see the times below' : ': no reserved hours recorded'}`);
            days.append(day);
        }
        list.replaceChildren();
        if (!slots.length) {
            message('No reserved times recorded for this room. Availability is checked again when you reserve.');
            return;
        }
        slots.forEach(slot => {
            const item = document.createElement('span');
            item.textContent = `Reserved: ${formatBookedSlot(slot)}`;
            list.append(item);
        });
    };
    const load = async () => {
        const id = ++requestId;
        const room = select.value;
        const url = select.selectedOptions[0]?.dataset.url;
        if (!url) { calendar.hidden = true; return; }
        calendar.hidden = false;
        if (loadedRoom !== room) {
            slots = [];
            availabilityState = 'loading';
            render();
        }
        calendar.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Availability unavailable');
            const data = await response.json();
            if (!Array.isArray(data.slots)) throw new Error('Availability unavailable');
            if (id !== requestId) return;
            slots = data.slots;
            loadedRoom = room;
            availabilityState = 'ready';
            render();
        } catch {
            if (id !== requestId) return;
            slots = [];
            loadedRoom = '';
            availabilityState = 'error';
            render();
        } finally {
            if (id === requestId) calendar.setAttribute('aria-busy', 'false');
        }
    };
    select.addEventListener('change', load);
    document.getElementById('home-prev').addEventListener('click', () => { cursor.setUTCMonth(cursor.getUTCMonth() - 1); render(); });
    document.getElementById('home-next').addEventListener('click', () => { cursor.setUTCMonth(cursor.getUTCMonth() + 1); render(); });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCalendar);
    else initCalendar();
}
