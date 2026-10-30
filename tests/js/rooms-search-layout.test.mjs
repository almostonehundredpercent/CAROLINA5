import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [baseStyles, roomsStyles, roomsView] = await Promise.all([
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/rooms/index.blade.php', import.meta.url), 'utf8'),
]);
const roomsSearchScript = roomsView.match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(roomsSearchScript, 'the rooms page should provide its interactive search controls');

let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function render(viewport, { darkMode = false } = {}) {
    const page = await browser.newPage({ viewport });
    await page.setContent(`<html${darkMode ? ' class="dark-mode"' : ''}><head><style>${baseStyles}\n${roomsStyles}</style></head><body>
        <section class="section compact rooms-results">
            <form class="filter-bar rooms-search" method="GET">
                <input id="rooms-check-in" type="hidden" name="check_in" value="">
                <button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false"><span>Check-in date</span><b>Choose date</b></button>
                <div class="rooms-choice" id="rooms-stay-choice"><span>Stay length</span><input id="rooms-stay" type="hidden" name="stay" value="day"><button id="rooms-stay-trigger" type="button" aria-expanded="false">1 day stay</button><div class="rooms-choice-menu" id="rooms-stay-menu" hidden><button type="button" data-stay="day">1 day stay</button><button type="button" data-stay="6">6 hours</button></div></div>
                <div class="rooms-time-field" id="rooms-time-field" hidden><span>Check-in time</span><input id="rooms-check-in-time" name="check_in_time" type="hidden" value="12:00"><button id="rooms-time-trigger" type="button" aria-expanded="false">Choose time</button><div class="rooms-time-menu" id="rooms-time-menu" hidden><button type="button" data-time="14:00">2 PM</button></div></div>
                <div class="rooms-choice" id="rooms-guests-choice"><span>Guests</span><input id="rooms-guests" type="hidden" name="guests" value=""><button id="rooms-guests-trigger" type="button" aria-expanded="false">Any guests</button><div class="rooms-choice-menu" id="rooms-guests-menu" hidden><button type="button" data-guests="">Any guests</button><button type="button" data-guests="2">2+ guests</button></div></div>
                <div class="rooms-sort-control" id="rooms-sort-control"><span id="rooms-sort-label">Sort by price</span><input id="rooms-sort" type="hidden" name="sort" value="recommended"><button id="rooms-sort-trigger" type="button" aria-labelledby="rooms-sort-label rooms-sort-current" aria-expanded="false" aria-controls="rooms-sort-menu"><span id="rooms-sort-current">Recommended</span></button><div class="rooms-sort-menu" id="rooms-sort-menu" role="group" aria-label="Sort by price" hidden><button type="button" data-sort="recommended" aria-pressed="true"><span>Recommended</span><small>Best overall match</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button><button type="button" data-sort="price_asc" aria-pressed="false"><span>Lowest first</span><small>Lower price to higher</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button><button type="button" data-sort="price_desc" aria-pressed="false"><span>Highest first</span><small>Higher price to lower</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button></div></div>
                <button class="button">Check availability</button>
                <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose check-in date"><div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div><div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="rooms-calendar-days" id="rooms-calendar-days"></div><p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date.</p></section>
            </form>
            <p class="result-count">16 available · 2 unavailable right now</p>
            <div class="rooms-carousel"><p class="rooms-page-status">Showing rooms 1–6 of 18 · Group 1 of 3</p><div class="room-grid"><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article></div></div>
        </section>
    </body></html>`, { waitUntil: 'domcontentloaded' });
    await page.addScriptTag({ content: roomsSearchScript });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    return page;
}

