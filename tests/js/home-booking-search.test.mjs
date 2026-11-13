import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';

const root = new URL('../../', import.meta.url);
const template = await readFile(new URL('resources/views/home.blade.php', root), 'utf8');
const stylesheet = await readFile(new URL('public/css/home-premium.css', root), 'utf8');
const baseStylesheet = await readFile(new URL('public/css/site.css', root), 'utf8');
const script = new URL('public/js/home-booking-search.js', root);

const fixture = `<form class="home-booking-search" action="/book">
  <input type="hidden" name="mode" value="dates">
  <div class="home-search-field home-search-date" data-home-date-picker><label for="home-date-trigger">Check-in date</label><input id="home-date" type="hidden" name="check_in" min="2026-11-13" data-home-date><button id="home-date-trigger" type="button" class="home-search-trigger home-date-trigger" data-home-date-trigger aria-haspopup="dialog" aria-expanded="false"><span data-home-date-value>Choose date</span><svg aria-hidden="true"></svg></button><div class="home-date-calendar" data-home-date-calendar hidden></div></div>
  <div class="home-search-field home-custom-select" data-home-select><label for="home-stay-trigger">Stay length</label><select class="home-native-select" id="home-stay" name="stay" data-home-stay><option value="3" data-mode="hourly" data-display="3 hours">3 hours · air-conditioned</option><option value="6" data-mode="hourly" data-display="6 hours">6 hours · air-conditioned</option><option value="22" data-mode="hourly" data-display="22 hours">22 hours · Fan ₱450 / Air-conditioned ₱850</option></select><button id="home-stay-trigger" type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false"><span data-home-select-value></span><svg aria-hidden="true"></svg></button><div class="home-select-list" data-home-select-list role="listbox" hidden></div></div>
  <div class="home-search-field home-custom-select" data-home-select><label for="home-time">Check-in time</label><select class="home-native-select" id="home-time" name="check_in_time"><option value="12:00">12:00 PM</option></select><button type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false"><span data-home-select-value></span><svg aria-hidden="true"></svg></button><div class="home-select-list" data-home-select-list role="listbox" hidden></div></div>
  <div class="home-search-field home-custom-select" data-home-select><label for="home-guests">Guests</label><select class="home-native-select" id="home-guests" name="guests"><option value="">Any guests</option><option value="2">2+ guests</option></select><button type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false"><span data-home-select-value></span><svg aria-hidden="true"></svg></button><div class="home-select-list" data-home-select-list role="listbox" hidden></div></div>
  <div class="home-search-field home-custom-select" data-home-select><label for="home-sort">Sort by price</label><select class="home-native-select" id="home-sort" name="sort"><option value="recommended">Recommended</option><option value="price_asc">Lowest price first</option><option value="price_desc">Highest price first</option></select><button type="button" class="home-search-trigger" data-home-select-trigger aria-haspopup="listbox" aria-expanded="false"><span data-home-select-value></span><svg aria-hidden="true"></svg></button><div class="home-select-list" data-home-select-list role="listbox" hidden></div></div>
  <button type="submit">Check availability</button>
</form>`;

