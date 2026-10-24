import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const styles = await readFile(new URL('../../public/css/admin.css', import.meta.url), 'utf8');
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

test('review moderation is readable in both themes and has large touch-friendly controls', async () => {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
        const markup = `<head><style>${styles}</style></head><body>
            <article class="review-moderation-card">
                <div class="review-moderation-heading"><div>
                    <h3>Sam Guest <span>· Room 4</span></h3>
                    <p class="review-moderation-rating"><span>★★★★★</span><b>5 / 5</b></p>
                </div></div>
                <p class="review-moderation-guidance">Remove private details before publishing.</p>
                <form class="review-moderation-form">
                    <label for="comment">Public comment guests will see <small>Edit to remove private details.</small></label>
                    <textarea id="comment">A bright and comfortable room.</textarea>
                    <button class="review-moderation-button review-moderation-approve">Approve &amp; publish</button>
                </form>
            </article>
        </body>`;

        for (const theme of ['dark-mode', '']) {
            await page.setContent(`<html class="${theme}">${markup}</html>`);
            const result = await page.evaluate(() => {
                const card = document.querySelector('.review-moderation-card');
                const elements = [...card.querySelectorAll('h3, h3 span, .review-moderation-rating, .review-moderation-rating b, .review-moderation-pending, .review-moderation-guidance, .review-moderation-form label, .review-moderation-form label small, .review-moderation-form textarea')];
                const buttons = [...card.querySelectorAll('.review-moderation-button')];
                return {
                    background: getComputedStyle(card).backgroundColor,
                    text: elements.map(element => ({
                        label: element.textContent.trim(),
                        color: getComputedStyle(element).color,
                        background: getComputedStyle(element).backgroundColor,
                    })),
                    buttons: buttons.map(button => ({
                        label: button.textContent.trim(),
                        height: button.getBoundingClientRect().height,
                        width: button.getBoundingClientRect().width,
                        color: getComputedStyle(button).color,
                        background: getComputedStyle(button).backgroundColor,
                    })),
                };
            });

            assert.notEqual(result.background, 'rgba(0, 0, 0, 0)', `${theme || 'light'} review cards need a deliberate surface`);
            for (const text of result.text) {
                const background = text.background === 'rgba(0, 0, 0, 0)' ? result.background : text.background;
                assert.ok(contrastRatio(text.color, background) >= 4.5,
                    `${theme || 'light'}: ${text.label} is too low contrast (${contrastRatio(text.color, background).toFixed(2)}:1)`);
            }
            for (const button of result.buttons) {
                assert.ok(button.height >= 48, `${theme || 'light'}: ${button.height}px button is below the 48px touch target`);
                assert.ok(button.width >= 120, `${theme || 'light'}: ${button.width}px button is too narrow to press comfortably`);
                assert.ok(contrastRatio(button.color, button.background) >= 4.5,
                    `${theme || 'light'}: ${button.label} text is too low contrast (${contrastRatio(button.color, button.background).toFixed(2)}:1)`);
            }
        }
    } finally {
        await page.close();
    }
});
