import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const layout = await readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
const navigationStyles = await readFile(new URL('../../public/css/navigation-glass.css', import.meta.url), 'utf8');
const responsiveStyles = await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8');
const match = layout.match(/const updateGlassHeader = \(\) => glassHeader\.classList\.toggle\('is-scrolled', ([\s\S]*?)\);/);

test('glass homepage navigation becomes opaque shortly after scrolling within the hero', async () => {
    assert.ok(match, 'the shared layout should define the homepage glass-header scroll state');
    const browser = await chromium.launch();
    try {
        for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 900 }]) {
            const page = await browser.newPage({ viewport });
            await page.setContent(`
                <style>${responsiveStyles}</style>
                <style>${navigationStyles}</style>
                <header class="nav nav--over-hero"></header>
                <main><section class="home-hero" style="height:1200px"></section><div style="height:600px"></div></main>
            `);
            await page.evaluate((predicate) => {
                const header = document.querySelector('.nav--over-hero');
                const hero = document.querySelector('.home-hero');
                window.updateGlassHeader = new Function('glassHeader', 'homeHero', `return () => glassHeader.classList.toggle('is-scrolled', ${predicate})`)(header, hero);
                window.updateGlassHeader();
            }, match[1]);
            await page.evaluate(() => window.scrollTo(0, 60));
            await page.evaluate(() => window.updateGlassHeader());
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.nav')).backgroundColor === 'rgba(28, 20, 15, 0.9)');

            const state = await page.locator('.nav').evaluate(element => ({
                scrolled: element.classList.contains('is-scrolled'),
                background: getComputedStyle(element).backgroundColor,
            }));
            assert.equal(state.scrolled, true, `${viewport.width}px header should enter its readable state during hero scroll`);
            assert.equal(state.background, 'rgba(28, 20, 15, 0.9)', `${viewport.width}px header should obscure text passing behind it`);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
