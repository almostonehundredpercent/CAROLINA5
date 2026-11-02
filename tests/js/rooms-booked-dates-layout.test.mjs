import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [baseStyles, roomsStyles, roomsView] = await Promise.all([
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/rooms/index.blade.php', import.meta.url), 'utf8'),
]);
const availabilityScript = [...roomsView.matchAll(/<script>([\s\S]*?)<\/script>/g)][2]?.[1];
assert.ok(availabilityScript, 'room cards should render the interactive booked-date calendar');

const card = (number) => `<article class="room-card rooms-card">
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="Room ${number}">
    <div class="room-card-body"><span>Air-conditioned room · 2 guests</span><h3>Room ${number}</h3>
        <div class="room-availability-line"><span class="room-availability-badge is-available">Available now</span></div>
        <section class="room-card-availability" data-availability-url="/rooms/room-${number}/availability">
            <button class="room-availability-toggle" type="button" aria-expanded="false" aria-controls="calendar-${number}"><span><b>View booked dates</b><small>Unavailable dates are marked in red.</small></span><span aria-hidden="true">＋</span></button>
            <div class="room-availability-panel" id="calendar-${number}" hidden><div class="room-availability-calendar">
                <div class="room-availability-head"><button type="button" data-month-prev aria-label="Previous month">‹</button><b data-month-label></b><button type="button" data-month-next aria-label="Next month">›</button></div>
                <div class="room-availability-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                <div class="room-availability-days" data-calendar-days></div><div class="room-availability-detail" aria-live="polite">Choose a date to see its booked times.</div>
                <p class="room-availability-legend"><i aria-hidden="true"></i>Booked or unavailable</p>
            </div></div>
        </section>
        <small class="room-promo-badge">Carolina 10% Off · CAROLINA</small>
        <p>A comfortable private room for two guests with a 12-hour daytime stay.</p>
        <div class="rooms-card-footer"><strong>₱450 <small>per stay</small></strong><a class="button small" href="#room-${number}">View room</a></div>
    </div>
</article>`;

test('opening a booked-dates calendar does not stretch the other room cards in its row', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1200, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, timezoneId: 'Asia/Singapore' });
            try {
                await page.route('**/rooms/*/availability', route => route.fulfill({
                    contentType: 'application/json',
                    body: JSON.stringify({ ranges: [], slots: [] }),
                }));
                await page.setContent(`<html><head><base href="http://localhost/"><style>${baseStyles}\n${roomsStyles}</style></head><body><div class="room-grid">${card(1)}${card(2)}${card(3)}</div></body></html>`);
                await page.addScriptTag({ content: availabilityScript });
                await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

                const cards = page.locator('.rooms-card');
                const before = await cards.evaluateAll(items => items.map(item => item.getBoundingClientRect().height));
                await cards.nth(1).locator('.room-availability-toggle').click();
                await cards.nth(1).locator('.room-availability-panel:not([hidden])').waitFor();
                const after = await cards.evaluateAll(items => items.map(item => item.getBoundingClientRect().height));

                assert.ok(after[1] > before[1] + 150, `open card should expand to contain its calendar: before=${before}, after=${after}`);
                assert.ok(after[0] <= before[0] + 1, `first neighboring card should retain its own height: before=${before}, after=${after}`);
                assert.ok(after[2] <= before[2] + 1, `third neighboring card should retain its own height: before=${before}, after=${after}`);

                await cards.nth(1).locator('.room-availability-toggle').click();
                const closed = await cards.evaluateAll(items => items.map(item => item.getBoundingClientRect().height));
                assert.deepEqual(closed.map(Math.round), before.map(Math.round), 'closing the calendar should restore the original card row heights');
            } finally {
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
