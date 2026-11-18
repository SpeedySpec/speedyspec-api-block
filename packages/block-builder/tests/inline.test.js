import assert from 'node:assert/strict';
import { test } from 'node:test';

import { keyAction, safeLink, settingSchema, tidyTyped } from '../src/inline.js';

test('what a browser produced while typing is tidied to the allowed subset', () => {
    assert.equal(tidyTyped('<b>a</b> <i>b</i> <strike>c</strike> <del>d</del>'), '<strong>a</strong> <em>b</em> <s>c</s> <s>d</s>');
    assert.equal(tidyTyped('<span style="color:red">a</span><font face="x">b</font>'), 'ab');
    assert.equal(tidyTyped('one<div>two</div><div>three</div>'), 'one<br>two<br>three');
    assert.equal(tidyTyped('<p>one</p><p>two</p>'), 'one<br>two');
    assert.equal(tidyTyped('<a href="https://e.test" onclick="x" class="y">a</a>'), '<a href="https://e.test">a</a>');
    assert.equal(tidyTyped('<strong class="x" style="y">a</strong>'), '<strong>a</strong>');
    assert.equal(tidyTyped('a&nbsp;b<br>'), 'a b');
    assert.equal(tidyTyped('<div><br></div>'), '');
    assert.equal(tidyTyped('<B>a</B><I>b</I>'), '<strong>a</strong><em>b</em>');
    assert.equal(tidyTyped('plain'), 'plain');
    assert.equal(tidyTyped(''), '');
});

test('tidying leaves the allowed elements and line breaks alone', () => {
    const allowed = '<strong>a</strong><em>b</em><s>c</s><code>d</code><sub>e</sub><sup>f</sup><mark>g</mark>x<br>y';

    assert.equal(tidyTyped(allowed), allowed);
});

test('keys while typing decide what happens', () => {
    assert.equal(keyAction({ key: 'Enter', shiftKey: false }), 'commit-split');
    assert.equal(keyAction({ key: 'Enter', shiftKey: true }), 'line-break');
    assert.equal(keyAction({ key: 'Backspace', empty: true, atStart: true }), 'remove');
    assert.equal(keyAction({ key: 'Backspace', empty: false, atStart: true }), null);
    assert.equal(keyAction({ key: 'Backspace', empty: true, atStart: false }), null);
    assert.equal(keyAction({ key: 'Escape' }), 'stop');
    assert.equal(keyAction({ key: 'z', metaKey: true }), null);
    assert.equal(keyAction({ key: 'a' }), null);
    assert.equal(keyAction({ key: 'Enter', shiftKey: false, plain: true }), 'line-break');
});

test('a link is kept only when it is safe', () => {
    assert.equal(safeLink('example.com/a'), 'https://example.com/a');
    assert.equal(safeLink('https://example.com'), 'https://example.com');
    assert.equal(safeLink('mailto:a@e.test'), 'mailto:a@e.test');
    assert.equal(safeLink('tel:+1 555'), 'tel:+1 555');
    assert.equal(safeLink('/about'), '/about');
    assert.equal(safeLink('#top'), '#top');
    assert.equal(safeLink('javascript:alert(1)'), null);
    assert.equal(safeLink('JaVaScRiPt:alert(1)'), null);
    assert.equal(safeLink('data:text/html,x'), null);
    assert.equal(safeLink(' //evil.test '), null);
    assert.equal(safeLink('/\\evil.test'), null);
    assert.equal(safeLink(''), null);
    assert.equal(safeLink('   '), null);
});

test('the schema of a setting is found by its path, into lists and maps', () => {
    const schema = {
        properties: {
            text: { type: 'string', 'x-control': 'rich-text' },
            rows: { type: 'array', items: { type: 'array', items: { type: 'string', 'x-control': 'rich-text' } } },
            code: { type: 'string' },
            notes: { type: 'object', additionalProperties: { type: 'string' } },
        },
    };

    assert.equal(settingSchema(schema, 'text')['x-control'], 'rich-text');
    assert.equal(settingSchema(schema, 'rows.1.2')['x-control'], 'rich-text');
    assert.deepEqual(settingSchema(schema, 'code'), { type: 'string' });
    assert.deepEqual(settingSchema(schema, 'notes.any'), { type: 'string' });
    assert.equal(settingSchema(schema, 'missing'), null);
    assert.equal(settingSchema(undefined, 'text'), null);
});

