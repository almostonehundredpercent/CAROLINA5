import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function luminance(color) {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
    assert.equal(channels?.length, 3, `Expected an RGB color, received ${color}`);
    const linear = channels.map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

function contrast(foreground, background) {
    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

for (const dark of [false, true]) {
    test(`${dark ? 'dark' : 'light'} booking status menu options remain legible`, async () => {
        const page = await browser.newPage();
        try {
            await page.setContent(`<html class="${dark ? 'dark-mode' : ''}"><head><style>${stylesheet}</style></head><body>
                <main class="admin-main"><table><tbody><tr><td><select class="status-select confirmed" aria-label="Booking status">
                    <option value="pending">Pending</option><option value="confirmed" selected>Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                </select></td></tr></tbody></table></main></body></html>`);
            const menu = await page.locator('select.status-select').evaluate(select => ({
                scheme: getComputedStyle(select).colorScheme,
                options: [...select.options].map(option => {
                    const style = getComputedStyle(option);
                    return { color: style.color, background: style.backgroundColor };
                }),
            }));
            assert.equal(menu.scheme, dark ? 'dark' : 'light');
            for (const option of menu.options) {
                assert.ok(!option.background.endsWith(', 0)'), `option has a transparent background: ${option.background}`);
                assert.ok(contrast(option.color, option.background) >= 4.5,
                    `option contrast is below 4.5:1: ${option.color} on ${option.background}`);
            }
        } finally {
            await page.close();
        }
    });
}
