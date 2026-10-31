import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [baseStyles, roomsStyles, promoStyles, roomsView] = await Promise.all([
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/rooms-premium.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/promo-campaign.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/rooms/index.blade.php', import.meta.url), 'utf8'),
]);
const roomsSearchScript = roomsView.match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(roomsSearchScript, 'the rooms page should provide its interactive search controls');
const roomsPaginationScript = roomsView.match(/<script>([\s\S]*?)<\/script>/g)
    ?.map(script => script.slice('<script>'.length, -'</script>'.length))
    .find(script => script.includes("const carousel = document.getElementById('rooms-carousel')"));

let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function render(viewport, { darkMode = false } = {}) {
    const page = await browser.newPage({ viewport });
    await page.setContent(`<html${darkMode ? ' class="dark-mode"' : ''}><head><style>${baseStyles}\n${roomsStyles}</style></head><body>
        <section class="section compact rooms-results">
            <form class="filter-bar rooms-search" method="GET">
                <input id="rooms-check-in" type="hidden" name="check_in" value="">
                <div class="rooms-date-control" id="rooms-date-control"><button class="rooms-date-trigger" id="rooms-check-in-trigger" type="button" aria-expanded="false" aria-controls="rooms-search-calendar"><span>Check-in date</span><b>Choose date</b></button>
                <section class="rooms-search-calendar" id="rooms-search-calendar" hidden aria-label="Choose check-in date"><div class="rooms-calendar-head"><button type="button" id="rooms-calendar-prev" aria-label="Previous month">‹</button><b id="rooms-calendar-month"></b><button type="button" id="rooms-calendar-next" aria-label="Next month">›</button></div><div class="rooms-calendar-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="rooms-calendar-days" id="rooms-calendar-days"></div><p class="rooms-calendar-note" id="rooms-calendar-note" aria-live="polite">Choose your check-in date.</p></section></div>
                <div class="rooms-choice" id="rooms-stay-choice"><span>Stay length</span><input id="rooms-stay" type="hidden" name="stay" value="day"><button id="rooms-stay-trigger" type="button" aria-expanded="false">1 day stay</button><div class="rooms-choice-menu" id="rooms-stay-menu" hidden><button type="button" data-stay="day">1 day stay</button><button type="button" data-stay="6">6 hours</button></div></div>
                <div class="rooms-time-field" id="rooms-time-field" hidden><span>Check-in time</span><input id="rooms-check-in-time" name="check_in_time" type="hidden" value="12:00"><button id="rooms-time-trigger" type="button" aria-expanded="false">Choose time</button><div class="rooms-time-menu" id="rooms-time-menu" hidden><button type="button" data-time="14:00">2 PM</button></div></div>
                <div class="rooms-choice" id="rooms-guests-choice"><span>Guests</span><input id="rooms-guests" type="hidden" name="guests" value=""><button id="rooms-guests-trigger" type="button" aria-expanded="false">Any guests</button><div class="rooms-choice-menu" id="rooms-guests-menu" hidden><button type="button" data-guests="">Any guests</button><button type="button" data-guests="2">2+ guests</button></div></div>
                <div class="rooms-sort-control" id="rooms-sort-control"><span id="rooms-sort-label">Sort by price</span><input id="rooms-sort" type="hidden" name="sort" value="recommended"><button id="rooms-sort-trigger" type="button" aria-labelledby="rooms-sort-label rooms-sort-current" aria-expanded="false" aria-controls="rooms-sort-menu"><span id="rooms-sort-current">Recommended</span></button><div class="rooms-sort-menu" id="rooms-sort-menu" role="group" aria-label="Sort by price" hidden><button type="button" data-sort="recommended" aria-pressed="true"><span>Recommended</span><small>Best overall match</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button><button type="button" data-sort="price_asc" aria-pressed="false"><span>Lowest first</span><small>Lower price to higher</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button><button type="button" data-sort="price_desc" aria-pressed="false"><span>Highest first</span><small>Higher price to lower</small><b class="rooms-sort-check" aria-hidden="true">✓</b></button></div></div>
                <button class="button">Check availability</button>
            </form>
            <p class="result-count">16 available · 2 unavailable right now</p>
            <div class="rooms-carousel"><p class="rooms-page-status">Showing rooms 1–6 of 18 · Group 1 of 3</p><div class="room-grid"><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article><article class="room-card rooms-card"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt=""><div class="room-card-body">Room preview</div></article></div></div>
        </section>
    </body></html>`, { waitUntil: 'domcontentloaded' });
    await page.addScriptTag({ content: roomsSearchScript });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    return page;
}

