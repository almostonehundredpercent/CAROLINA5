import assert from 'node:assert/strict';
import test from 'node:test';
import { after, before } from 'node:test';
import { readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright';

const [view, baseStyles, paletteStyles] = await Promise.all([
    readFile(new URL('../../resources/views/bookings/receipt.blade.php', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/site.css', import.meta.url), 'utf8'),
    readFile(new URL('../../public/css/palette.css', import.meta.url), 'utf8'),
]);
const receiptStyles = [...view.matchAll(/<style>([\s\S]*?)<\/style>/g)].map(match => match[1]).join('\n');
const paymentScriptStart = view.indexOf("const checkoutForm = document.getElementById('paymongo-checkout-form');");
const paymentScriptEnd = view.indexOf("checkoutForm?.addEventListener('submit'", paymentScriptStart);
const paymentOptionScript = view.slice(paymentScriptStart, paymentScriptEnd)
    .replace('@json(number_format($fullPaymentAmount, 2))', '"799.00"')
    .replace('@json(number_format($amountDueNow, 2))', '"239.70"');
let browser;

before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

test('payment summary leads with amount due and retains its full breakdown and payment choices', () => {
    assert.match(view, /class="receipt-pay-now"[\s\S]*?Amount to pay now[\s\S]*?id="selected-payment-amount"/);
    assert.match(view, /class="receipt-financial-breakdown"[\s\S]*?Booking total[\s\S]*?Room rate when booked[\s\S]*?Amount paid[\s\S]*?Remaining balance/);
    assert.match(view, /id="payment-option-label"/);
    assert.match(view, /paymentOptionLabel\.textContent = option\.value === 'full' \? 'Full payment selected' : 'Downpayment selected'/);
    assert.match(view, /-minute payment window/);
    assert.match(view, /data-hold-countdown/);
    assert.match(view, /name="payment_option" value="deposit"/);
    assert.match(view, /name="payment_option" value="full"/);
});

test('changing the payment choice updates the amount and payment summary label', async () => {
    assert.ok(paymentOptionScript, 'payment option change handler should be present');
    const page = await browser.newPage();
    try {
        await page.setContent(`<form id="paymongo-checkout-form"><div class="payment-options"><label><input type="radio" name="payment_option" value="deposit" checked></label><label><input type="radio" name="payment_option" value="full"></label></div><span class="paymongo-submit-label"></span></form><b id="selected-payment-amount">₱239.70</b><b id="payment-option-label">Downpayment selected</b>`);
        await page.addScriptTag({ content: paymentOptionScript });
        await page.locator('input[value="full"]').check();

        assert.equal(await page.locator('#selected-payment-amount').textContent(), '₱799.00');
        assert.equal(await page.locator('#payment-option-label').textContent(), 'Full payment selected');
        assert.equal(await page.locator('.paymongo-submit-label').textContent(), 'Pay in full ₱799.00 with GCash');
    } finally {
        await page.close();
    }
});

test('back-to-home action has an opaque branded surface and warm white text in both themes', async () => {
    for (const mode of ['light', 'dark']) {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        try {
            await page.setContent(`<html class="${mode === 'dark' ? 'dark-mode' : ''}"><head><style>${baseStyles}\n${paletteStyles}\n${receiptStyles}</style></head><body><section class="confirmation receipt-page"><div class="receipt-actions"><a class="button receipt-home-link" href="/">Back to home</a></div></section></body></html>`);
            const colors = await page.locator('.receipt-home-link').evaluate(button => {
                const style = getComputedStyle(button);
                return { background: style.backgroundColor, text: style.color };
            });

            assert.equal(colors.background, 'rgb(119, 60, 18)', `${mode} home action should have the deep Carolina-brown fill`);
            assert.equal(colors.text, 'rgb(246, 241, 233)', `${mode} home action should use dirty-white text`);
            assert.notEqual(colors.background, 'rgba(0, 0, 0, 0)', `${mode} home action must not be transparent`);
        } finally {
            await page.close();
        }
    }
});

test('payment summary is readable and stays within desktop and mobile viewports in both themes', async () => {
    for (const mode of ['light', 'dark']) {
        for (const width of [1440, 768, 390, 320]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            try {
                await page.setContent(`<html class="${mode === 'dark' ? 'dark-mode' : ''}"><head><style>${baseStyles}\n${paletteStyles}\n${receiptStyles}</style></head><body>
                    <main class="confirmation receipt-page"><h1>Reservation received</h1><article class="receipt-unified-card"><div class="receipt-layout">
                        <section class="receipt-summary receipt-payment-primary"><h2 class="receipt-payment-title">Complete your downpayment</h2>
                            <div class="receipt-pay-now"><div><small>Amount to pay now</small><b id="selected-payment-amount">₱239.70</b></div></div>
                            <div class="receipt-financial-breakdown"><div class="receipt-financial-item"><small>Booking total</small><b>₱799.00</b><small>Promo savings</small></div><div class="receipt-financial-item"><small>Room rate when booked</small><b>₱799.00</b><small>per stay</small></div><div class="receipt-financial-item"><small>Amount paid</small><b>₱0.00</b><small>No payment received yet</small></div><div class="receipt-financial-item"><small>Remaining balance</small><b>₱799.00</b><small>Remaining amount for this reservation</small></div></div>
                            <div class="receipt-payment-facts"><div><small>Payment option</small><b>Downpayment selected</b></div><div><small>Payment method</small><b>GCash</b></div></div>
                            <div class="receipt-hold-alert"><strong>30-minute payment window</strong><p>Your room is held for up to 30 minutes.</p></div>
                            <form><div class="payment-options"><label class="payment-option"><input type="radio" name="payment_option" checked><span><strong>Pay 30% downpayment</strong><small>Pay the remaining balance at the property.</small></span></label><label class="payment-option"><input type="radio" name="payment_option"><span><strong>Pay in full</strong><small>Automatically confirm after payment verification.</small></span></label></div><button class="button paymongo-submit">Pay ₱239.70 with GCash</button></form>
                        </section><section class="receipt-stay-details"><h2 class="receipt-details-title">Stay details</h2><div><small>Stay</small><b>Nov 12, 6 AM – Nov 15, 6 PM</b></div><div><small>Guests</small><b>1 total</b></div></section>
                    </div></article></main></body></html>`);
                const result = await page.evaluate(() => {
                    const bounds = selector => {
                        const { x, y, width, height, right, bottom } = document.querySelector(selector).getBoundingClientRect();
                        return { x, y, width, height, right, bottom };
                    };
                    const payNow = document.querySelector('.receipt-pay-now');
                    const breakdown = document.querySelector('.receipt-financial-breakdown');
                    const options = document.querySelector('.payment-options');
                    return {
                        viewport: document.documentElement.clientWidth,
                        scrollWidth: document.documentElement.scrollWidth,
                        payNow: bounds('.receipt-pay-now'),
                        breakdown: bounds('.receipt-financial-breakdown'),
                        items: [...document.querySelectorAll('.receipt-financial-item')].map(item => item.getBoundingClientRect().width),
                        optionHeight: document.querySelector('.payment-option').getBoundingClientRect().height,
                        payNowBackground: getComputedStyle(payNow).backgroundColor,
                        breakdownColumns: getComputedStyle(breakdown).gridTemplateColumns,
                        optionColumns: getComputedStyle(options).gridTemplateColumns,
                    };
                });

                assert.ok(result.scrollWidth <= result.viewport, `receipt should not overflow at ${width}px in ${mode} mode: ${JSON.stringify(result)}`);
                assert.ok(result.payNow.width > 0 && result.payNow.height > 0);
                assert.ok(result.breakdown.width > 0 && result.items.every(itemWidth => itemWidth > 0));
                assert.notEqual(result.payNowBackground, 'rgba(0, 0, 0, 0)', `amount due needs a visible surface in ${mode} mode`);
                assert.ok(result.optionHeight >= 44, `payment choice should remain easy to tap at ${width}px: ${JSON.stringify(result)}`);
                if (mode === 'light' && width === 1440) await page.screenshot({ path: join(tmpdir(), 'carolina-receipt-payment-desktop.png'), fullPage: true });
                if (mode === 'dark' && width === 390) await page.screenshot({ path: join(tmpdir(), 'carolina-receipt-payment-mobile-dark.png'), fullPage: true });
            } finally {
                await page.close();
            }
        }
    }
});
