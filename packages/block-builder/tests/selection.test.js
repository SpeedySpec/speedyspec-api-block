import assert from 'node:assert/strict';
import { test } from 'node:test';

import { lockOf, pick } from '../src/selection.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, extensions: {}, bindings: {}, children });
const home = () => ({ id: 'home', kind: 'page', blocks: [block('a', block('a1'), block('a2')), block('b')] });

test('picking gives the blocks in document order, without any inside another picked block', () => {
    assert.deepEqual(pick(home(), ['b', 'a1', 'a']).map((picked) => picked.id), ['a', 'b']);
    assert.deepEqual(pick(home(), ['a2', 'gone', 'a1']).map((picked) => picked.id), ['a1', 'a2']);
    assert.deepEqual(pick(home(), []), []);
});

test('a block\'s lock is read the way the server reads it: only true locks', () => {
    assert.deepEqual(lockOf({ extensions: { 'core/lock': { move: true, remove: 1 } } }), { move: true, remove: false });
    assert.deepEqual(lockOf({}), { move: false, remove: false });
    assert.deepEqual(lockOf(null), { move: false, remove: false });
});
