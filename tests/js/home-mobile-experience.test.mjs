import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const css = await Promise.all([
    'site.css',
    'palette.css',
    'responsive.css',
    'navigation-glass.css',
    'home-premium.css',
].map(file => readFile(new URL(`../../public/css/${file}`, import.meta.url), 'utf8')));
const home = await readFile(new URL('../../resources/views/home.blade.php', import.meta.url), 'utf8');
const carouselStyles = home.match(/<style>(\.home-room-carousel[\s\S]*?)<\/style>/)?.[1];
const carouselScript = home.match(/<script>[\s\S]*?<\/script>/g)
    ?.map(script => script.replace(/^<script>|<\/script>$/g, ''))
    .find(script => script.includes("document.querySelector('.home-room-carousel')"));
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

test('mobile review cards are compact while the desktop card sizing stays intact', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        const reviews = [
            { comment: 'twas a good 6 hours solo experience, rooms nice and yeah!', author: 'Glyza Delfin', room: 'Room 8', date: 'Oct 2026' },
            { comment: 'We had a lovely stay! The place was clean, comfortable, and just as described. Check-in was easy, and our host was welcoming and responsive. We’d happily stay here again.', author: 'aerl', room: 'Room 10', date: 'Oct 2026' },
            { comment: 'Guest shared a 5-star rating.', author: 'Aeru', room: 'Room 7', date: 'Oct 2026' },
        ];
        const reviewCards = reviews.map(review => `<article class="public-review home-review-card">
            <div class="home-review-rating"><span class="review-stars">★★★★★</span><small>Verified stay</small></div>
            <p class="home-review-comment">${review.comment}</p>
            <div class="home-review-author"><div><b>${review.author}</b><small>${review.room}</small></div><time>${review.date}</time></div>
        </article>`).join('');
        await page.setContent(`<html><head><style>${css.join('\n')}</style></head><body>
            <section class="section home-reviews"><div class="section-heading"><div><span class="eyebrow">GUEST REVIEWS</span>
                <h2>Experiences shared by our guests.</h2><p>Recent reviews from completed Carolina stays.</p></div></div>
                <div class="review-list">${reviewCards}</div></section></body></html>`);

        const mobile = await page.locator('.home-reviews').evaluate(section => ({
            height: Math.round(section.getBoundingClientRect().height),
            paddingTop: getComputedStyle(section).paddingTop,
            paddingBottom: getComputedStyle(section).paddingBottom,
            headingGap: getComputedStyle(section.querySelector('.section-heading')).marginBottom,
            titleSize: getComputedStyle(section.querySelector('h2')).fontSize,
            listGap: getComputedStyle(section.querySelector('.review-list')).rowGap,
            cards: [...section.querySelectorAll('.home-review-card')].map(element => ({
                padding: getComputedStyle(element).padding,
                minHeight: getComputedStyle(element).minHeight,
                commentSize: getComputedStyle(element.querySelector('.home-review-comment')).fontSize,
                height: Math.round(element.getBoundingClientRect().height),
                commentIsVisible: element.querySelector('.home-review-comment').scrollHeight === element.querySelector('.home-review-comment').clientHeight,
            })),
        }));
        assert.equal(mobile.paddingTop, '8px', 'mobile review section should use less top spacing');
        assert.equal(mobile.paddingBottom, '24px', 'mobile review section should use less bottom spacing');
        assert.equal(mobile.headingGap, '12px', 'the heading should sit closer to the reviews');
        assert.equal(mobile.titleSize, '27.2px', 'the mobile title should remain prominent but take fewer lines');
        assert.equal(mobile.listGap, '8px', 'review cards should feel grouped without excess gaps');
        assert.ok(mobile.height < 640, `mobile review section should use less page height; got ${JSON.stringify(mobile)}`);
        assert.equal(mobile.cards.length, 3, 'all reviews should remain visible in the vertical list');
        assert.ok(mobile.cards.every(card => card.padding === '10px' && card.minHeight === '0px'), 'mobile review cards should be compact without forced height');
        assert.ok(mobile.cards.every(card => Number.parseFloat(card.commentSize) >= 14), 'review text should stay comfortably readable');
        assert.ok(mobile.cards.every(card => card.height < 180), 'each mobile review card should stay concise');
        assert.ok(mobile.cards.every(card => card.commentIsVisible), 'long review comments should remain fully visible');

        await page.setViewportSize({ width: 1280, height: 800 });
        const desktop = await page.locator('.home-reviews').evaluate(section => ({
            padding: getComputedStyle(section.querySelector('.home-review-card')).padding,
            minHeight: getComputedStyle(section.querySelector('.home-review-card')).minHeight,
            columns: getComputedStyle(section.querySelector('.review-list')).gridTemplateColumns.split(' ').length,
        }));
        assert.equal(desktop.padding, '22px', 'desktop review card padding should remain unchanged');
        assert.equal(desktop.minHeight, '230px', 'desktop review card height should remain unchanged');
        assert.equal(desktop.columns, 3, 'desktop review grid should keep its existing three columns');
    } finally {
        await page.close();
    }
});

