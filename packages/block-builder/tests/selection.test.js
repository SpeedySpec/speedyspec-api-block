import assert from 'node:assert/strict';
import { test } from 'node:test';

import { lockOf, pick, toggleInSelection, withLock } from '../src/selection.js';

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

test('a block is added to the selection beside its siblings, taken out again, or starts a new selection', () => {
    const doc = home();

    assert.deepEqual(toggleInSelection(doc, [], 'a2'), ['a2']);
    assert.deepEqual(toggleInSelection(doc, ['a2'], 'a1'), ['a1', 'a2']);
    assert.deepEqual(toggleInSelection(doc, ['a1', 'a2'], 'a1'), ['a2']);
    assert.deepEqual(toggleInSelection(doc, ['a1', 'a2'], 'b'), ['b']);
    assert.deepEqual(toggleInSelection(doc, ['b'], 'a'), ['a', 'b']);
    assert.deepEqual(toggleInSelection(doc, ['a'], 'a'), []);
});

test('blocks can be locked or unlocked together, in a copy', () => {
    const blocks = home().blocks;
    const locked = withLock(blocks, ['a1', 'b'], { move: true, remove: true });
    const unlocked = withLock(locked, ['a1'], { move: false, remove: false });

    assert.deepEqual(locked[0].children[0].extensions, { 'core/lock': { move: true, remove: true } });
    assert.deepEqual(locked[1].extensions, { 'core/lock': { move: true, remove: true } });
    assert.deepEqual(locked[0].extensions, {});
    assert.deepEqual(blocks[1].extensions, {});
    assert.deepEqual(unlocked[0].children[0].extensions, {});
    assert.deepEqual(unlocked[1].extensions, { 'core/lock': { move: true, remove: true } });
});
