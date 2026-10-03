import test from 'node:test';
import assert from 'node:assert/strict';
import { occupiedDate, formatBookedSlot } from '../../public/js/home-availability.mjs';

test('a same day hourly stay shows its hours and occupies that date', () => {
    const slot = { start: '2026-10-05T13:00:00+00:00', end: '2026-10-05T19:00:00+00:00' };
    assert.equal(occupiedDate('2026-10-05', [slot]), true);
    assert.equal(occupiedDate('2026-10-06', [slot]), false);
    assert.equal(formatBookedSlot(slot), 'Oct 5, 2026 · 1 PM–7 PM · 6 hours');
});

test('cross midnight hourly reservations show both dates and exclude the end boundary', () => {
    const slot = { start: '2026-10-05T23:00:00Z', end: '2026-10-06T05:00:00Z' };
    assert.equal(occupiedDate('2026-10-05', [slot]), true);
    assert.equal(occupiedDate('2026-10-06', [slot]), true);
    assert.equal(formatBookedSlot(slot), 'Oct 5, 2026, 11 PM–Oct 6, 2026, 5 AM · 6 hours');
    assert.equal(occupiedDate('2026-10-06', [{ start: '2026-10-05T12:00:00Z', end: '2026-10-06T00:00:00Z' }]), false);
});

test('overnight reservations retain a meaningful night count', () => {
    const slot = { start: '2026-10-05T00:00:00Z', end: '2026-10-07T00:00:00Z', booking_type: 'dates', nights: 2 };
    assert.equal(formatBookedSlot(slot), 'Oct 5, 2026–Oct 7, 2026 · 2 nights');
    assert.equal(occupiedDate('2026-10-07', [slot]), false);
});
