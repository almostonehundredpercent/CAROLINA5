import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const scriptPath = new URL('../../public/js/cleaning-countdown.js', import.meta.url);
const script = await readFile(scriptPath, 'utf8').catch((error) => {
    if (error.code === 'ENOENT') return '';
    throw error;
});
let browser;

const launchOptions = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
    ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
    : {};

before(async () => { browser = await chromium.launch(launchOptions); });
after(async () => { await browser?.close(); });

test('cleaning countdown updates each second and stays at zero when complete', async () => {
    const page = await browser.newPage();

    try {
        await page.setContent(`
            <div role="timer" data-cleaning-countdown data-remaining-seconds="2">
                <time data-countdown-value>00:02</time>
                <span data-countdown-complete hidden>Cleaning window complete</span>
            </div>`);
        if (script) await page.addScriptTag({ content: script });

        const value = page.locator('[data-countdown-value]');
        assert.equal(await value.textContent(), '00:02');

        await page.waitForTimeout(1100);
        assert.equal(await value.textContent(), '00:01');

        await page.waitForTimeout(1100);
        assert.equal(await value.textContent(), '00:00');
        assert.equal(await page.locator('[data-countdown-complete]').isVisible(), true);
        assert.equal(await page.locator('[data-cleaning-countdown]').getAttribute('data-remaining-seconds'), '0');

        await page.waitForTimeout(1100);
        assert.equal(await value.textContent(), '00:00');
    } finally {
        await page.close();
    }
});
