import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [script, styles] = await Promise.all([
    readFile(new URL('../../public/js/room-gallery.js', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/room-gallery.css', import.meta.url), 'utf8'),
]);
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

async function loadRoomGallery(page, images) {
    page.setDefaultTimeout(3000);
    await page.setContent(`<html><head><style>${styles}</style></head><body>
        <section class="room-detail"><img src="${images[0]}" alt="Room four"></section>
        <script id="room-gallery-data" type="application/json">${JSON.stringify({ room: 'Room 4', images })}</script>
    </body></html>`, { waitUntil: 'domcontentloaded' });
    await page.addScriptTag({ content: script });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
}

test('a room image opens an accessible mobile-sized view and returns focus when closed', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await loadRoomGallery(page, ['https://images.example/room-four.jpg']);
        const imageButton = page.getByRole('button', { name: 'Enlarge Room 4 photo' });

        await imageButton.click();

        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        await dialog.waitFor({ state: 'visible' });
        assert.equal(await dialog.locator('img').getAttribute('src'), 'https://images.example/room-four.jpg');
        const bounds = await dialog.boundingBox();
        assert.ok(bounds.width >= 389 && bounds.height >= 843, 'mobile viewer should fill the screen');

        await dialog.getByRole('button', { name: 'Close enlarged photo' }).click();
        await dialog.waitFor({ state: 'hidden' });
        assert.equal(await imageButton.evaluate(element => element === document.activeElement), true);

        await imageButton.click();
        await page.keyboard.press('Escape');
        await dialog.waitFor({ state: 'hidden' });
        assert.equal(await imageButton.evaluate(element => element === document.activeElement), true);
    } finally {
        await page.close();
    }
});

test('the enlarged view follows room-gallery selection and supports photo navigation', async () => {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    try {
        await loadRoomGallery(page, [
            'https://images.example/room-four-one.jpg',
            'https://images.example/room-four-two.jpg',
            'https://images.example/room-four-three.jpg',
        ]);
        await page.getByRole('button', { name: 'Show photo 2 of 3' }).click();
        await page.getByRole('button', { name: 'Enlarge Room 4 photo 2 of 3' }).click();

        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        assert.equal(await dialog.locator('img').getAttribute('src'), 'https://images.example/room-four-two.jpg');

        await dialog.getByRole('button', { name: 'Next photo' }).click();
        assert.equal(await dialog.locator('img').getAttribute('src'), 'https://images.example/room-four-three.jpg');
        await page.keyboard.press('ArrowLeft');
        assert.equal(await dialog.locator('img').getAttribute('src'), 'https://images.example/room-four-two.jpg');
        assert.equal(await page.locator('.room-gallery-main-image').getAttribute('src'), 'https://images.example/room-four-two.jpg');
    } finally {
        await page.close();
    }
});
