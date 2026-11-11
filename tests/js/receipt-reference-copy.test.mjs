import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const view = await readFile(new URL('../../resources/views/bookings/receipt.blade.php', import.meta.url), 'utf8');

test('payment confirmation copies its booking reference and announces success', async () => {
    const script = view.match(/<script data-receipt-reference-copy>([\s\S]*?)<\/script>/)?.[1];
    assert.ok(script, 'the receipt must provide an executable reference-copy control');
    assert.match(view, /class="receipt-copy-reference"[\s\S]*?data-copy-reference="\{\{\s*\$booking->reference\s*\}\}"/);

    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.setContent(`<p class="receipt-success-reference"><span>Reference: <strong>CAR-TEST123</strong></span><button type="button" class="receipt-copy-reference" data-copy-reference="CAR-TEST123"><span data-copy-label>Copy</span></button><span data-copy-status role="status" aria-live="polite"></span></p>`);
        await page.evaluate(() => {
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: { writeText: async value => { window.copiedReference = value; } },
            });
        });
        await page.addScriptTag({ content: script });
        await page.locator('.receipt-copy-reference').click();

        assert.equal(await page.evaluate(() => window.copiedReference), 'CAR-TEST123');
        assert.equal(await page.locator('[data-copy-label]').textContent(), 'Copied!');
        assert.equal(await page.locator('[data-copy-status]').textContent(), 'Booking reference copied.');
        assert.equal(await page.locator('.receipt-copy-reference').getAttribute('aria-label'), 'Booking reference copied');
    } finally {
        await browser.close();
    }
});
