import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [baseStyles, roomsStyles, roomsView] = await Promise.all([
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/rooms/index.blade.php', import.meta.url), 'utf8'),
]);
const scripts = [...roomsView.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]);
const availabilityScript = scripts[2];
assert.ok(availabilityScript, 'room cards should render the interactive booked-date calendar');

function propertyDateAfter(days) {
    const values = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Singapore', year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date()).map(part => [part.type, part.value]));
    const date = new Date(Date.UTC(Number(values.year), Number(values.month) - 1, Number(values.day) + days));

    return date.toISOString().slice(0, 10);
}

function plusDays(value, days) {
    const date = new Date(`${value}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

function roomCard(slug, id) {
    return `<article class="room-card-availability" data-availability-url="/rooms/${slug}/availability">
        <button class="room-availability-toggle" type="button" aria-expanded="false" aria-controls="${id}">
            <span><b>View booked dates</b><small>Unavailable dates are marked in red.</small></span><span aria-hidden="true">＋</span>
        </button>
        <div class="room-availability-panel" id="${id}" hidden><div class="room-availability-calendar">
            <div class="room-availability-head"><button type="button" data-month-prev aria-label="Previous month">‹</button><b data-month-label></b><button type="button" data-month-next aria-label="Next month">›</button></div>
            <div class="room-availability-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="room-availability-days" data-calendar-days></div>
            <div class="room-availability-detail" aria-live="polite">Choose a date to see availability.</div>
            <p class="room-availability-legend"><i aria-hidden="true"></i>Booked or unavailable</p>
        </div></div>
    </article>`;
}

test('booked-date toggle uses the same typeface as the room card copy', async () => {
    const browser = await chromium.launch({ headless: true });

    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            try {
                await page.setContent(`<html><head><style>${baseStyles}\n${roomsStyles}</style></head><body>
                    <article class="room-card rooms-card"><div class="room-card-body">
                        <p>Room description in the established typeface.</p>
                        ${roomCard('room-one', 'room-calendar-one')}
                    </div></article>
                </body></html>`);

                const fonts = await page.locator('.room-availability-toggle').evaluate(button => ({
                    button: getComputedStyle(button).fontFamily,
                    label: getComputedStyle(button.querySelector('b')).fontFamily,
                    helper: getComputedStyle(button.querySelector('small')).fontFamily,
                    roomCopy: getComputedStyle(document.querySelector('.room-card-body > p')).fontFamily,
                }));

                assert.equal(fonts.button, fonts.roomCopy, `toggle button should match the room copy at ${width}px`);
                assert.equal(fonts.label, fonts.roomCopy, `toggle label should match the room copy at ${width}px`);
                assert.equal(fonts.helper, fonts.roomCopy, `toggle helper text should match the room copy at ${width}px`);
            } finally {
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

test('each room calendar marks its own booked dates red, including cross-midnight hourly stays', async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, timezoneId: 'Asia/Singapore' });
    const page = await context.newPage();
    const allDayStart = propertyDateAfter(5);
    const allDayEnd = plusDays(allDayStart, 2);
    const hourlyStart = propertyDateAfter(9);
    const hourlyEnd = plusDays(hourlyStart, 1);

    try {
        await page.route('**/rooms/*/availability', async route => {
            const firstRoom = route.request().url().includes('room-one');
            const json = firstRoom
                ? { ranges: [{ start: allDayStart, end: allDayEnd }], slots: [{ start: `${allDayStart}T00:00:00+00:00`, end: `${allDayEnd}T00:00:00+00:00` }] }
                : { ranges: [], slots: [{ start: `${hourlyStart}T18:00:00+00:00`, end: `${hourlyEnd}T02:00:00+00:00` }] };

            await route.fulfill({ contentType: 'application/json', body: JSON.stringify(json) });
        });
        await page.setContent(`<html><head><base href="http://localhost/"><style>${baseStyles}\n${roomsStyles}</style></head><body>
            ${roomCard('room-one', 'room-calendar-one')}${roomCard('room-two', 'room-calendar-two')}
        </body></html>`);
        await page.addScriptTag({ content: availabilityScript });
        await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

        for (const [slug, id, targetDate, expectedColor] of [
            ['room-one', 'room-calendar-one', allDayStart, 'rgb(198, 62, 54)'],
            ['room-two', 'room-calendar-two', hourlyStart, 'rgb(198, 62, 54)'],
        ]) {
            const card = page.locator(`[data-availability-url="/rooms/${slug}/availability"]`);
            await card.locator('.room-availability-toggle').click();
            const targetMonth = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Singapore', month: 'long', year: 'numeric' })
                .format(new Date(`${targetDate}T12:00:00+08:00`));
            for (let attempt = 0; attempt < 2 && (await card.locator('[data-month-label]').textContent()) !== targetMonth; attempt++) {
                await card.locator('[data-month-next]').click();
            }

            const day = card.locator(`.room-availability-day[data-date="${targetDate}"]`);
            await day.waitFor();
            assert.match(await day.getAttribute('class'), /\bbooked\b/, `${slug} ${targetDate} in ${await card.locator('[data-month-label]').textContent()} should be blocked; detail=${await card.locator('.room-availability-detail').innerText()}`);
            assert.equal(await day.evaluate(element => getComputedStyle(element).backgroundColor), expectedColor);
        }

        const crossMidnightDate = page.locator(`[data-availability-url="/rooms/room-two/availability"] .room-availability-day[data-date="${hourlyEnd}"]`);
        assert.match(await crossMidnightDate.getAttribute('class'), /\bbooked\b/, 'the stay should also mark its local check-out date when occupied past midnight');
    } finally {
        await context.close();
        await browser.close();
    }
});
