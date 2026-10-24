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

for (const width of [1280, 390]) {
    test(`dark-mode More hover stays warm and readable at ${width}px`, async () => {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            await page.setContent(`<html class="dark-mode"><head><style>${stylesheet}</style></head><body>
                <aside class="admin-sidebar"><nav class="admin-nav">
                    <details class="admin-more" open><summary>More <span aria-hidden="true">⌄</span></summary>
                        <div class="admin-more-links"><a href="#">Overview</a></div>
                    </details>
                </nav></aside></body></html>`);
            const summary = page.locator('.admin-more summary');
            await summary.hover();
            const colors = await summary.evaluate(element => {
                const style = getComputedStyle(element);
                return { foreground: style.color, background: style.backgroundColor };
            });

            assert.ok(luminance(colors.background) < 0.08,
                `dark-mode More hover should use a dark warm surface, got ${colors.background}`);
            assert.ok(contrast(colors.foreground, colors.background) >= 4.5,
                `dark-mode More hover contrast is ${contrast(colors.foreground, colors.background).toFixed(2)}:1`);
        } finally {
            await page.close();
        }
    });
}
