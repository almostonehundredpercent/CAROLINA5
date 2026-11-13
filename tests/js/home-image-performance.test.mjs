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
  <div class="home-hero-slideshow" data-interval="4000" aria-label="Carolina property photos">
    <img class="home-hero-slide is-active" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" aria-hidden="true">
    <img class="home-hero-slide" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" aria-hidden="true">
    <img class="home-hero-slide" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" aria-hidden="true">
  </div>
  <div class="home-hero-glow" aria-hidden="true"></div><div class="hero-copy">Hero content</div>
</section>`;

test('hero photos crossfade automatically without visible overlay controls', async () => {
    assert.match(template, /home-hero-slideshow/);
    assert.doesNotMatch(template, /home-hero-controls|data-hero-(?:prev|next|toggle|slide)/);
    assert.match(stylesheet, /\.home-hero-slide[^\{]*\{[^}]*opacity:0[^}]*transition:opacity/s);
    assert.match(stylesheet, /\.home-hero-slide\.is-active[^\{]*\{[^}]*opacity:1/s);

    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.clock.install();
        await page.setContent(`<style>${baseStylesheet}</style><style>${stylesheet}</style><style>.home-hero{height:300px;min-height:300px}</style>${fixture}<div style="height:40px"></div>`);
        await page.addScriptTag({ path: fileURLToPath(script) });

        const hero = page.locator('.home-hero');
        const slides = page.locator('.home-hero-slide');
        assert.equal(await slides.count(), 3);
        assert.equal(await page.locator('.home-hero-controls').count(), 0);
        assert.equal(await hero.evaluate(el => getComputedStyle(el).isolation), 'isolate');
        assert.equal(await page.locator('.home-hero-slide.is-active').count(), 1);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => getComputedStyle(el).opacity), '1');
        assert.equal(await page.locator('.home-hero-slide:not(.is-active)').first().evaluate(el => getComputedStyle(el).opacity), '0');

        await page.clock.fastForward(4010);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1);

        await page.locator('.home-hero').hover();
        await page.clock.fastForward(8000);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 1, 'hovering the hero should pause photo rotation');
        await page.mouse.move(0, 320);
        await page.clock.fastForward(4010);
        assert.equal(await page.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 2);
        await page.close();

        const reducedMotion = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
        await reducedMotion.clock.install();
        await reducedMotion.setContent(`<style>${baseStylesheet}</style><style>${stylesheet}</style>${fixture}`);
        await reducedMotion.addScriptTag({ path: fileURLToPath(script) });
        await reducedMotion.clock.fastForward(9000);
        assert.equal(await reducedMotion.locator('.home-hero-slide.is-active').evaluate(el => [...el.parentElement.querySelectorAll('.home-hero-slide')].indexOf(el)), 0, 'reduced-motion preference should stop automatic rotation');
        await reducedMotion.close();
    } finally {
        await browser.close();
    }
});
