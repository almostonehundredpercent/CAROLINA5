import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/js/site-back.js', import.meta.url), 'utf8');

function clickBackLink({ currentUrl, referrer, historyLength = 2 }) {
    const backLink = { href: 'https://carolina.example/rooms' };
    const current = new URL(currentUrl);
    let clickHandler;
    let prevented = false;
    let stopped = false;
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
        target: { closest: (selector) => selector === '[data-site-back]' ? backLink : null },
        preventDefault: () => { prevented = true; },
        stopImmediatePropagation: () => { stopped = true; },
    });

    return { prevented, stopped, backCalls, fallbackUrl: backLink.href };
}

test('room back control returns to a previous Carolina page when one exists', () => {
    const result = clickBackLink({
        currentUrl: 'https://carolina.example/rooms/room-1',
        referrer: 'https://carolina.example/rooms?sort=price_asc',
    });

    assert.equal(result.prevented, true);
    assert.equal(result.stopped, true);
    assert.equal(result.backCalls, 1);
});

test('room back control keeps its room-list fallback when the room is opened directly', () => {
    const result = clickBackLink({
        currentUrl: 'https://carolina.example/rooms/room-1',
        referrer: '',
        historyLength: 1,
    });

    assert.equal(result.prevented, false);
    assert.equal(result.backCalls, 0);
    assert.equal(result.fallbackUrl, 'https://carolina.example/rooms');
});

test('room back control does not navigate visitors back to an external site', () => {
    const result = clickBackLink({
        currentUrl: 'https://carolina.example/rooms/room-1',
        referrer: 'https://search.example/results',
    });

    assert.equal(result.prevented, false);
    assert.equal(result.backCalls, 0);
    assert.equal(result.fallbackUrl, 'https://carolina.example/rooms');
});
