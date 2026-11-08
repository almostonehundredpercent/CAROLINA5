import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';
import postcss from 'postcss';

const [motionStyles, appLayout] = await Promise.all([
    readFile(new URL('../../public/css/motion.css', import.meta.url), 'utf8'),
    readFile(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8'),
]);

const stylesheet = postcss.parse(motionStyles);
const rules = [];
stylesheet.walkRules(rule => rules.push(rule));
const ruleFor = selector => rules.find(rule => rule.selector === selector);
const declaration = (rule, property) => rule?.nodes.find(node => node.type === 'decl' && node.prop === property)?.value;

test('hero and availability panel use brief, subtle entrance animations', () => {
    assert.match(declaration(ruleFor('.home-hero .hero-copy'), 'animation'), /^home-hero-enter \.48s/);
    assert.match(declaration(ruleFor('.room-availability-panel:not([hidden])'), 'animation'), /^availability-panel-enter \.18s/);
    assert.match(motionStyles, /@keyframes home-hero-enter/);
    assert.match(motionStyles, /@keyframes availability-panel-enter/);
});

test('room-card lift is restricted to fine-pointer hover and does not scale neighboring cards', () => {
    const hoverMedia = [];
    stylesheet.walkAtRules('media', rule => {
        if (/hover:\s*hover/.test(rule.params) && /pointer:\s*fine/.test(rule.params)) hoverMedia.push(rule);
    });
    assert.equal(hoverMedia.length, 1);
    const roomCardRule = hoverMedia[0].nodes.find(node => node.type === 'rule' && node.selector === '.rooms-card:hover');
    assert.ok(roomCardRule, 'room cards should only lift on devices with a fine hover pointer');
    assert.equal(declaration(roomCardRule, 'transform'), 'translateY(-3px)');
    assert.ok(!declaration(roomCardRule, 'transform').includes('scale('), 'room cards should not enlarge or shift layout when hovered');
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
    assert.ok(overrideSelectors.some(selector => selector.includes('.room-availability-panel')));
    assert.match(appLayout, /asset\('css\/motion\.css'\).*filemtime/);
});
