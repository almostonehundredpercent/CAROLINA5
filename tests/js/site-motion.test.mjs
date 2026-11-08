import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';
import postcss from 'postcss';

const [motionStyles, appLayout, revealScript] = await Promise.all([
    readFile(new URL('../../public/css/motion.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8'),
    readFile(new URL('../../public/js/scroll-reveal.js', import.meta.url), 'utf8').catch(() => ''),
]);

const stylesheet = postcss.parse(motionStyles);
const rules = [];
stylesheet.walkRules(rule => rules.push(rule));
const ruleFor = selector => rules.find(rule => rule.selector.split(',').some(item => item.trim() === selector));
const declaration = (rule, property) => rule?.nodes.find(node => node.type === 'decl' && node.prop === property)?.value;
const declarationFor = (selector, property) => rules
    .filter(rule => rule.selector.split(',').some(item => item.trim() === selector))
    .map(rule => declaration(rule, property))
    .find(value => value !== undefined);

test('hero copy animates in a visible sequence and availability panels open clearly', () => {
    assert.match(declaration(ruleFor('.home-hero .hero-copy > h1'), 'animation'), /^home-hero-enter \.72s/);
    assert.match(declarationFor('.home-hero .hero-copy > .home-hero-actions', 'animation-delay'), /\.32s/);
    assert.match(declaration(ruleFor('.room-availability-panel:not([hidden])'), 'animation'), /^availability-panel-enter \.32s/);
    assert.match(motionStyles, /@keyframes home-hero-enter/);
    assert.match(motionStyles, /@keyframes availability-panel-enter/);
});

test('room-card lift is restricted to fine-pointer hover and does not scale neighboring cards', () => {
    const hoverMedia = [];
    stylesheet.walkAtRules('media', rule => {
        if (/hover:\s*hover/.test(rule.params) && /pointer:\s*fine/.test(rule.params)) hoverMedia.push(rule);
    });
    assert.equal(hoverMedia.length, 1);
    const roomCardRule = hoverMedia[0].nodes.find(node => node.type === 'rule' && node.selector.split(',').some(item => item.trim() === '.rooms-card:hover'));
    assert.ok(roomCardRule, 'room cards should only lift on devices with a fine hover pointer');
    assert.equal(declaration(roomCardRule, 'transform'), 'translateY(-10px) scale(1.03)');
    assert.equal(declaration(roomCardRule, 'z-index'), '3');
    assert.equal(declarationFor('.home-hero .home-hero-primary:hover', 'transform'), 'translateY(-5px) scale(1.04)');
});

test('reduced-motion preference removes motion and the shared layout loads the motion stylesheet', () => {
    let reducedMotionRule;
    stylesheet.walkAtRules('media', rule => {
        if (/prefers-reduced-motion:\s*reduce/.test(rule.params)) reducedMotionRule = rule;
    });
    assert.ok(reducedMotionRule, 'reduced-motion users should receive a dedicated override');
    const overrideSelectors = [];
    reducedMotionRule.walkRules(rule => overrideSelectors.push(rule.selector));
    assert.ok(overrideSelectors.some(selector => selector.includes('.home-hero .hero-copy')));
    assert.ok(overrideSelectors.some(selector => selector.includes('.home-hero .hero-copy > h1')));
    assert.ok(overrideSelectors.some(selector => selector.includes('.room-availability-panel')));
    assert.match(appLayout, /asset\('css\/motion\.css'\).*filemtime/);
});

test('room and featured-room cards reveal when they enter the viewport', () => {
    assert.match(revealScript, /IntersectionObserver/);
    assert.match(revealScript, /\.home-rooms \.room-card/);
    assert.match(revealScript, /\.rooms-page \.rooms-card/);
    assert.match(revealScript, /\.ber-promo/);
    assert.match(revealScript, /is-motion-visible/);
    assert.match(motionStyles, /\.motion-reveal/);
    assert.match(motionStyles, /\.motion-reveal\.is-motion-visible/);
    assert.match(declaration(ruleFor('.motion-reveal'), 'transform'), /translateY\(44px\) scale\(\.96\)/);
    assert.match(appLayout, /asset\('js\/scroll-reveal\.js'\)/);
});
