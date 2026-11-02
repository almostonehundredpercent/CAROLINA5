import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

test('Carolina favicon is circular with transparent corners', async () => {
    const favicon = await readFile(new URL('../../public/images/carolina-favicon.png', import.meta.url));
    const fallbackIcon = await readFile(new URL('../../public/favicon.ico', import.meta.url));
    const browser = await chromium.launch({
        ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
            ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
            : {}),
    });

    try {
        const page = await browser.newPage();
        const pixels = await page.evaluate(async (dataUrl) => {
            const image = new Image();
            image.src = dataUrl;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            const context = canvas.getContext('2d');
            context.drawImage(image, 0, 0);

            return {
                width: image.naturalWidth,
                height: image.naturalHeight,
                cornerAlpha: context.getImageData(0, 0, 1, 1).data[3],
                centerAlpha: context.getImageData(canvas.width / 2, canvas.height / 2, 1, 1).data[3],
            };
        }, `data:image/png;base64,${favicon.toString('base64')}`);

        assert.equal(pixels.width, 128);
        assert.equal(pixels.height, 128);
        assert.equal(pixels.cornerAlpha, 0);
        assert.ok(pixels.centerAlpha >= 250);

        assert.equal(fallbackIcon.readUInt16LE(2), 1);
        assert.equal(fallbackIcon.readUInt16LE(4), 1);
        assert.equal(fallbackIcon[6], 128);
        assert.equal(fallbackIcon[7], 128);
        assert.equal(fallbackIcon.readUInt32LE(18), 22);
        assert.deepEqual(fallbackIcon.subarray(22, 30), favicon.subarray(0, 8));
    } finally {
        await browser.close();
    }
});
