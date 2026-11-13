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
  <label class="home-search-field"><span>Check-in date</span><input type="date" name="check_in"></label>
  <label class="home-search-field"><span>Stay length</span><select name="stay" data-home-stay><option value="day" data-mode="dates">1 day stay</option><option value="48" data-mode="dates">2 day stay</option><option value="6" data-mode="hourly">6 hours</option></select></label>
  <label class="home-search-field"><span>Check-in time</span><select name="check_in_time"><option value="12:00">12:00 PM</option></select></label>
  <label class="home-search-field"><span>Guests</span><select name="guests"><option value="">Any guests</option><option value="2">2+ guests</option></select></label>
  <label class="home-search-field"><span>Sort by price</span><select name="sort"><option value="recommended">Recommended</option><option value="price_asc">Lowest price first</option><option value="price_desc">Highest price first</option></select></label>
  <button type="submit">Check availability</button>
</form>`;

test('homepage booking search submits the selected filters and adapts to mobile', async () => {
    assert.match(template, /class="home-booking-search"/);
    assert.doesNotMatch(template, /home-room-picker|home-calendar/);
    assert.match(stylesheet, /\.home-booking-search/);

    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${baseStylesheet}</style><style>${stylesheet}</style></head><body>${fixture}</body>`);
        await page.addScriptTag({ path: fileURLToPath(script) });

        await page.locator('[name="check_in"]').fill('2026-12-12');
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
        assert.ok(mobile.width <= mobile.viewport, 'mobile search should not overflow horizontally');
        assert.equal(mobile.columns, 2);
        assert.deepEqual(Object.fromEntries(mobile.fields), {
            mode: 'hourly', check_in: '2026-12-12', stay: '6', check_in_time: '12:00', guests: '2', sort: 'price_asc',
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
            const control = field.querySelector('input, select');
            const styles = getComputedStyle(control);
            return {
                height: field.getBoundingClientRect().height,
                appearance: styles.appearance,
                backgroundImage: styles.backgroundImage,
                color: styles.color,
            };
        }));

        assert.ok(light.every(field => field.height >= 58), 'each selector should retain a comfortable touch target');
        assert.equal(light.slice(1).every(field => field.appearance === 'none'), true, 'selects should use the shared custom chevron instead of mismatched browser arrows');
        assert.equal(light.slice(1).every(field => field.backgroundImage !== 'none'), true, 'each select should show a visible, consistent chevron');

        await page.evaluate(() => document.body.classList.add('dark-mode'));
        const dark = await page.locator('.home-search-field input, .home-search-field select').evaluateAll(controls => controls.map(control => ({
            colorScheme: getComputedStyle(control).colorScheme,
            color: getComputedStyle(control).color,
        })));
        assert.ok(dark.every(control => control.colorScheme === 'dark'), 'native picker surfaces should follow dark mode');
        assert.ok(dark.every(control => control.color !== 'rgb(48, 37, 29)'), 'dark-mode values should not keep the light-mode ink color');
        await browser.close();
    } finally {
        await browser.close();
    }
});
