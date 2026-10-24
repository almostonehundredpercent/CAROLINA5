import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [script, styles] = await Promise.all([
    readFile(new URL('../../public/js/room-gallery.js', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/room-gallery.css', import.meta.url), 'utf8'),
]);
let browser;

const portraitPhoto = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1536" height="2048"><rect width="100%" height="100%" fill="#c78a45"/></svg>')}`;

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
        await loadRoomGallery(page, [portraitPhoto]);
        const imageButton = page.getByRole('button', { name: 'Enlarge Room 4 photo' });

        await imageButton.click();

        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        await dialog.waitFor({ state: 'visible' });
        assert.equal(await dialog.locator('img').getAttribute('src'), portraitPhoto);
        await dialog.locator('img').evaluate(img => img.decode());
        const imageBounds = await dialog.locator('img').boundingBox();
        const stageBounds = await dialog.locator('.room-lightbox-stage').boundingBox();
        assert.ok(imageBounds.height <= stageBounds.height + 1, 'portrait photo must fit inside the viewer instead of being clipped');
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

test('a guest can zoom into and drag a room photo to inspect it', async () => {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    try {
        await loadRoomGallery(page, [portraitPhoto]);
        await page.getByRole('button', { name: 'Enlarge Room 4 photo' }).click();

        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        const image = dialog.locator('img');
        await image.evaluate(element => element.decode());
        const fitTransform = await image.evaluate(element => element.style.transform);
        await dialog.getByRole('button', { name: 'Zoom in' }).click();
        const zoomedTransform = await image.evaluate(element => element.style.transform);
        assert.notEqual(zoomedTransform, fitTransform, 'Zoom in should enlarge the image');

        const stage = await dialog.locator('.room-lightbox-stage').boundingBox();
        const startX = stage.x + stage.width / 2;
        const startY = stage.y + stage.height / 2;
        await page.mouse.move(startX, startY);
        await page.mouse.down();
        await page.mouse.move(startX + 60, startY + 35);
        await page.mouse.up();
        const pannedTransform = await image.evaluate(element => element.style.transform);
        assert.notEqual(pannedTransform, zoomedTransform, 'dragging should move the zoomed image');

        await dialog.getByRole('button', { name: 'Reset zoom' }).click();
        assert.equal(await image.evaluate(element => element.style.transform), 'translate3d(0px, 0px, 0px) scale(1)');
    } finally {
        await page.close();
    }
});

test('a portrait room photo remains within the desktop viewer frame', async () => {
    const page = await browser.newPage({ viewport: { width: 1212, height: 900 } });
    try {
        await loadRoomGallery(page, [portraitPhoto]);
        await page.getByRole('button', { name: 'Enlarge Room 4 photo' }).click();

        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        const image = dialog.locator('img');
        await image.evaluate(element => element.decode());
        const imageBounds = await image.boundingBox();
        const stageBounds = await dialog.locator('.room-lightbox-stage').boundingBox();
        assert.ok(imageBounds.height <= stageBounds.height + 1, 'the full photo should fit instead of overflowing the viewer');
    } finally {
        await page.close();
    }
});

test('a zoomed room photo can be dragged with touch on mobile', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    try {
        await loadRoomGallery(page, [portraitPhoto]);
        await page.getByRole('button', { name: 'Enlarge Room 4 photo' }).click();
        const dialog = page.getByRole('dialog', { name: 'Room 4 photos' });
        await dialog.getByRole('button', { name: 'Zoom in' }).click();
        const stage = await dialog.locator('.room-lightbox-stage').boundingBox();
        const startX = stage.x + stage.width / 2;
        const startY = stage.y + stage.height / 2;
        const session = await page.context().newCDPSession(page);

        await session.send('Input.dispatchTouchEvent', {
            type: 'touchStart',
            touchPoints: [{ id: 1, x: startX, y: startY }],
        });
        await session.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ id: 1, x: startX + 40, y: startY + 25 }],
        });
        await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });

        const transform = await dialog.locator('img').evaluate(element => element.style.transform);
        assert.notEqual(transform, 'translate3d(0px, 0px, 0) scale(1.5)', 'touch drag should pan the zoomed photo');
        await session.detach();
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
