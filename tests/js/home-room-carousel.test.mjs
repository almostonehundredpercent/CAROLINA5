import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const homeView = await readFile(new URL('../../resources/views/home.blade.php', import.meta.url), 'utf8');
const carouselScript = homeView.match(/<script>\s*(document\.addEventListener\('DOMContentLoaded',[\s\S]*?const carousel = document\.querySelector\('\.home-room-carousel'\);[\s\S]*?)<\/script>/)?.[1];
assert.ok(carouselScript, 'the home page should initialize its room carousel');

let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

test('desktop room carousel returns to the first card and stops auto-scrolling when resized to mobile', async () => {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    const cards = [1, 2, 3].map(number => `<article class="room-card">Room ${number}</article>`).join('');
    await page.setContent(`<style>
        .home-room-carousel { display:flex; width:1000px; gap:25px; overflow-x:auto; scroll-snap-type:x mandatory; }
        .room-card { flex:0 0 calc((100% - 50px) / 3); scroll-snap-align:start; }
        @media (max-width:800px) { .home-room-carousel { width:343px; gap:16px; } .room-card { flex-basis:84%; } }
    </style><div class="home-room-carousel">${cards}</div><script>${carouselScript}</script>`);
    await page.waitForFunction(() => document.querySelector('.home-room-carousel').children.length === 9);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForFunction(() => {
        const carousel = document.querySelector('.home-room-carousel');
        return carousel.children.length === 3 && carousel.scrollLeft === 0;
    }, null, { timeout: 1500 });

    const firstCardLeft = await page.locator('.home-room-carousel .room-card').first().evaluate(card => card.getBoundingClientRect().left);
    assert.ok(firstCardLeft >= 0 && firstCardLeft < 20, `the first room should be visible at the start of the mobile carousel, got left=${firstCardLeft}`);

    await page.waitForTimeout(300);
    assert.equal(await page.locator('.home-room-carousel').evaluate(carousel => carousel.scrollLeft), 0,
        'the room cards should stay in place until the visitor swipes');
    await page.close();
});
