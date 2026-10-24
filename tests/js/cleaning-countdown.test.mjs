import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const scriptPath = new URL('../../public/js/cleaning-countdown.js', import.meta.url);
const stylesheetPath = new URL('../../public/css/admin.css', import.meta.url);
const roomsTemplatePath = new URL('../../resources/views/admin/rooms.blade.php', import.meta.url);
const script = await readFile(scriptPath, 'utf8').catch((error) => {
    if (error.code === 'ENOENT') return '';
    throw error;
});
const stylesheet = await readFile(stylesheetPath, 'utf8');
const roomsTemplate = await readFile(roomsTemplatePath, 'utf8');
const roomStyles = roomsTemplate.match(/<style>([\s\S]*?)<\/style>/)?.[1] ?? '';
let browser;

const launchOptions = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
    ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
    : {};

before(async () => { browser = await chromium.launch(launchOptions); });
after(async () => { await browser?.close(); });

test('cleaning countdown updates each second and stays at zero when complete', async () => {
    const page = await browser.newPage();

    try {
        await page.setContent(`<!doctype html><html><head><style>${stylesheet}</style><style>${roomStyles}</style></head><body>
            <div class="schedule-item">
                <div class="cleaning-countdown" role="timer" data-cleaning-countdown data-remaining-seconds="2">
                    <span class="cleaning-countdown-copy">
                        <b data-countdown-label>Cleaning time remaining</b>
                        <span data-countdown-complete hidden>Cleaning window complete</span>
                    </span>
                    <time data-countdown-value>00:02</time>
                </div>
            </div>
        </body></html>`);
        if (script) await page.addScriptTag({ content: script });

        const value = page.locator('[data-countdown-value]');
        const completion = page.locator('[data-countdown-complete]');
        assert.equal(await value.textContent(), '00:02');
        assert.equal(await completion.isVisible(), false);

        await page.evaluate(() => {
            const currentTime = Date.now.bind(Date);
            Date.now = () => currentTime() + 3000;
            document.dispatchEvent(new Event('visibilitychange'));
        });
        assert.equal(await value.textContent(), '00:00');
        assert.equal(await completion.isVisible(), true);
        assert.equal(await page.locator('[data-cleaning-countdown]').getAttribute('data-remaining-seconds'), '0');

        await page.waitForTimeout(1100);
        assert.equal(await value.textContent(), '00:00');
    } finally {
        await page.close();
    }
});
