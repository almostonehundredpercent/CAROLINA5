import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const siteStyles = await readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8');
const responsiveStyles = await readFile(new URL('../../public/css/responsive.css', import.meta.url), 'utf8');
let browser;

before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

function starChoices(name, title, required = false) {
    const groupClass = name === 'rating' ? 'review-rating review-rating-overall' : 'review-rating review-rating-compact';
    return `<fieldset class="${groupClass}"><legend>${title}</legend><div class="review-stars-picker">
        ${[5, 4, 3, 2, 1].map(value => `<input class="review-star-input" type="radio" id="${name}-${value}" name="${name}" value="${value}" ${required ? 'required' : ''}>
            <label class="review-star-choice" for="${name}-${value}" title="${value} ${value === 1 ? 'star' : 'stars'}"><span aria-hidden="true">★</span><span class="sr-only">${value} stars</span></label>`).join('')}
    </div></fieldset>`;
}

async function render(width) {
    const page = await browser.newPage({ viewport: { width, height: 850 } });
    await page.setContent(`<html class="dark-mode"><head><style>${siteStyles}\n${responsiveStyles}</style></head><body>
        <main><section class="booking-review-area"><details class="review-form" open><summary>Leave a review</summary>
            <form>${starChoices('rating', 'Overall rating', true)}
                <div class="review-score-grid">${starChoices('cleanliness_rating', 'Cleanliness (optional)')}
                    ${starChoices('comfort_rating', 'Comfort (optional)')}${starChoices('value_rating', 'Value (optional)')}</div>
                <label>Comment<textarea name="comment"></textarea></label><button class="button small" type="submit">Submit review</button>
            </form>
        </details></section></main></body></html>`);
    return page;
}

test('clicking a star saves the existing numeric review rating and keeps it selected', async () => {
    const page = await render(1280);
    try {
        const fourStars = page.locator('label[for="rating-4"]');
        await fourStars.click();
        await page.mouse.move(0, 0);
        const selected = await page.locator('form').evaluate(form => ({
            value: new FormData(form).get('rating'),
            checked: form.querySelector('input[name="rating"][value="4"]').checked,
            starColors: [...form.querySelectorAll('.review-rating-overall .review-star-input')].map(input => ({
                value: input.value,
                color: getComputedStyle(input.nextElementSibling).color,
            })),
        }));
        assert.equal(selected.value, '4', 'the existing review field should submit the numeric rating');
        assert.equal(selected.checked, true);
        assert.notEqual(selected.starColors.find(star => star.value === '4').color,
            selected.starColors.find(star => star.value === '5').color,
            'the chosen rating should stay highlighted after the pointer leaves');
    } finally {
        await page.close();
    }
});

test('mobile star choices keep comfortable tap targets without horizontal overflow', async () => {
    const page = await render(390);
    try {
        const layout = await page.evaluate(() => {
            const choices = [...document.querySelectorAll('.review-rating-overall .review-star-choice')];
            return {
                smallestWidth: Math.min(...choices.map(choice => choice.getBoundingClientRect().width)),
                smallestHeight: Math.min(...choices.map(choice => choice.getBoundingClientRect().height)),
                pageWidth: document.documentElement.scrollWidth,
                viewportWidth: window.innerWidth,
            };
        });
        assert.ok(layout.smallestWidth >= 40, `star tap target is only ${layout.smallestWidth}px wide`);
        assert.ok(layout.smallestHeight >= 40, `star tap target is only ${layout.smallestHeight}px tall`);
        assert.ok(layout.pageWidth <= layout.viewportWidth, `review form overflows to ${layout.pageWidth}px`);
    } finally {
        await page.close();
    }
});