test('homepage booking search submits the selected filters and adapts to mobile', async () => {
    assert.match(template, /class="home-booking-search"/);
    assert.doesNotMatch(template, /home-room-picker/);
    assert.doesNotMatch(template, /<input type="date" name="check_in"/);
    assert.match(template, /data-home-date-trigger/);
    assert.match(stylesheet, /\.home-booking-search/);

    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${baseStylesheet}</style><style>${stylesheet}</style></head><body>${fixture}</body>`);
        await page.addScriptTag({ path: fileURLToPath(script) });

        await page.locator('[data-home-date-trigger]').click();
        await page.locator('[data-calendar-date="2026-11-18"]').click();
        await page.locator('[name="stay"]').selectOption('6');
        await page.locator('[name="guests"]').selectOption('2');
        await page.locator('[name="sort"]').selectOption('price_asc');
        const mobile = await page.evaluate(() => ({
            mode: document.querySelector('[name="mode"]').value,
            width: document.body.scrollWidth,
            viewport: innerWidth,
            columns: getComputedStyle(document.querySelector('.home-booking-search')).gridTemplateColumns.split(' ').length,
            fields: [...new FormData(document.querySelector('.home-booking-search')).entries()],
        }));

        assert.equal(mobile.mode, 'hourly');
        assert.ok(mobile.width <= mobile.viewport, `mobile search should not overflow horizontally: ${JSON.stringify(mobile)}`);
        assert.equal(mobile.columns, 2);
        assert.deepEqual(Object.fromEntries(mobile.fields), {
            mode: 'hourly', check_in: '2026-11-18', stay: '6', check_in_time: '12:00', guests: '2', sort: 'price_asc',
        });

        await page.setViewportSize({ width: 1440, height: 900 });
        assert.equal(await page.locator('.home-booking-search').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length), 6);
        await browser.close();
    } finally {
        await browser.close();
    }
});

test('homepage selectors share a clear, themed control treatment', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${baseStylesheet}</style><style>${stylesheet}</style></head><body>${fixture}</body>`);
        const light = await page.locator('.home-search-field').evaluateAll(fields => fields.map(field => {
            const control = field.querySelector('[data-home-date-trigger], [data-home-select-trigger]');
            const styles = getComputedStyle(control);
            return {
                height: field.getBoundingClientRect().height,
                appearance: styles.appearance,
                textOverflow: getComputedStyle(control.querySelector('span')).textOverflow,
                hasIcon: Boolean(control.querySelector('svg')),
                color: styles.color,
            };
        }));

        assert.ok(light.every(field => field.height >= 58), 'each selector should retain a comfortable touch target');
        assert.equal(light.slice(1).every(field => field.hasIcon), true, 'each visible selector should show an authored chevron icon');
        assert.equal(light.slice(1).every(field => field.textOverflow === 'ellipsis'), true, `long selected values should truncate cleanly on narrow screens: ${JSON.stringify(light)}`);

        await page.evaluate(() => document.body.classList.add('dark-mode'));
        const dark = await page.locator('.home-search-trigger').evaluateAll(controls => controls.map(control => ({
            color: getComputedStyle(control).color,
            surface: getComputedStyle(control.closest('.home-search-field')).backgroundColor,
        })));
        assert.ok(dark.every(control => control.color === 'rgb(255, 244, 232)'), 'dark-mode selector values should use readable warm-white text');
        assert.ok(dark.every(control => control.surface !== 'rgba(0, 0, 0, 0)'), 'dark-mode selector fields should have opaque themed surfaces');
        await browser.close();
    } finally {
        await browser.close();
    }
});

test('homepage date and select popovers are branded, keyboard accessible, and update submitted values', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${baseStylesheet}</style><style>${stylesheet}</style></head><body>${fixture}</body>`);
        await page.addScriptTag({ path: fileURLToPath(script) });

        const nativeDateInputs = await page.locator('.home-booking-search input[type="date"]').count();
        assert.equal(nativeDateInputs, 0, 'the homepage should not invoke the browser-native blue date popup');

        const dateTrigger = page.locator('[data-home-date-trigger]');
        await page.locator('label[for="home-date-trigger"]').click();
        assert.equal(await page.locator('[data-home-date-calendar]').getAttribute('hidden'), null, 'the branded calendar should open');
        const calendarBounds = await page.locator('[data-home-date-calendar]').boundingBox();
        assert.ok(calendarBounds.x >= 0 && calendarBounds.x + calendarBounds.width <= 390, `calendar should fit the mobile viewport: ${JSON.stringify(calendarBounds)}`);
        assert.equal(await page.locator('[data-calendar-date="2026-11-12"]').isDisabled(), true, 'dates before the property-local minimum should be disabled');
        await page.locator('[data-calendar-date="2026-11-18"]').click();
        assert.equal(await page.locator('[name="check_in"]').inputValue(), '2026-11-18');
        assert.equal(await page.locator('[data-home-date-value]').textContent(), 'Nov 18, 2026');

        const stayTrigger = page.locator('[data-home-select-trigger]').first();
        await page.locator('label[for="home-stay-trigger"]').click();
        assert.equal(await page.locator('[data-home-select-list]').first().getAttribute('hidden'), null, 'clicking the selector label should open the menu');
        await page.locator('[role="option"][data-value="22"]').click();
        assert.equal(await page.locator('[name="stay"]').inputValue(), '22');
        assert.equal(await page.locator('[data-home-select-value]').first().textContent(), '22 hours');
        assert.notEqual(await page.locator('[data-home-select-list]').first().getAttribute('hidden'), null, 'selection should close the custom option list');

        await page.locator('[data-home-select-trigger]').nth(1).click();
        const timeListBounds = await page.locator('[data-home-select-list]').nth(1).boundingBox();
        assert.ok(timeListBounds.x >= 0 && timeListBounds.x + timeListBounds.width <= 390, `right-column selector menus should fit the mobile viewport: ${JSON.stringify(timeListBounds)}`);
        await page.keyboard.press('Escape');

        await page.locator('[data-home-select-trigger]').last().focus();
        await page.keyboard.press('ArrowDown');
        assert.equal(await page.locator('[data-home-select-list]').last().getAttribute('hidden'), null, 'keyboard users should be able to open a selector');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-home-select-trigger]').last().getAttribute('aria-expanded'), 'false');
    } finally {
        await browser.close();
    }
});
