import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/js/site-back.js', import.meta.url), 'utf8');

function clickBackLink({ currentUrl, referrer, historyLength = 2, fallbackUrl }) {
    const link = { href: fallbackUrl };
    const current = new URL(currentUrl);
    let clickHandler;
    let prevented = false;
    let backCalls = 0;

    runInNewContext(script, {
        URL,
        document: {
            referrer,
            addEventListener(type, handler) {
                if (type === 'click') clickHandler = handler;
            },
        },
        window: {
            location: { href: current.href, origin: current.origin },
            history: { length: historyLength, back: () => { backCalls += 1; } },
        },
    });

    clickHandler({
        target: { closest: (selector) => selector === '[data-site-back]' ? link : null },
        preventDefault: () => { prevented = true; },
        stopImmediatePropagation() {},
    });

    return { prevented, backCalls, fallbackUrl: link.href };
}

test('shared back button returns to the prior same-site page when browser history is usable', () => {
    const result = clickBackLink({
        currentUrl: 'https://carolina.example/rooms/room-1',
        referrer: 'https://carolina.example/rooms?sort=price_asc',
        fallbackUrl: 'https://carolina.example/rooms',
    });

    assert.equal(result.prevented, true);
    assert.equal(result.backCalls, 1);
    assert.equal(result.fallbackUrl, 'https://carolina.example/rooms');
});

test('shared back button follows its safe fallback if there is no same-site history', () => {
    for (const referrer of ['', 'https://search.example/results']) {
        const result = clickBackLink({
            currentUrl: 'https://carolina.example/rooms/room-1',
            referrer,
            fallbackUrl: 'https://carolina.example/rooms',
        });

        assert.equal(result.prevented, false);
        assert.equal(result.backCalls, 0);
        assert.equal(result.fallbackUrl, 'https://carolina.example/rooms');
    }
});