test('rooms page keeps its compact intro and inserts the promotion after featured rooms on desktop and mobile', async () => {
    const cards = Array.from({ length: 6 }, (_, index) => `
        <article class="room-card rooms-card">
            <img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Room ${index + 1}">
            <div class="room-card-body"><h3>Room ${index + 1}</h3><p>Preview room information.</p></div>
        </article>
    `);
    const promo = `<section class="ber-promo ber-promo-rooms" aria-label="Current room promotion"><div class="ber-promo-layout"><div class="ber-promo-copy"><span class="ber-promo-kicker">Ber-Months Promo · Code CAROLINA</span><h2>Fan Room Solo—with free breakfast.</h2><p>Book the 22-hour stay for only ₱450 and enter CAROLINA at checkout to save 10%.</p><div class="ber-promo-highlights"><span>Smart TV</span><span>WiFi</span><span>Pet-friendly</span></div></div><a class="button" href="#book">See Fan Room Solo →</a></div></section>`;

    for (const width of [1440, 390]) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        try {
            await page.setContent(`<html><head><style>${baseStyles}\n${roomsStyles}\n@media(min-width:901px){.rooms-grid-with-promo{grid-template-columns:repeat(3,minmax(0,1fr));}}</style></head><body>
                <header class="nav"><a class="brand" href="#">Carolina</a><nav><a>Rooms</a><a>About</a><a>Contact</a><a>My bookings</a><a>Admin</a><a class="nav-button">Book now</a><button class="theme-toggle">Dark mode</button><a class="nav-button">Sign out</a></nav></header>
                <section class="page-hero rooms-hero"><div><h1>Find a room</h1><p>Choose your dates and stay length.</p></div></section>
                <section class="section compact rooms-results"><form class="filter-bar rooms-search"><button class="rooms-date-trigger">Check-in</button><div class="rooms-choice">Stay length</div><div class="rooms-time-field">Check-in time</div><div class="rooms-choice">Guests</div><div class="rooms-sort-control">Sort by price</div><button class="button">Check availability</button></form>
                    <p class="result-count">16 available · 2 unavailable</p>
                    <div class="rooms-carousel"><p class="rooms-page-status">Showing rooms 1–6 of 18 · Group 1 of 3</p><div class="room-grid rooms-grid-with-promo">${cards.slice(0, 3).join('')}${promo}${cards.slice(3).join('')}</div></div>
                </section></body></html>`, { waitUntil: 'domcontentloaded' });

            const layout = await page.evaluate(() => {
                const rect = selector => {
                    const { x, y, width, height, bottom } = document.querySelector(selector).getBoundingClientRect();
                    return { x, y, width, height, bottom };
                };
                return {
                    intro: rect('.rooms-hero'),
                    backgroundImage: getComputedStyle(document.querySelector('.rooms-hero')).backgroundImage,
                    search: rect('.rooms-search'),
                    grid: rect('.rooms-grid-with-promo'),
                    promo: rect('.ber-promo-rooms'),
                    cards: [...document.querySelectorAll('.rooms-grid-with-promo .rooms-card')].map(card => {
                        const { y, width, bottom } = card.getBoundingClientRect();
                        return { y, width, bottom };
                    }),
                    bookNow: document.querySelector('.nav .nav-button')?.textContent.trim(),
                    controls: [...document.querySelectorAll('.rooms-search button')].map(button => button.textContent.trim()),
                };
            });

            assert.ok(layout.intro.height < 170, `the rooms intro should stay compact at ${width}px`);
            assert.equal(layout.backgroundImage, 'none', `the compact intro should not retain the tall decorative hero at ${width}px`);
            assert.ok(layout.search.y - layout.intro.bottom < 45, `the booking controls should follow the intro closely at ${width}px`);
            assert.ok(layout.promo.width >= layout.grid.width - 2, `the promotion should span the room grid at ${width}px`);
            assert.ok(layout.bookNow.includes('Book now'), 'the existing header booking action should remain');
            assert.ok(layout.controls.includes('Check availability'), 'the existing availability search should remain');

            if (width > 650) {
                assert.ok(layout.cards[2].bottom <= layout.promo.y, 'desktop promotion should follow the first three room cards');
                assert.ok(layout.cards[3].y >= layout.promo.bottom, 'remaining desktop cards should follow the promotion');
            } else {
                assert.ok(layout.cards[0].bottom <= layout.promo.y, 'mobile promotion should follow the first room card');
                assert.ok(layout.cards[1].y >= layout.promo.bottom, 'remaining mobile cards should follow the promotion');
            }
        } finally {
            await page.close();
        }
    }
});

