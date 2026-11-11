import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const publicStyles = [
    await readFile(new URL('../../public/css/palette.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/navigation-glass.css', import.meta.url), 'utf8'),
    await readFile(new URL('../../public/css/button-refinement.css', import.meta.url), 'utf8'),
].join('\n');
const appLayout = await readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('Book now reads as a regular navigation link in guest and signed-in headers', async () => {
    const headerCtas = appLayout.match(/class="nav-button nav-book-now"[\s\S]*?<\/a>/g) ?? [];
    assert.equal(headerCtas.length, 2, 'guest and signed-in headers should both retain Book now');
    assert.ok(headerCtas.every(cta => !/<svg\b/.test(cta)), 'header Book now should not carry a separate button icon');

    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    try {
        await page.setContent(`<!doctype html><html><head><style>${publicStyles}</style></head><body>
            <header class="nav"><a class="brand" href="#">Carolina</a><nav class="is-open">
                <a href="#rooms">Rooms</a>
                <a class="nav-button nav-book-now" href="#book">Book now</a>
                <a class="nav-button" href="#sign-in">Sign in</a>
            </nav></header>
        </body></html>`);

        const styles = await page.evaluate(() => Object.fromEntries(
            ['a[href="#rooms"]', '.nav-book-now', 'a[href="#sign-in"]'].map(selector => {
                const style = getComputedStyle(document.querySelector(selector));
                return [selector, {
                    backgroundImage: style.backgroundImage,
                    backgroundColor: style.backgroundColor,
                    backdropFilter: style.backdropFilter,
                    borderStyle: style.borderTopStyle,
                    color: style.color,
                    fontSize: style.fontSize,
                    fontWeight: style.fontWeight,
                    padding: style.padding,
                    radius: style.borderRadius,
                }];
            }),
        ));

        assert.equal(styles['.nav-book-now'].backgroundImage, 'none');
        assert.equal(styles['.nav-book-now'].backgroundColor, styles['a[href="#rooms"]'].backgroundColor);
        assert.equal(styles['.nav-book-now'].backdropFilter, 'none');
        assert.equal(styles['.nav-book-now'].borderStyle, 'none');
        assert.equal(styles['.nav-book-now'].color, styles['a[href="#rooms"]'].color);
        assert.equal(styles['.nav-book-now'].fontSize, styles['a[href="#rooms"]'].fontSize);
        assert.equal(styles['.nav-book-now'].fontWeight, styles['a[href="#rooms"]'].fontWeight);
        assert.equal(styles['.nav-book-now'].padding, styles['a[href="#rooms"]'].padding);
        assert.equal(styles['.nav-book-now'].radius, styles['a[href="#rooms"]'].radius);
        assert.equal(styles['.nav-book-now'].backgroundImage, 'none');
        assert.notEqual(styles['.nav-book-now'].backgroundImage, styles['a[href="#sign-in"]'].backgroundImage);
        assert.equal(styles['a[href="#sign-in"]'].backdropFilter, 'none');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator('nav').evaluate(element => element.classList.add('is-open'));
        const mobile = await page.evaluate(() => {
            const normal = getComputedStyle(document.querySelector('a[href="#rooms"]'));
            const cta = getComputedStyle(document.querySelector('.nav-book-now'));
            return { normalPadding: normal.padding, ctaPadding: cta.padding, normalWidth: normal.width, ctaWidth: cta.width, ctaDisplay: cta.display };
        });
        assert.equal(mobile.ctaPadding, mobile.normalPadding, 'mobile Book now should align with the other menu links');
        assert.equal(mobile.ctaWidth, mobile.normalWidth, 'mobile Book now should share the menu link width');
        assert.notEqual(mobile.ctaDisplay, 'none');
    } finally {
        await page.close();
    }
});

test('selected Book now is filled and highlighted instead of relying on an outline', async () => {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    try {
        await page.setContent(`<!doctype html><html><head><style>${publicStyles}</style></head><body>
            <header class="nav"><a class="brand" href="#">Carolina</a><nav>
                <a href="#rooms">Rooms</a>
                <a class="nav-button nav-book-now" href="#book" aria-current="page">Book now</a>
                <a class="nav-button" href="#sign-in">Sign in</a>
            </nav></header>
        </body></html>`);

        const activeStyle = await page.locator('.nav-book-now').evaluate(element => {
            const style = getComputedStyle(element);
            return { backgroundImage: style.backgroundImage, backgroundColor: style.backgroundColor };
        });

        assert.ok(activeStyle.backgroundImage !== 'none' || activeStyle.backgroundColor !== 'rgba(0, 0, 0, 0)', 'the selected navigation item needs a visible filled surface');

        await page.setViewportSize({ width: 390, height: 844 });
        const mobileStyle = await page.locator('.nav-book-now').evaluate(element => getComputedStyle(element).backgroundImage);
        assert.notEqual(mobileStyle, 'none', 'the selected Book now item should stay filled in the mobile menu');
    } finally {
        await page.close();
    }
});
