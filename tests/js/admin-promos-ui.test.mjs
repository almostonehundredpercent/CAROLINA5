import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [adminStyles, promoView] = await Promise.all([
    readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/admin/promos.blade.php', import.meta.url), 'utf8'),
]);
const promoStyles = promoView.match(/<style>([\s\S]*?)<\/style>/)?.[1];
assert.ok(promoStyles, 'the promo screen should define its scoped layout styles');

const markup = `
    <main class="admin-main">
        <div class="promo-layout">
            <section class="panel promo-create-panel">
                <form class="promo-form">
                    <section class="promo-form-section">
                        <div class="promo-form-section-heading"><span class="promo-step">01</span><div><h3>Offer details</h3><p>Clear description</p></div></div>
                        <div class="promo-grid"><label>Promo code<input value="CAROLINA"></label><label>Offer name<input value="Carolina 10% Off"></label></div>
                    </section>
                    <fieldset class="room-fieldset"><legend>Eligible rooms</legend><p>Choose rooms.</p><div class="room-checks"><label><input type="checkbox" checked>Room 1</label><label><input type="checkbox">Room 2</label></div></fieldset>
                </form>
            </section>
            <section class="panel promo-offers-panel"><article class="promo-card"><div class="promo-card-head"><div><span class="promo-code">CAROLINA</span><h3>Carolina 10% Off</h3></div><span class="promo-status">Active</span></div></article></section>
        </div>
    </main>`;

async function render(width, darkMode) {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width, height: 850 } });
    await page.setContent(`<html${darkMode ? ' class="dark-mode"' : ''}><head><style>${adminStyles}\n${promoStyles}</style></head><body>${markup}</body></html>`);
    return { browser, page };
}

test('promo editor has readable tap targets and balanced desktop panels in dark mode', async () => {
    const { browser, page } = await render(1280, true);
    try {
        const layout = await page.locator('.promo-layout').evaluate(element => ({
            columns: getComputedStyle(element).gridTemplateColumns.split(' ').length,
            inputHeight: element.querySelector('input').getBoundingClientRect().height,
            roomTargetHeight: element.querySelector('.room-checks label').getBoundingClientRect().height,
            labelColor: getComputedStyle(element.querySelector('.promo-form label')).color,
        }));
        assert.equal(layout.columns, 2);
        assert.ok(layout.inputHeight >= 46);
        assert.ok(layout.roomTargetHeight >= 46);
        assert.equal(layout.labelColor, 'rgb(241, 227, 213)');
    } finally {
        await browser.close();
    }
});

test('promo editor stacks cleanly on narrow light-mode screens', async () => {
    const { browser, page } = await render(375, false);
    try {
        const layout = await page.evaluate(() => ({
            columns: getComputedStyle(document.querySelector('.promo-layout')).gridTemplateColumns.split(' ').length,
            formColumns: getComputedStyle(document.querySelector('.promo-grid') ?? document.querySelector('.promo-layout')).gridTemplateColumns.split(' ').length,
            labelColor: getComputedStyle(document.querySelector('.promo-form label')).color,
            pageWidth: document.documentElement.scrollWidth,
            viewportWidth: document.documentElement.clientWidth,
        }));
        assert.equal(layout.columns, 1);
        assert.equal(layout.formColumns, 1);
        assert.equal(layout.labelColor, 'rgb(61, 48, 39)');
        assert.ok(layout.pageWidth <= layout.viewportWidth, 'the small screen should not scroll sideways');
    } finally {
        await browser.close();
    }
});
