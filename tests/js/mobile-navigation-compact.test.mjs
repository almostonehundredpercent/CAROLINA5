import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await Promise.all([
    'site.css',
    'palette.css',
    'responsive.css',
    'navigation-glass.css',
    'button-refinement.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('mobile navigation panel is compact without shrinking its tap targets or changing desktop spacing', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.join('\n')}</style></head><body>
            <header class="nav nav--over-hero">
                <a class="brand" href="#home">Carolina</a>
                <button class="menu-toggle" aria-expanded="true">Close</button>
                <nav class="is-open">
                    <a href="#rooms">Rooms</a>
                    <a href="#find">Find booking</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                    <a class="nav-button nav-book-now" href="#book">Book now</a>
                    <button class="theme-toggle nav-theme-toggle"><span>Dark mode</span></button>
                    <a class="nav-button" href="#sign-in">Sign in</a>
                </nav>
            </header>
        </body></html>`);

        const mobile = await page.evaluate(() => {
            const panel = document.querySelector('.nav nav');
            const panelRect = panel.getBoundingClientRect();
            const style = getComputedStyle(panel);
            const targets = [...panel.querySelectorAll('a,button')].map(element => Math.round(element.getBoundingClientRect().height));
            return {
                width: Math.round(panelRect.width),
                height: Math.round(panelRect.height),
                gap: style.gap,
                left: Math.round(panelRect.left),
                right: Math.round(innerWidth - panelRect.right),
                targetHeights: targets,
                documentWidth: document.documentElement.scrollWidth,
            };
        });

        assert.ok(mobile.width <= 340, `menu should be narrower than the current 351px panel, got ${mobile.width}px`);
        assert.ok(mobile.height <= 360, `menu should avoid the current tall layout, got ${mobile.height}px`);
        assert.equal(mobile.gap, '4px', 'mobile menu should use a compact, consistent row gap');
        assert.ok(mobile.targetHeights.every(height => height >= 40), `all menu links and controls should remain easy to tap; got ${mobile.targetHeights.join(', ')}px`);
        assert.equal(mobile.documentWidth, 390, 'the compact panel should not cause horizontal overflow');
        assert.ok(Math.abs(mobile.left - mobile.right) <= 1, 'the compact panel should remain centered');

        await page.setViewportSize({ width: 1280, height: 800 });
        const desktopGap = await page.locator('.nav nav').evaluate(element => getComputedStyle(element).gap);
        assert.equal(desktopGap, '24px', 'desktop navigation spacing should remain unchanged');
    } finally {
        await page.close();
    }
});
