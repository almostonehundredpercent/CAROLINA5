import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/home-premium.css', import.meta.url), 'utf8');
const touchScript = fileURLToPath(new URL('../../public/js/home-touch-cards.js', import.meta.url));
const cardSelector = '.home-lookup .empty-state';
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

async function render(context) {
    const page = await context.newPage();
    await page.setContent(`<style>${stylesheet}</style><section class="home-lookup"><div class="empty-state"><div><h2>Find your stay in seconds.</h2><p>Look up a booking.</p></div><a class="button" href="#">Find booking</a></div></section>`);
    return page;
}

async function lift(page) {
    return page.locator(cardSelector).evaluate(element => new DOMMatrixReadOnly(getComputedStyle(element).transform).m42);
}

test('lookup panel lifts when hovered with a precise pointer', async () => {
    const context = await browser.newContext();
    try {
        const page = await render(context);
        assert.equal(await lift(page), 0);
        await page.locator(cardSelector).hover();
        await page.waitForTimeout(300);
        assert.ok(await lift(page) < -2, 'the whole panel should move upward');
    } finally {
        await context.close();
    }
});

test('lookup panel responds to touch like the other homepage cards', async () => {
    const context = await browser.newContext({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });
    try {
        const page = await render(context);
        await page.addScriptTag({ path: touchScript });
        await page.locator(cardSelector).dispatchEvent('pointerdown', { pointerType: 'touch', isPrimary: true, pointerId: 7, clientX: 100, clientY: 100 });
        await page.waitForTimeout(300);
        assert.ok(await lift(page) < -2, 'touch press should lift the panel');
    } finally {
        await context.close();
    }
});

test('lookup panel does not lift when reduced motion is requested', async () => {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    try {
        const page = await render(context);
        await page.locator(cardSelector).hover();
        assert.equal(await lift(page), 0);
    } finally {
        await context.close();
    }
});
