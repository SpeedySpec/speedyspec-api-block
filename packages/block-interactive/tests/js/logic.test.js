import assert from 'node:assert/strict';
import { test } from 'node:test';

import { clamp, countAt, formatNumber, nextTab, remaining, slideLabel } from '../../assets/logic.js';

test('a number read from the page is kept within its bounds, or replaced when it is not a number', () => {
    assert.equal(clamp('0', 3, 30, 6), 3);
    assert.equal(clamp('-1', 3, 30, 6), 3);
    assert.equal(clamp('abc', 3, 30, 6), 6);
    assert.equal(clamp('1e9', 0, 10000, 2000), 10000);
    assert.equal(clamp(NaN, 0, 1, 0.5), 0.5);
    assert.equal(clamp('', 3, 30, 6), 6);
    assert.equal(clamp('8', 3, 30, 6), 8);
});

test('the arrow keys, Home and End move between tabs, wrapping at the ends', () => {
    assert.equal(nextTab(0, 3, 'ArrowRight'), 1);
    assert.equal(nextTab(2, 3, 'ArrowRight'), 0);
    assert.equal(nextTab(0, 3, 'ArrowLeft'), 2);
    assert.equal(nextTab(1, 3, 'Home'), 0);
    assert.equal(nextTab(1, 3, 'End'), 2);
    assert.equal(nextTab(1, 3, 'Enter'), null);
});

test('the time left is never below zero', () => {
    const day = 86_400_000;

    assert.deepEqual(remaining(day + 3_723_000, 0), { days: 1, hours: 1, minutes: 2, seconds: 3, ended: false });
    assert.deepEqual(remaining(1000, 1000), { days: 0, hours: 0, minutes: 0, seconds: 0, ended: true });
    assert.deepEqual(remaining(0, 5000), { days: 0, hours: 0, minutes: 0, seconds: 0, ended: true });
    assert.equal(remaining(50 * 365 * day, 0).days, 18250);
});

test('a count eases from its start to exactly its end, up or down', () => {
    assert.equal(countAt(0, 100, 0, 2000), 0);
    assert.equal(countAt(0, 100, 1000, 2000), 87.5);
    assert.equal(countAt(0, 100, 2000, 2000), 100);
    assert.equal(countAt(0, 100, 9999, 2000), 100);
    assert.equal(countAt(100, 0, 2000, 2000), 0);
    assert.equal(countAt(100, 0, 1000, 2000), 12.5);
    assert.equal(countAt(5, 50, 10, 0), 50);
});

test('a number is written with its decimals, prefix and suffix', () => {
    assert.equal(formatNumber(1234.5, 2, '$', '+'), '$1,234.50+');
    assert.equal(formatNumber(87.5, 0, '', '%'), '88%');
    assert.equal(formatNumber(1, 3, '', ''), '1.000');
});

test('a slide without a name is called by its place', () => {
    assert.equal(slideLabel(1, 5), 'Slide 2 of 5');
});
