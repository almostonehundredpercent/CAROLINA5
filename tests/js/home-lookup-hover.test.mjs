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
    await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}${stylesheet}</style><section class="home-lookup"><div class="empty-state"><div><span class="eyebrow">ALREADY BOOKED?</span><h2>Find your stay in seconds.</h2><p>Use your email address and booking reference to view your reservation anytime—no account required.</p></div><a class="button" href="#">Find booking</a></div></section>`);
    return page;
}

async function lift(page) {
    return page.locator(cardSelector).evaluate(element => new DOMMatrixReadOnly(getComputedStyle(element).transform).m42);
}

test('mobile booking lookup is compact while the desktop panel keeps its original size', async () => {
    const context = await browser.newContext({ isMobile: true, viewport: { width: 390, height: 844 } });
    try {
        const page = await render(context);
        const mobile = await page.locator(cardSelector).evaluate(element => {
            const title = element.querySelector('h2');
            const copy = element.querySelector('p');
            const button = element.querySelector('.button');
            return {
                padding: getComputedStyle(element).padding,
                titleSize: Number.parseFloat(getComputedStyle(title).fontSize),
                copySize: Number.parseFloat(getComputedStyle(copy).fontSize),
                buttonHeight: Math.round(button.getBoundingClientRect().height),
                height: Math.round(element.getBoundingClientRect().height),
                scrollWidth: document.documentElement.scrollWidth,
            };
        });
        assert.equal(mobile.padding, '20px 16px');
        assert.equal(mobile.titleSize, 22.4);
        assert.equal(mobile.copySize, 14.4);
        assert.ok(mobile.buttonHeight <= 44, `mobile button should stay compact; got ${mobile.buttonHeight}px`);
        assert.ok(mobile.height < 220, `lookup panel should take less vertical space; got ${mobile.height}px`);
        assert.equal(mobile.scrollWidth, 390, 'compact lookup panel should not cause horizontal page overflow');

        await page.setViewportSize({ width: 1280, height: 900 });
        const desktop = await page.locator(cardSelector).evaluate(element => ({
            padding: getComputedStyle(element).padding,
            titleSize: Number.parseFloat(getComputedStyle(element.querySelector('h2')).fontSize),
        }));
        assert.equal(desktop.padding, '43px 48px');
        assert.equal(desktop.titleSize, 38.4);
    } finally {
        await context.close();
    }
});

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