test('all five mobile benefits fit in one row without horizontal scrolling', async () => {
    const page = await browser.newPage({ viewport: { width: 400, height: 850 } });
    try {
        await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.join('\n')}</style></head><body>
            <section class="benefits section home-benefits"><div class="section-heading center"><div><span class="eyebrow">A LITTLE MORE COMFORT</span><h2>Everything you need, already here.</h2><p>Simple thoughtful touches, included with your stay.</p></div></div><div class="benefit-grid">
                <div>Breakfast</div><div>Hygiene</div><div>Wi-Fi</div><div>Netflix</div><div>Private CR</div>
            </div></section></body></html>`);
        for (const width of [400, 320]) {
            await page.setViewportSize({ width, height: 850 });
            const layout = await page.locator('.benefit-grid > div').evaluateAll(elements => ({
                cards: elements.map(element => {
                    const rect = element.getBoundingClientRect();
                    return { top: Math.round(rect.top), left: Math.round(rect.left), width: Math.round(rect.width) };
                }),
                clientWidth: document.querySelector('.benefit-grid').clientWidth,
                scrollWidth: document.querySelector('.benefit-grid').scrollWidth,
                rowWidth: Math.round(document.querySelector('.home-benefits').getBoundingClientRect().width),
                pageWidth: document.documentElement.scrollWidth,
            }));
            assert.equal(new Set(layout.cards.map(card => card.top)).size, 1,
                `all benefit cards should stay on a single row at ${width}px`);
            assert.equal(layout.pageWidth, width, `the one-row benefits should not create page overflow at ${width}px`);
            assert.equal(layout.rowWidth, width, `the full benefits section should remain inside the phone viewport at ${width}px`);
            assert.ok(layout.cards.every(card => card.width >= 50 && card.width <= 72),
                `benefit items should use the full row width at ${width}px`);
            assert.ok(layout.scrollWidth <= layout.clientWidth,
                'the benefit row should show all five items without sideways scrolling');
            assert.ok(layout.cards.every((card, index) => index === 0 || card.left > layout.cards[index - 1].left),
                'benefits should run left-to-right in a single row');
        }

        await page.setViewportSize({ width: 1280, height: 850 });
        const desktopColumns = await page.locator('.benefit-grid').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length);
        assert.equal(desktopColumns, 5, 'desktop should keep its existing five-column layout');
    } finally {
        await page.close();
    }
});

test('mobile room carousel stays at the first room and remains manually scrollable', async () => {
    assert.ok(carouselStyles, 'the live carousel layout rules should be present');
    assert.ok(carouselScript?.includes("window.matchMedia('(max-width: 800px)').matches"),
        'autoplay and clone setup should not run on mobile');
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.join('\n')}${carouselStyles}
            .home-room-carousel { width:343px; }
            .home-room-carousel .room-card { height:150px; }
        </style></head><body><div class="home-room-carousel"><article class="room-card">Room 1</article><article class="room-card">Room 2</article></div>
            <script>${carouselScript}</script></body></html>`);
        await page.waitForTimeout(150);
        const start = await page.locator('.home-room-carousel').evaluate(element => ({
            count: element.children.length,
            scrollLeft: element.scrollLeft,
            firstX: Math.round(element.children[0].getBoundingClientRect().x),
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        }));
        assert.equal(start.count, 2, 'mobile should not be filled with autoplay clones');
        assert.ok(start.scrollLeft < 5, `mobile carousel should start at its first room, got ${start.scrollLeft}px`);
        assert.ok(start.firstX >= 0 && start.firstX < 50, `first room should be visible at the start, got x=${start.firstX}`);
        assert.ok(start.scrollWidth > start.clientWidth, 'mobile cards should still support horizontal scrolling');

        await page.locator('.home-room-carousel').evaluate(element => { element.scrollLeft = element.clientWidth; });
        const next = await page.locator('.home-room-carousel').evaluate(element => ({
            scrollLeft: element.scrollLeft,
            secondX: Math.round(element.children[1].getBoundingClientRect().x),
        }));
        assert.ok(next.scrollLeft > 0, 'users should be able to scroll the carousel by hand');
        assert.ok(next.secondX < start.clientWidth, 'the next room should move into view');
    } finally {
        await page.close();
    }
});

test('mobile theme action stays legible over hero content in either site theme', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        await page.setContent(`<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css.join('\n')}</style></head>
            <body class="menu-open"><header class="nav nav--over-hero"><nav class="is-open">
                <a href="#rooms">Rooms</a><button class="theme-toggle nav-theme-toggle"><span>Dark mode</span></button>
            </nav></header><main style="height:1600px"></main></body></html>`);
        const style = await page.locator('.nav-theme-toggle').evaluate(element => ({
            text: getComputedStyle(element).color,
            background: getComputedStyle(element).backgroundColor,
            border: getComputedStyle(element).borderColor,
            label: getComputedStyle(element.querySelector('span')).color,
            bodyOverflow: getComputedStyle(document.body).overflow,
        }));
        assert.notEqual(style.bodyOverflow, 'hidden', 'open nav should not lock the page scroll');
        assert.equal(style.text, 'rgb(231, 236, 239)', 'theme control text should use the light palette color');
        assert.equal(style.label, style.text, 'visible theme label should inherit its high-contrast text color');
        assert.equal(style.background, 'rgb(57, 57, 58)', 'theme toggle should use an opaque graphite surface instead of showing the hero through it');
        await page.evaluate(() => window.scrollTo(0, 300));
        assert.ok(await page.evaluate(() => window.scrollY > 0), 'the page should scroll while the menu is open');

        await page.evaluate(() => document.documentElement.classList.add('dark-mode'));
        const darkThemeText = await page.locator('.nav-theme-toggle').evaluate(element => getComputedStyle(element).color);
        assert.equal(darkThemeText, 'rgb(231, 236, 239)', 'theme control label should remain light when dark mode is active');
    } finally {
        await page.close();
    }
});