test('price sorting keeps all room search controls readable on desktop and mobile', async () => {
    for (const width of [1274, 1024, 901, 768, 390]) {
        const page = await render({ width, height: 850 });
        try {
            const layout = await page.locator('.rooms-search').evaluate(form => {
                const bounds = selector => {
                    const rect = form.querySelector(selector).getBoundingClientRect();
                    return { left: rect.left, right: rect.right, width: rect.width };
                };
                return {
                    date: bounds('.rooms-date-trigger'),
                    sort: bounds('.rooms-sort-control'),
                    submit: bounds('.rooms-search > .button'),
                    form: form.getBoundingClientRect(),
                    items: [...form.children].filter(element => element.getBoundingClientRect().width > 0)
                        .map(element => element.getBoundingClientRect().toJSON()),
                };
            });
            assert.ok(layout.date.width >= 145, `date field should remain readable at ${width}px`);
            assert.ok(layout.sort.width >= 170, `price sort should remain readable at ${width}px`);
            assert.ok(layout.submit.left >= layout.form.left && layout.submit.right <= layout.form.right,
                `submit button should fit inside the search bar at ${width}px`);
            for (let index = 0; index < layout.items.length; index++) {
                for (let other = index + 1; other < layout.items.length; other++) {
                    const first = layout.items[index], second = layout.items[other];
                    const overlaps = first.left < second.right && first.right > second.left
                        && first.top < second.bottom && first.bottom > second.top;
                    assert.equal(overlaps, false, `search controls should not overlap at ${width}px`);
                }
            }
        } finally {
            await page.close();
        }
    }
});

test('price sort dropdown is styled consistently and has clear keyboard focus in both themes', async () => {
    for (const darkMode of [false, true]) {
        const page = await render({ width: 1274, height: 850 }, { darkMode });
        try {
            const control = page.locator('.rooms-sort-control');
            const initialBorder = await control.evaluate(element => getComputedStyle(element).borderColor);
            await control.hover();
            await page.waitForTimeout(220);
            const hoverStyle = await control.evaluate(element => ({
                border: getComputedStyle(element).borderColor,
                shadow: getComputedStyle(element).boxShadow,
                translation: new DOMMatrixReadOnly(getComputedStyle(element).transform).m42,
            }));
            assert.notEqual(hoverStyle.border, initialBorder, 'hover should reveal the warm accent border');
            assert.notEqual(hoverStyle.shadow, 'none', 'hover should lift the control from the search surface');
            assert.ok(hoverStyle.translation < 0, 'hover should give a subtle lift');

            await page.locator('#rooms-sort-trigger').focus();
            await page.waitForTimeout(220);
            const focusShadow = await control.evaluate(element => getComputedStyle(element).boxShadow);
            assert.notEqual(focusShadow, 'none', 'keyboard focus should remain clearly visible');
        } finally {
            await page.close();
        }
    }
});

test('premium room search hover effects respect reduced-motion preferences', async () => {
    const page = await render({ width: 1274, height: 850 });
    try {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.locator('.rooms-sort-control').hover();
        await page.waitForTimeout(220);
        const offset = await page.locator('.rooms-sort-control').evaluate(element => new DOMMatrixReadOnly(getComputedStyle(element).transform).m42);
        assert.equal(offset, 0, 'reduced-motion users should not see the hover lift');
    } finally {
        await page.close();
    }
});

test('date calendar and filter dropdowns never stay open over one another', async () => {
    for (const width of [1274, 390]) {
        const page = await render({ width, height: 850 });
        try {
            await page.locator('#rooms-check-in-trigger').click();
            assert.equal(await page.locator('#rooms-search-calendar').isVisible(), true);
            await page.locator('#rooms-guests-trigger').click();
            assert.equal(await page.locator('#rooms-search-calendar').isVisible(), false,
                'opening a filter menu should close the calendar');
            assert.equal(await page.locator('#rooms-guests-menu').isVisible(), true);
            await page.locator('#rooms-check-in-trigger').click();
            assert.equal(await page.locator('#rooms-guests-menu').isVisible(), false,
                'opening the calendar should close the filter menu');
            assert.equal(await page.locator('#rooms-search-calendar').isVisible(), true);
        } finally {
            await page.close();
        }
    }
});

