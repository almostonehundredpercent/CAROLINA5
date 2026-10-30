import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [baseStyles, roomsStyles] = await Promise.all([
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8'),
]);

function roomResultsFixture() {
    return `<section class="page-hero rooms-hero"><div><h1>Find a room</h1><p>Choose your dates and stay length.</p></div></section>
        <section class="section compact rooms-results">
            <form class="filter-bar rooms-search"><button class="rooms-date-trigger">Check-in date</button><button class="button">Check availability</button></form>
            <article class="ber-promo-rooms"><div class="ber-promo-layout"><div class="ber-promo-copy"><h2>Fan Room Solo—with free breakfast.</h2><p>Book a comfortable stay.</p></div><a class="button">See room</a></div></article>
            <p class="result-count">15 available · 3 unavailable right now</p>
            <div class="rooms-carousel"><p class="rooms-page-status">Showing rooms 1–6 of 18 · Group 1 of 3</p><button class="rooms-page-arrow rooms-page-arrow-previous" type="button" aria-label="Previous rooms">←</button><div class="rooms-page-track"><div class="rooms-page"><div class="room-grid">
                <article class="room-card rooms-card"><div class="room-card-body"><h3>Room 1</h3><section class="room-card-availability"><button class="room-availability-toggle">View booked dates</button></section></div></article>
            </div></div></div><button class="rooms-page-arrow rooms-page-arrow-next" type="button" aria-label="Next rooms">→</button></div>
        </section>`;
}

test('room results use wider gutters on mobile while desktop spacing stays unchanged', async () => {
    const browser = await chromium.launch({ headless: true });

    try {
        for (const width of [320, 390, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            try {
                await page.setContent(`<html><head><style>${baseStyles}\n${roomsStyles}</style></head><body>${roomResultsFixture()}</body></html>`);
                const layout = await page.evaluate(() => {
                    const rect = selector => {
                        const element = document.querySelector(selector);
                        const { left, top, width, height } = element.getBoundingClientRect();
                        return { left: Math.round(left), top: Math.round(top), width: Math.round(width), height: Math.round(height) };
                    };
                    const style = (selector, property) => getComputedStyle(document.querySelector(selector))[property];
                    return {
                        hero: rect('.rooms-hero h1'),
                        search: rect('.rooms-search'),
                        promo: rect('.ber-promo-rooms'),
                        resultCount: rect('.result-count'),
                        carousel: rect('.rooms-carousel'),
                        card: rect('.rooms-card'),
                        previousArrow: rect('.rooms-page-arrow-previous'),
                        nextArrow: rect('.rooms-page-arrow-next'),
                        title: rect('.rooms-card h3'),
                        bookedDates: rect('.room-availability-toggle'),
                        gutters: {
                            hero: style('.rooms-hero', 'paddingLeft'),
                            search: style('.rooms-search', 'marginLeft'),
                            promo: style('.rooms-results > .ber-promo-rooms', 'marginLeft'),
                            resultCount: style('.result-count', 'marginLeft'),
                            carousel: style('.rooms-carousel', 'paddingLeft'),
                        },
                        documentWidth: document.documentElement.scrollWidth,
                    };
                });

                if (width <= 650) {
                    for (const key of ['hero', 'search', 'promo', 'resultCount', 'card']) {
                        assert.ok(layout[key].left <= 24, `${key} should align near the phone edge at ${width}px; got ${layout[key].left}px`);
                    }
                    assert.ok(layout.card.width >= width - 48, `room card should be at least ${width - 48}px wide at ${width}px; got ${layout.card.width}px`);
                    assert.equal(layout.documentWidth, width, `mobile layout should not overflow horizontally at ${width}px`);
                    const intersects = (a, b) => a.left < b.left + b.width && a.left + a.width > b.left && a.top < b.top + b.height && a.top + a.height > b.top;
                    assert.equal(intersects(layout.previousArrow, layout.title), false, `previous pagination arrow should not cover the room title at ${width}px`);
                    assert.equal(intersects(layout.previousArrow, layout.bookedDates), false, `previous pagination arrow should not cover booked dates at ${width}px`);
                    assert.equal(intersects(layout.nextArrow, layout.title), false, `next pagination arrow should not cover the room title at ${width}px`);
                    assert.equal(intersects(layout.nextArrow, layout.bookedDates), false, `next pagination arrow should not cover booked dates at ${width}px`);
                } else {
                    assert.deepEqual(layout.gutters, {
                        hero: '48px', search: '48px', promo: '48px', resultCount: '48px', carousel: '48px',
                    }, 'desktop gutters should not change');
                }
            } finally {
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
