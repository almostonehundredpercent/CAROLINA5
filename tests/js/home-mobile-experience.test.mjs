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
        await page.setContent(`<html><head><style>${css.join('\n')}</style></head><body>
            <section class="section home-reviews"><div class="review-list"><article class="public-review home-review-card">
                <div class="home-review-rating"><span class="review-stars">★★★★★</span><small>Verified stay</small></div>
                <p class="home-review-comment">A comfortable stay with a clean room and a helpful host.</p>
                <div class="home-review-author"><div><b>Guest</b><small>Room 1</small></div><time>Oct 2026</time></div>
            </article></div></section></body></html>`);

        const mobile = await page.locator('.home-review-card').evaluate(element => ({
            padding: getComputedStyle(element).padding,
            minHeight: getComputedStyle(element).minHeight,
            commentSize: getComputedStyle(element.querySelector('.home-review-comment')).fontSize,
            height: Math.round(element.getBoundingClientRect().height),
        }));
        assert.equal(mobile.padding, '14px');
        assert.equal(mobile.minHeight, '0px');
        assert.equal(Number.parseFloat(mobile.commentSize), 14.72);
        assert.ok(mobile.height < 190, `mobile review should avoid excess card height; got ${mobile.height}px`);

        await page.setViewportSize({ width: 1280, height: 800 });
        const desktop = await page.locator('.home-review-card').evaluate(element => ({
            padding: getComputedStyle(element).padding,
            minHeight: getComputedStyle(element).minHeight,
        }));
        assert.equal(desktop.padding, '22px', 'desktop review card padding should remain unchanged');
        assert.equal(desktop.minHeight, '230px', 'desktop review card height should remain unchanged');
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

test('mobile menu scrolls with the page and keeps the dark-header theme action legible', async () => {
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
        assert.equal(style.text, 'rgb(255, 246, 233)', 'theme control text should remain bright on the dark glass header');
        assert.equal(style.label, style.text, 'visible theme label should inherit its high-contrast text color');
        assert.notEqual(style.background, 'rgba(0, 0, 0, 0)', 'theme toggle should have a visible button surface');
        await page.evaluate(() => window.scrollTo(0, 300));
        assert.ok(await page.evaluate(() => window.scrollY > 0), 'the page should scroll while the menu is open');
    } finally {
        await page.close();
    }
});
