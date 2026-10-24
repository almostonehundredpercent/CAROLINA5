import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await Promise.all([
    'site.css',
    'responsive.css',
    'rooms-premium.css',
    'home-premium.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function contrastRatio(foreground, background) {
    const luminance = color => {
        const channels = color.match(/[\d.]+/g).slice(0, 3).map(Number).map(channel => {
            const value = channel / 255;
            return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        });
        return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    };
    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

test('dark-mode room reviews use a dark card with readable guest, date, comment, and detail text', async () => {
    const page = await browser.newPage();
    try {
        await page.setContent(`<html class="dark-mode"><head><style>${styles.join('\n')}</style></head><body>
            <article class="public-review"><div><b>Guest name</b><small>Verified stay · Oct 2026</small></div>
                <span class="review-stars">★★★★☆</span><p>A quiet, comfortable stay.</p>
                <small class="review-detail">Cleanliness 5/5 · Comfort 4/5 · Value 5/5</small>
            </article>
            <article class="rooms-card"><small class="card-rating"><span class="card-rating-stars">★★★★☆</span>
                <span class="card-rating-score">4.0</span><span class="card-rating-count">(1 review)</span></small></article>
            <article class="home-room-carousel"><small class="card-rating"><span class="card-rating-stars">★★★★☆</span>
                <span class="card-rating-score">4.0</span><span class="card-rating-count">(1 review)</span></small></article>
        </body></html>`);

        const result = await page.evaluate(() => {
            const card = document.querySelector('.public-review');
            const background = getComputedStyle(card).backgroundColor;
            const reviewText = [...card.querySelectorAll('b, small, p, .review-stars')]
                .map(element => ({ label: element.textContent.trim(), color: getComputedStyle(element).color }));
            const roomRatings = [...document.querySelectorAll('.rooms-card .card-rating, .home-room-carousel .card-rating')]
                .flatMap(cardRating => [...cardRating.querySelectorAll('span')])
                .map(element => ({ label: element.textContent.trim(), color: getComputedStyle(element).color }));
            return { background, reviewText, roomRatings };
        });

        assert.notEqual(result.background, 'rgb(255, 255, 255)', 'dark-mode review cards should not stay white');
        for (const text of [...result.reviewText, ...result.roomRatings]) {
            assert.ok(contrastRatio(text.color, result.background) >= 4.5,
                `${text.label} is too low contrast (${contrastRatio(text.color, result.background).toFixed(2)}:1)`);
        }
    } finally {
        await page.close();
    }
});