test('rooms promotion stays near the top, between the finder and results, on desktop and mobile', async () => {
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
            await page.setContent(`<html><head><style>${baseStyles}\n${roomsStyles}\n${promoStyles}</style></head><body>
                <header class="nav"><a class="brand" href="#">Carolina</a><nav><a>Rooms</a><a>About</a><a>Contact</a><a>My bookings</a><a>Admin</a><a class="nav-button">Book now</a><button class="theme-toggle">Dark mode</button><a class="nav-button">Sign out</a></nav></header>
                <section class="page-hero rooms-hero"><div><h1>Find a room</h1><p>Choose your dates and stay length.</p></div></section>
                <section class="section compact rooms-results"><form class="filter-bar rooms-search"><button class="rooms-date-trigger">Check-in</button><div class="rooms-choice">Stay length</div><div class="rooms-time-field">Check-in time</div><div class="rooms-choice">Guests</div><div class="rooms-sort-control">Sort by price</div><button class="button">Check availability</button></form>${promo}
                    <p class="result-count">16 available · 2 unavailable</p>
                    <div class="rooms-carousel"><p class="rooms-page-status">Showing rooms 1–6 of 18 · Group 1 of 3</p><div class="room-grid">${cards.join('')}</div></div>
                </section></body></html>`, { waitUntil: 'domcontentloaded' });

            const layout = await page.evaluate(() => {
                const rect = selector => {
                    const { x, y, width, height, bottom } = document.querySelector(selector).getBoundingClientRect();
                    return { x, y, width, height, bottom };
                };
                return {
                    intro: rect('.rooms-hero'),
                    heading: rect('.rooms-hero h1'),
                    backgroundImage: getComputedStyle(document.querySelector('.rooms-hero')).backgroundImage,
                    search: rect('.rooms-search'),
                    resultCount: rect('.result-count'),
                    grid: rect('.room-grid'),
                    promo: rect('.ber-promo-rooms'),
                    promoParent: document.querySelector('.ber-promo-rooms').parentElement.className,
                    cards: [...document.querySelectorAll('.room-grid .rooms-card')].map(card => {
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
            assert.ok([layout.heading.x, layout.search.x, layout.resultCount.x].every(left => Math.abs(left - layout.grid.x) < 2),
                `the heading, search, result count, and room cards should share a left edge at ${width}px`);
            assert.ok(layout.promo.width >= layout.grid.width - 2, `the promotion should span the room grid at ${width}px`);
            assert.ok(Math.abs(layout.promo.x - layout.grid.x) < 2, `the promotion should align with the room cards at ${width}px`);
            assert.equal(layout.promoParent, 'section compact rooms-results', `the promotion should sit outside the room-card grid at ${width}px`);
            assert.ok(layout.promo.y >= layout.search.bottom, `the promotion should follow the booking controls at ${width}px`);
            assert.ok(layout.resultCount.y >= layout.promo.bottom, `room availability should follow the promotion at ${width}px`);
            assert.ok(layout.promo.height < (width > 650 ? 190 : 285), `the promotion should stay compact at ${width}px`);
            assert.ok(layout.bookNow.includes('Book now'), 'the existing header booking action should remain');
            assert.ok(layout.controls.includes('Check availability'), 'the existing availability search should remain');
            assert.ok(layout.cards[0].y >= layout.resultCount.bottom, `room cards should follow the availability count at ${width}px`);
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
            await page.mouse.click(width - 5, 820);
            assert.equal(await page.locator('#rooms-search-calendar').isVisible(), false,
                'clicking outside should close the dropdown');
            await page.locator('#rooms-guests-trigger').click();
            assert.equal(await page.locator('#rooms-search-calendar').isVisible(), false,
                'opening a filter menu should keep the calendar closed');
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

test('check-in calendar opens as an anchored dropdown without expanding the search panel', async () => {
    assert.match(roomsView, /class="rooms-date-control" id="rooms-date-control"/,
        'the live rooms page should group the date selector and its calendar');
    assert.match(roomsView, /aria-controls="rooms-search-calendar"/,
        'the date selector should identify its dropdown for assistive technology');
    for (const width of [1274, 390, 320]) {
        const page = await render({ width, height: 850 });
        try {
            const before = await page.evaluate(() => ({
                form: document.querySelector('.rooms-search').getBoundingClientRect().toJSON(),
                results: document.querySelector('.result-count').getBoundingClientRect().toJSON(),
            }));
            await page.locator('#rooms-check-in-trigger').click();
            const layout = await page.evaluate(() => {
                const trigger = document.querySelector('#rooms-check-in-trigger').getBoundingClientRect();
                const calendar = document.querySelector('#rooms-search-calendar');
                const bounds = calendar.getBoundingClientRect();
                return {
                    position: getComputedStyle(calendar).position,
                    zIndex: Number(getComputedStyle(calendar).zIndex),
                    trigger,
                    calendar: bounds.toJSON(),
                    form: document.querySelector('.rooms-search').getBoundingClientRect().toJSON(),
                    results: document.querySelector('.result-count').getBoundingClientRect().toJSON(),
                    scrollWidth: document.documentElement.scrollWidth,
                    viewport: innerWidth,
                    isOpen: document.querySelector('#rooms-date-control').classList.contains('is-open'),
                    overlapTarget: (() => {
                        const lower = document.querySelector('#rooms-stay-choice').getBoundingClientRect();
                        const x = Math.max(bounds.left, lower.left) + 8;
                        const y = Math.max(bounds.top, lower.top) + 8;
                        return bounds.right > lower.left && bounds.left < lower.right && bounds.bottom > lower.top && bounds.top < lower.bottom
                            ? document.elementFromPoint(x, y)?.closest('#rooms-search-calendar') !== null
                            : false;
                    })(),
                };
            });
            assert.equal(layout.position, 'absolute', 'the calendar should float instead of taking form layout space');
            assert.equal(layout.isOpen, true, 'the date control should use its open stacking state');
            assert.ok(Math.abs(layout.calendar.left - layout.trigger.left) < 2, 'the calendar should align with the date field');
            assert.ok(Math.abs(layout.calendar.top - layout.trigger.bottom - 8) < 2, 'the calendar should open just below the date field');
            assert.ok(layout.zIndex >= 20, 'the calendar should stack above the filter controls');
            if (width === 1274) assert.ok(layout.calendar.width >= 450, 'the desktop calendar should remain wide enough for a readable week grid');
            assert.ok(Math.abs(layout.form.height - before.form.height) < 1, 'opening the calendar should not expand the search form');
            assert.ok(Math.abs(layout.results.top - before.results.top) < 1, 'opening the calendar should not move the results');
            if (width === 390) assert.equal(layout.overlapTarget, true, 'the calendar should stay clickable above lower filter controls');
            if (width === 320) {
                assert.ok(layout.calendar.left >= 0 && layout.calendar.right <= layout.viewport, 'calendar should fit inside the phone viewport');
                assert.ok(layout.scrollWidth <= layout.viewport, 'opening the calendar should not create horizontal scrolling');
            }
        } finally {
            await page.close();
        }
    }
});

test('check-in date selection updates the filter and dismisses the dropdown', async () => {
    const page = await render({ width: 390, height: 850 });
    try {
        await page.locator('#rooms-check-in-trigger').click();
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#rooms-search-calendar').isVisible(), false, 'Escape should dismiss the dropdown');
        assert.equal(await page.locator('#rooms-check-in-trigger').evaluate(element => element === document.activeElement), true,
            'Escape should return focus to the date selector');
        await page.locator('#rooms-check-in-trigger').click();
        const date = await page.locator('.rooms-calendar-day:not([disabled])').first().getAttribute('data-date');
        await page.locator(`.rooms-calendar-day[data-date="${date}"]`).click();
        assert.equal(await page.locator('#rooms-check-in').inputValue(), date);
        assert.notEqual(await page.locator('#rooms-check-in-trigger b').textContent(), 'Choose date');
        assert.equal(await page.locator('#rooms-search-calendar').isVisible(), false);
        assert.equal(await page.locator('#rooms-check-in-trigger').getAttribute('aria-expanded'), 'false');
        assert.equal(await page.locator('#rooms-date-control').evaluate(element => element.classList.contains('is-open')), false);
    } finally {
        await page.close();
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

test('the price sort menu stays inside a narrow phone viewport', async () => {
    const page = await render({ width: 320, height: 740 }, { darkMode: true });
    try {
        await page.locator('#rooms-sort-trigger').click();
        const geometry = await page.locator('#rooms-sort-menu').evaluate(element => {
            const { left, right, width } = element.getBoundingClientRect();
            return { left, right, width, viewport: innerWidth, document: document.documentElement.scrollWidth };
        });
        assert.ok(geometry.left >= 0 && geometry.right <= geometry.viewport,
            `the menu should fit the phone viewport (${JSON.stringify(geometry)})`);
        assert.ok(geometry.document <= geometry.viewport, 'opening the sort menu should not introduce horizontal scrolling');
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

test('room pagination sits below the cards and clearly marks the active page on desktop and mobile', async () => {
    assert.ok(roomsPaginationScript, 'the rooms page should provide its pagination behavior');
    assert.match(roomsView, /<nav class="rooms-pagination"[^>]*aria-label="Room pages"/,
        'the page selector should be an accessible navigation group');
    assert.doesNotMatch(roomsView, /rooms-page-arrow/,
        'the old arrows that float beside the cards should be removed');
    assert.ok(roomsView.indexOf('class="rooms-pagination"') > roomsView.indexOf('id="rooms-page-track"'),
        'the page selector should follow the room cards');

    for (const width of [1274, 390, 320]) {
        const page = await browser.newPage({ viewport: { width, height: 850 } });
        try {
            const pagesMarkup = [1, 2, 3].map(number => `<div class="rooms-page" data-room-page="${number}"><article class="room-card rooms-card">Room page ${number}</article></div>`).join('');
            await page.setContent(`<html><head><style>${baseStyles}\n${roomsStyles}</style></head><body>
                <div class="rooms-carousel" id="rooms-carousel">
                    <div class="rooms-page-track" id="rooms-page-track">${pagesMarkup}</div>
                    <nav class="rooms-pagination" aria-label="Room pages">
                        <p class="rooms-page-summary" id="rooms-page-summary" aria-live="polite"></p>
                        <div class="rooms-pagination-controls"><button type="button" id="rooms-page-previous">Previous</button><div class="rooms-page-numbers" id="rooms-page-numbers"></div><button type="button" id="rooms-page-next">Next</button></div>
                    </nav>
                </div>
            </body></html>`, { waitUntil: 'domcontentloaded' });
            await page.addScriptTag({ content: roomsPaginationScript.replaceAll('{{ $rooms->count() }}', '18') });
            await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

            const positions = await page.evaluate(() => {
                const track = document.querySelector('#rooms-page-track').getBoundingClientRect();
                const pager = document.querySelector('.rooms-pagination').getBoundingClientRect();
                return { trackBottom: track.bottom, pagerTop: pager.top, pageWidth: document.documentElement.scrollWidth, viewport: innerWidth };
            });
            assert.ok(positions.pagerTop >= positions.trackBottom,
                `pagination should be below the room cards at ${width}px (${JSON.stringify(positions)})`);
            assert.equal(positions.pageWidth, positions.viewport, 'pagination should not introduce horizontal scrolling');
            assert.equal(await page.locator('#rooms-page-summary').textContent(), 'Showing rooms 1–6 of 18 · Page 1 of 3');
            assert.equal(await page.locator('#rooms-page-numbers [aria-current="page"]').textContent(), '1');
            assert.equal(await page.locator('#rooms-page-previous').isDisabled(), true);

            const buttons = await page.locator('.rooms-pagination-controls button').evaluateAll(elements => elements.map(element => ({
                width: element.getBoundingClientRect().width,
                height: element.getBoundingClientRect().height,
            })));
            assert.ok(buttons.every(button => button.width >= 44 && button.height >= 44),
                `pagination controls should be comfortable tap targets at ${width}px`);

            await page.locator('#rooms-page-numbers button').filter({ hasText: '2' }).click();
            assert.equal(await page.locator('#rooms-page-summary').textContent(), 'Showing rooms 7–12 of 18 · Page 2 of 3');
            assert.equal(await page.locator('#rooms-page-numbers [aria-current="page"]').textContent(), '2');
            assert.equal(await page.locator('[data-room-page="2"]').isVisible(), true);
            assert.equal(await page.locator('[data-room-page="1"]').isVisible(), false);
            assert.equal(await page.locator('#rooms-page-previous').isDisabled(), false);
            await page.locator('#rooms-page-next').click();
            assert.equal(await page.locator('#rooms-page-summary').textContent(), 'Showing rooms 13–18 of 18 · Page 3 of 3');
            assert.equal(await page.locator('#rooms-page-next').isDisabled(), true);
        } finally {
            await page.close();
        }
    }
});
