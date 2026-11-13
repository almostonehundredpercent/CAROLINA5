import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

test('mobile amenities align at both edges and retain every description', async () => {
    const css = await Promise.all(['site.css', 'palette.css', 'responsive.css', 'home-premium.css'].map(name => readFile(new URL(`../../public/css/${name}`, import.meta.url), 'utf8')));
    const home = await readFile(new URL('../../resources/views/home.blade.php', import.meta.url), 'utf8');
    const section = home.match(/<section class="benefits section home-benefits"[\s\S]*?<\/section>/)[0];
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        for (const width of [320, 390, 620, 1280]) {
            for (const theme of ['', 'dark-mode']) {
                await page.setViewportSize({ width, height: 900 });
                await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.join('\n')}</style></head><body class="${theme}">${section}</body></html>`);
                const cards = await page.locator('.benefit-grid > div').evaluateAll(nodes => nodes.map(node => {
                    const r = node.getBoundingClientRect();
                    const p = node.querySelector('p');
                    return { left: r.left, right: r.right, top: r.top, height: r.height, visible: getComputedStyle(p).display !== 'none' && p.scrollHeight <= p.clientHeight, text: p.textContent };
                }));
                assert.equal(cards.length, 5);
                assert.ok(cards.every(card => card.visible && card.text.length > 10));
                if (width <= 620) {
                    assert.equal(new Set(cards.map(card => card.top)).size, 1, 'all five mobile cards share one row');
                    assert.ok(cards.slice(1).every((card, index) => card.left > cards[index].left && card.left >= cards[index].right - 1), 'cards follow one another without overlapping');
                } else {
                    assert.equal(new Set(cards.map(card => card.top)).size, 1);
                }
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                if (width === 390) await page.screenshot({ path: `benefits-${theme || 'light'}.png`, fullPage: true });
            }
        }
    } finally { await browser.close(); }
});