test('opening the calendar expands the search panel instead of covering room results', async () => {
    for (const width of [1274, 390]) {
        const page = await render({ width, height: 850 });
        try {
            await page.locator('#rooms-check-in-trigger').click();
            const bounds = await page.evaluate(() => ({
                calendarBottom: document.querySelector('#rooms-search-calendar').getBoundingClientRect().bottom,
                resultTop: document.querySelector('.result-count').getBoundingClientRect().top,
                formBottom: document.querySelector('.rooms-search').getBoundingClientRect().bottom,
            }));
            assert.ok(bounds.calendarBottom <= bounds.resultTop,
                `calendar should not cover the results on a ${width}px viewport`);
            assert.ok(bounds.formBottom >= bounds.calendarBottom,
                `calendar should remain within the expanded search panel on a ${width}px viewport`);
        } finally {
            await page.close();
        }
    }
});

test('price sort selection updates the submitted sort value and closes the menu', async () => {
    const page = await render({ width: 390, height: 700 }, { darkMode: true });
    try {
        await page.evaluate(() => document.addEventListener('submit', event => {
            event.preventDefault();
            window.submittedSort = new FormData(event.target).get('sort');
        }));
        await page.locator('#rooms-sort-trigger').click();
        assert.equal(await page.locator('#rooms-sort-menu').isVisible(), true);
        await page.locator('#rooms-sort-menu [data-sort="price_asc"]').click();
        assert.equal(await page.locator('#rooms-sort').inputValue(), 'price_asc');
        assert.equal(await page.locator('#rooms-sort-current').textContent(), 'Lowest first');
        assert.equal(await page.locator('#rooms-sort-menu').isVisible(), false);
        assert.equal(await page.evaluate(() => window.submittedSort), 'price_asc');
    } finally {
        await page.close();
    }
});

test('price sort choices work with the keyboard and Escape returns focus to the selector', async () => {
    const page = await render({ width: 390, height: 700 });
    try {
        const trigger = page.getByRole('button', { name: 'Sort by price Recommended' });
        await trigger.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('#rooms-sort-menu').isVisible(), true);
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement.dataset.sort), 'recommended');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#rooms-sort-menu').isVisible(), false);
        assert.equal(await trigger.evaluate(element => element === document.activeElement), true);
    } finally {
        await page.close();
    }
});

test('price sort choices use a high-contrast, touch-friendly dark menu', async () => {
    const page = await render({ width: 390, height: 700 }, { darkMode: true });
    try {
        await page.locator('#rooms-sort-trigger').click();
        const menu = await page.locator('#rooms-sort-menu').evaluate(element => ({
            background: getComputedStyle(element).backgroundColor,
            backgroundImage: getComputedStyle(element).backgroundImage,
            border: getComputedStyle(element).borderColor,
            display: getComputedStyle(element).display,
            choices: [...element.querySelectorAll('button')].map(button => ({
                height: button.getBoundingClientRect().height,
                color: getComputedStyle(button).color,
            })),
        }));
        assert.notEqual(menu.backgroundImage, 'none', 'the dark menu should have its own opaque gradient surface');
        assert.notEqual(menu.border, 'rgba(0, 0, 0, 0)');
        assert.equal(menu.choices.length, 3);
        assert.ok(menu.choices.every(choice => choice.height >= 44), 'each option should be easy to tap');
        assert.ok(menu.choices.every(choice => choice.color.startsWith('rgb(255,')), 'option text should remain light against the dark menu');
    } finally {
        await page.close();
    }
});

test('price sort options remain above room cards where the dropdown overlaps them', async () => {
    // At this width the menu clips the right edge of a card while the search still has three columns.
    const page = await render({ width: 930, height: 700 }, { darkMode: true });
    try {
        await page.locator('#rooms-sort-trigger').click();
        const option = await page.locator('#rooms-sort-menu [data-sort="price_desc"]').boundingBox();
        const card = await page.locator('.room-grid .rooms-card:last-child').boundingBox();
        const point = { x: Math.max(option.x, card.x) + 1, y: Math.max(option.y, card.y) + 1 };
        assert.ok(point.x < Math.min(option.x + option.width, card.x + card.width)
            && point.y < Math.min(option.y + option.height, card.y + card.height),
        `the test viewport should place part of the option over a room card (option ${JSON.stringify(option)}, card ${JSON.stringify(card)})`);
        const layering = await page.evaluate(({ x, y }) => {
            return document.elementFromPoint(x, y)?.closest('#rooms-sort-menu') !== null;
        }, point);
        assert.equal(layering, true, 'the option should be clickable instead of hidden behind the card');
    } finally {
        await page.close();
    }
});
