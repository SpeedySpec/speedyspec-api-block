import assert from 'node:assert/strict';
import { test } from 'node:test';

import { lockOf, lockedAgainstRemoval, pick, toggleInSelection } from '../src/selection.js';

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

test('a block is kept from removal by its own lock or by a lock on any block inside it', () => {
    const locked = (id, ...children) => ({ ...block(id, ...children), extensions: { 'core/lock': { remove: true } } });
    const page = [block('p', block('q', locked('deep'))), block('free', block('x')), locked('top')];

    assert.equal(lockedAgainstRemoval(page[0]), true);
    assert.equal(lockedAgainstRemoval(page[1]), false);
    assert.equal(lockedAgainstRemoval(page[2]), true);
    assert.equal(lockedAgainstRemoval({ ...block('m'), extensions: { 'core/lock': { move: true } } }), false);
});
