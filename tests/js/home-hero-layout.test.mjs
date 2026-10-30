import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await Promise.all([
    'site.css',
    'responsive.css',
    'home-premium.css',
    'navigation-glass.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));

const pageMarkup = `
    <header class="nav nav--over-hero"></header>
    <main>
        <section class="hero home-hero">
            <div class="hero-copy">
                <span class="eyebrow">CAROLINA · TABACO CITY</span>
                <h1>Stay well.<br>Spend less.</h1>
                <p>Affordable bed &amp; breakfast and boarding-house stays in Tabaco City.</p>
                <div class="home-hero-actions">
                    <a class="button home-hero-primary" href="/rooms">Book now <span aria-hidden="true">→</span></a>
                    <a class="home-hero-link" href="/bookings/lookup">Already booked?</a>
                </div>
            </div>
        </section>
        <section class="search-wrap home-availability-wrap"><div class="home-availability">Find the right room for your dates.</div></section>
    </main>
`;

const luminance = color => {
    const [red, green, blue] = color.match(/\d+/g).slice(0, 3).map(channel => Number(channel) / 255).map(channel => (
        channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
    ));
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
};

test('the home hero fills the viewport without clipping its overlapping availability panel', async () => {
    const browser = await chromium.launch();
    try {
        for (const viewport of [{ width: 390, height: 844 }, { width: 320, height: 740 }, { width: 1440, height: 900 }]) {
            const page = await browser.newPage({ viewport });
            await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1">${styles.map(style => `<style>${style}</style>`).join('')}</head><body>${pageMarkup}</body>`);
            const layout = await page.evaluate(() => {
                const hero = document.querySelector('.home-hero').getBoundingClientRect();
                const availability = document.querySelector('.home-availability').getBoundingClientRect();
                return {
                    heroLeft: hero.left,
                    heroWidth: hero.width,
                    heroHeight: hero.height,
                    viewportWidth: window.innerWidth,
                    viewportHeight: window.innerHeight,
                    availabilityTop: availability.top,
                    bodyWidth: document.body.scrollWidth,
                };
            });

            assert.equal(layout.heroLeft, 0, `${viewport.width}px hero should meet the left edge`);
            assert.equal(layout.heroWidth, viewport.width, `${viewport.width}px hero should span the page`);
            assert.ok(layout.heroHeight >= viewport.height, `${viewport.width}px hero should fill the viewport: ${layout.heroHeight}px`);
            assert.ok(layout.availabilityTop < layout.heroHeight, 'the availability panel should still meet the hero');
            assert.ok(layout.bodyWidth <= layout.viewportWidth, `${viewport.width}px page should not scroll sideways`);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});

test('Book now is the visible brand-primary action and fits the phone layout', async () => {
    const browser = await chromium.launch();
    try {
        for (const viewport of [{ width: 390, height: 844 }, { width: 320, height: 740 }, { width: 1440, height: 900 }]) {
            const page = await browser.newPage({ viewport });
            await page.setContent(`<head><meta name="viewport" content="width=device-width, initial-scale=1">${styles.map(style => `<style>${style}</style>`).join('')}</head><body>${pageMarkup}</body>`);
            const action = await page.locator('.home-hero .button').evaluate(element => {
                const rect = element.getBoundingClientRect();
                return {
                    label: element.textContent.trim(),
                    background: getComputedStyle(element).backgroundColor,
                    color: getComputedStyle(element).color,
                    top: rect.top,
                    height: rect.height,
                    width: rect.width,
                    right: rect.right,
                    radius: Number.parseFloat(getComputedStyle(element).borderTopLeftRadius),
                };
            });
            const copy = await page.locator('.home-hero .hero-copy').boundingBox();
            const secondary = await page.locator('.home-hero-link').boundingBox();

            assert.match(action.label, /^Book now/);
            assert.equal(action.background, 'rgb(201, 121, 0)', 'the primary action should use Carolina gold');
            const contrast = (Math.max(luminance(action.background), luminance(action.color)) + 0.05) / (Math.min(luminance(action.background), luminance(action.color)) + 0.05);
            assert.ok(contrast >= 4.5, `the primary action text should meet normal-text contrast: ${contrast.toFixed(2)}:1`);
            assert.ok(action.height >= 54, `${viewport.width}px action should have a comfortable tap target`);
            assert.ok(action.radius <= 16, `${viewport.width}px primary action should have softly rounded, not pill-shaped, corners: ${action.radius}px`);
            assert.ok(action.right <= copy.x + copy.width + 1, `${viewport.width}px action should stay inside the copy column`);
            assert.ok(secondary.x >= copy.x && secondary.x + secondary.width <= copy.x + copy.width + 1, `${viewport.width}px secondary link should stay inside the copy column`);
            if (viewport.width <= 460) {
                assert.ok(action.width >= copy.width - 1, 'phone primary action should use the available content width');
                assert.ok(secondary.y >= action.top + action.height, `phone secondary link should sit below the primary action: ${JSON.stringify({ action, secondary })}`);
                const statPositions = await page.locator('.home-hero-details span').evaluateAll(items => items.map(item => item.getBoundingClientRect().top));
                assert.ok(statPositions.every(top => Math.abs(top - statPositions[0]) <= 1), `phone stay highlights should sit in one balanced row: ${statPositions}`);
            }
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
