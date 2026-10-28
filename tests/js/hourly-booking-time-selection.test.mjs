import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const view = await readFile(new URL('../../resources/views/bookings/create.blade.php', import.meta.url), 'utf8');
const script = [...view.matchAll(/<script>([\s\S]*?)<\/script>/g)][0]?.[1];
assert.ok(script, 'booking form should expose its availability behavior');

function hourlyBookingScript(slots) {
    const start = script.indexOf('    const closeOpenPickers');
    const end = script.indexOf('    const ranges = @json($blockedRanges);');
    assert.ok(start >= 0 && end > start, 'booking script should retain its hourly availability section');

    return script.slice(start, end)
        .replace('@json($hourlyBlockedSlots)', JSON.stringify(slots))
        .replace("{{ $room->rental_hours ? $room->price_per_night : 'null' }}", '450')
        .replace('{{ $room->rental_hours ?: \'null\' }}', '6')
        .replace('{{ $room->price_per_night / 24 }}', '18.75')
        .replace("@json(route('rooms.availability', $room))", '"/rooms/test/availability"');
}

test('a selected date with no valid start time cannot keep a booked time selected', async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ timezoneId: 'Asia/Manila' });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    const futureDate = new Date();
    const lastDayOfMonth = new Date(futureDate.getFullYear(), futureDate.getMonth() + 1, 0).getDate();
    futureDate.setDate(futureDate.getDate() < lastDayOfMonth ? futureDate.getDate() + 1 : futureDate.getDate());
    const date = `${futureDate.getFullYear()}-${String(futureDate.getMonth() + 1).padStart(2, '0')}-${String(futureDate.getDate()).padStart(2, '0')}`;
    const nextDate = new Date(futureDate);
    nextDate.setDate(nextDate.getDate() + 1);
    const endDate = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}-${String(nextDate.getDate()).padStart(2, '0')}`;
    const slots = [{ start: `${date}T06:00:00+00:00`, end: `${endDate}T00:00:00+00:00` }];

    try {
        await page.setContent(`<label class="clean-select"><input id="hourly-hours" type="hidden" value="48"><span class="selector-display"></span></label>
            <input id="hourly-date" value="${date}"><button id="hourly-date-trigger"></button>
            <section id="hourly-calendar" hidden><div id="hourly-calendar-days"></div><b id="hourly-calendar-month"></b><button id="hourly-calendar-prev"></button><button id="hourly-calendar-next"></button></section>
            <input id="hourly-time" value="06:00"><button id="hourly-time-trigger"></button>
            <section id="hourly-time-menu"><button data-time="06:00">6 AM</button><button data-time="12:00">12 PM</button><button data-time="18:00">6 PM</button></section>
            <div id="hourly-booked-summary"></div><p id="hourly-total"></p>`);
        await page.evaluate(() => { window.setInterval = () => 0; });
        await page.addScriptTag({ content: `document.addEventListener('DOMContentLoaded', () => {${hourlyBookingScript(slots)}});` });
        await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

        assert.equal(await page.locator('#hourly-time').inputValue(), '', `a stale booked time must not remain submitted; options=${JSON.stringify(await page.locator('[data-time]').evaluateAll(options => options.map(option => [option.dataset.time, option.disabled])))}; summary=${await page.locator('#hourly-booked-summary').innerText()}; errors=${pageErrors}`);
        assert.equal(await page.locator('#hourly-time-trigger').innerText(), 'Choose time');
        assert.equal(await page.locator('[data-time="06:00"]').isDisabled(), true);
        assert.equal(await page.locator('[data-time="12:00"]').isDisabled(), true);
        assert.match(await page.locator('#hourly-booked-summary').innerText(), /No check-in times are available for this date and stay length/);

        await page.locator('#hourly-date-trigger').click();
        assert.equal(await page.locator(`.hourly-calendar-day[data-date="${date}"]`).isDisabled(), true);
    } finally {
        await context.close();
        await browser.close();
    }
});
