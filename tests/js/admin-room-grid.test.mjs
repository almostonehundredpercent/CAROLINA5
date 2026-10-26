import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const adminStyles = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
const template = await readFile(new URL('../../resources/views/admin/rooms.blade.php', import.meta.url), 'utf8');
const roomStyles = template.match(/<style>([\s\S]*?)<\/style>/)?.[1] ?? '';
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

for (const [width, columns] of [[320, 3], [390, 3], [768, 4], [1440, 6]]) {
    test(`all room tiles fit the ${width}px board without sideways scrolling`, async () => {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            const rooms = Array.from({ length: 19 }, (_, index) =>
                `<button class="room-slot available" type="button"><span class="room-slot-number">${index + 1}</span><span class="room-slot-state">Available</span><span class="room-slot-name">Room ${index + 1}</span></button>`
            ).join('');
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${adminStyles}</style><style>${roomStyles}</style></head><body><main class="admin-main"><div class="room-selector-grid">${rooms}</div></main></body></html>`);

            const dimensions = await page.evaluate(() => ({
                pageWidth: document.documentElement.scrollWidth,
                viewportWidth: window.innerWidth,
                columnCount: getComputedStyle(document.querySelector('.room-selector-grid')).gridTemplateColumns.split(' ').length,
            }));
            assert.equal(await page.locator('.room-slot:visible').count(), 19);
            assert.equal(dimensions.columnCount, columns);
            assert.ok(dimensions.pageWidth <= dimensions.viewportWidth, `board overflows by ${dimensions.pageWidth - dimensions.viewportWidth}px`);
        } finally {
            await page.close();
        }
    });
}
