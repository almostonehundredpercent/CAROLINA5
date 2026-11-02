import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir, stat } from 'node:fs/promises';

const root = new URL('../../', import.meta.url);

test('the browser entry does not ship the unused Axios client', async () => {
    const [packageJson, viteConfig] = await Promise.all([
        readFile(new URL('package.json', root), 'utf8').then(JSON.parse),
        readFile(new URL('vite.config.js', root), 'utf8'),
    ]);

    assert.equal(packageJson.devDependencies?.axios, undefined);
    assert.doesNotMatch(viteConfig, /resources\/js\/app\.js/);
});

test('local room photos have smaller responsive WebP variants', async () => {
    const directory = new URL('public/images/rooms/', root);
    const originals = (await readdir(directory)).filter(name => /^room-\d+-angle-\d+\.jpg$/.test(name));

    assert.ok(originals.length > 0, 'room photo fixtures should exist');
    for (const original of originals) {
        const base = original.replace(/\.jpg$/, '');
        const originalSize = (await stat(new URL(original, directory))).size;
        for (const width of [480, 800, 1200]) {
            const variant = new URL(`${base}-${width}.webp`, directory);
            const variantSize = (await stat(variant)).size;
            assert.ok(variantSize > 0, `${variant.pathname} should be generated`);
            assert.ok(variantSize < originalSize, `${variant.pathname} should be smaller than its JPEG source`);
        }
    }

    const logoSize = (await stat(new URL('public/images/carolina-logo.jpg', root))).size;
    const logoWebpSize = (await stat(new URL('public/images/carolina-logo-256.webp', root))).size;
    assert.ok(logoWebpSize < logoSize, 'the compact logo should be smaller than its JPEG source');
});
