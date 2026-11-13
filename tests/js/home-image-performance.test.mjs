import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';

const root = new URL('../../', import.meta.url);
const template = await readFile(new URL('resources/views/home.blade.php', root), 'utf8');
const stylesheet = await readFile(new URL('public/css/home-premium.css', root), 'utf8');
const baseStylesheet = await readFile(new URL('public/css/site.css', root), 'utf8');
const script = new URL('public/js/home-hero-slideshow.js', root);

const fixture = `<section class="hero home-hero">
  <div class="home-hero-slideshow" data-interval="200" aria-label="Carolina property photos">
    <img class="home-hero-slide is-active" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" srcset="/images/home-hero/lobby-1-900.jpg 900w, /images/home-hero/lobby-1-1800.jpg 1800w, /images/home-hero/lobby-1-4k.jpg 3840w" sizes="100vw" alt="" aria-hidden="true">
    <img class="home-hero-slide" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" aria-hidden="true">
    <img class="home-hero-slide" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" aria-hidden="true">
    <div class="home-hero-controls"><button type="button" data-hero-prev aria-label="Previous image">Previous</button><button type="button" data-hero-toggle aria-label="Pause automatic image rotation">Pause</button><button type="button" data-hero-next aria-label="Next image">Next</button><div class="home-hero-pagination"><button type="button" data-hero-slide="0" aria-label="Show image 1" aria-current="true">1</button><button type="button" data-hero-slide="1" aria-label="Show image 2">2</button><button type="button" data-hero-slide="2" aria-label="Show image 3">3</button></div></div>
  </div>
  <div class="home-hero-glow" aria-hidden="true"></div><div class="hero-copy">Hero content</div>
</section>`;

test('home hero uses three local responsive lobby photos and accessible rotating controls', async () => {
    assert.match(template, /home-hero-slideshow/);
    assert.match(template, /home-hero\/lobby-1-4k\.jpg/);
    assert.doesNotMatch(template, /images\.unsplash\.com\/photo-1601918774946-25832a4be0d6/);
    assert.match(stylesheet, /\.home-hero-slide/);

    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.clock.install();
        await page.setContent(`<style>${baseStylesheet}</style><style>${stylesheet}</style>${fixture}`);
        await page.addScriptTag({ path: fileURLToPath(script) });

        const hero = page.locator('.home-hero');
        const slides = page.locator('.home-hero-slide');
        assert.equal(await slides.count(), 3);
        assert.equal(await page.locator('.home-hero-controls button').count(), 6);
        assert.equal(await page.locator('.home-hero-controls').isVisible(), true);
        assert.equal(await hero.evaluate(el => getComputedStyle(el).isolation), 'isolate');
        const mobileBounds = await page.locator('.home-hero-controls').boundingBox();
        assert.ok(mobileBounds.x >= 0 && mobileBounds.x + mobileBounds.width <= 390, 'mobile controls should stay inside the screen');

        await page.clock.fastForward(4010);
        assert.equal(await page.locator('.home-hero-slide.is-active').count(), 1);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1);

        await page.locator('[data-hero-toggle]').click();
        await page.clock.fastForward(8000);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1);
        await page.locator('[data-hero-slide="2"]').click();
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 2);
        await page.locator('[data-hero-prev]').click();
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1);
        await page.close();

        const desktop = await browser.newPage({ viewport: { width: 1440, height: 900 } });
        await desktop.setContent(`<style>${baseStylesheet}</style><style>${stylesheet}</style>${fixture}`);
        const desktopHero = await desktop.locator('.home-hero').boundingBox();
        const desktopControls = await desktop.locator('.home-hero-controls').boundingBox();
        assert.equal(desktopHero.width, 1440);
        assert.ok(desktopControls.x >= 0 && desktopControls.x + desktopControls.width <= 1440, 'desktop controls should stay inside the screen');
        await desktop.close();

        const reducedMotion = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
        await reducedMotion.clock.install();
        await reducedMotion.setContent(`<style>${baseStylesheet}</style><style>${stylesheet}</style>${fixture}`);
        await reducedMotion.addScriptTag({ path: fileURLToPath(script) });
        assert.equal(await reducedMotion.locator('[data-hero-toggle]').isHidden(), true, 'rotation control should be hidden when motion is reduced');
        await reducedMotion.clock.fastForward(7000);
        assert.equal(await reducedMotion.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 0, 'reduced-motion preference should stop automatic rotation');
        await reducedMotion.locator('[data-hero-next]').click();
        assert.equal(await reducedMotion.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1, 'manual image selection should remain available');
        await reducedMotion.close();
    } finally {
        await browser.close();
    }
});
