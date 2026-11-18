import assert from 'node:assert/strict';
import { test } from 'node:test';

import { isColourToken, tokenGroups, tokenNameProblem, widthProblem } from '../src/site-styles.js';

test('tokens are grouped by the part before the first dot, with undotted names last under Other', () => {
    assert.deepEqual(
        tokenGroups({ 'space.md': '1rem', 'color.primary': '#000', radius: '4px', 'color.accent': '#f06', 'font-size.lg': '2rem' }),
        [
            { title: 'Color', tokens: [{ name: 'color.accent', value: '#f06' }, { name: 'color.primary', value: '#000' }] },
            { title: 'Font size', tokens: [{ name: 'font-size.lg', value: '2rem' }] },
            { title: 'Space', tokens: [{ name: 'space.md', value: '1rem' }] },
            { title: 'Other', tokens: [{ name: 'radius', value: '4px' }] },
        ],
    );
    assert.deepEqual(tokenGroups({}), []);
});

test('colour tokens are the ones named color.something', () => {
    assert.equal(isColourToken('color.primary'), true);
    assert.equal(isColourToken('colorful'), false);
    assert.equal(isColourToken('space.md'), false);
});

test('a token name is checked the way the server checks it', () => {
    const problem = 'Use lower-case letters, digits and hyphens, with dots between parts, such as color.accent.';

    assert.equal(tokenNameProblem('color.accent'), null);
    assert.equal(tokenNameProblem('radius'), null);
    assert.equal(tokenNameProblem('Color'), problem);
    assert.equal(tokenNameProblem('color..x'), problem);
    assert.equal(tokenNameProblem('1x'), problem);
    assert.equal(tokenNameProblem(''), problem);
});

test('a width is a whole number of pixels, 1 or more', () => {
    const problem = 'Use a whole number of pixels, 1 or more.';

    assert.equal(widthProblem('768'), null);
    assert.equal(widthProblem(' 1280 '), null);
    assert.equal(widthProblem('0'), problem);
    assert.equal(widthProblem('7.5'), problem);
    assert.equal(widthProblem(''), problem);
    assert.equal(widthProblem('12px'), problem);
});
