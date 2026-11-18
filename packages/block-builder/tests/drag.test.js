import assert from 'node:assert/strict';
import { test } from 'node:test';

import { dropPosition, dropZone } from '../src/drag.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, children });
const page = {
    id: 'home',
    blocks: [block('a', block('a1'), block('a2', block('a2x')), block('a3')), block('b'), block('c')],
};

test('where the pointer is over a block decides before, inside or after', () => {
    assert.equal(dropZone(5, 100, true), 'before');
    assert.equal(dropZone(50, 100, true), 'inside');
    assert.equal(dropZone(90, 100, true), 'after');
    assert.equal(dropZone(30, 100, false), 'before');
    assert.equal(dropZone(70, 100, false), 'after');
    assert.equal(dropZone(0, 0, true), 'inside');
    assert.equal(dropZone(0, 0, false), 'after');
});

test('a new block dropped on a block goes before it, after it or inside it', () => {
    assert.deepEqual(dropPosition(page, null, 'a2', 'before'), { parent: 'a', index: 1 });
    assert.deepEqual(dropPosition(page, null, 'a2', 'after'), { parent: 'a', index: 2 });
    assert.deepEqual(dropPosition(page, null, 'a2', 'inside'), { parent: 'a2', index: 1 });
    assert.deepEqual(dropPosition(page, null, 'c', 'after'), { parent: null, index: 3 });
    assert.deepEqual(dropPosition(page, null, null, 'inside'), { parent: null, index: 3 });
    assert.equal(dropPosition(page, null, 'gone', 'after'), null);
});

test('a moved block lands where it was dropped, allowing for the gap it leaves behind', () => {
    // Among a1, a2, a3: the API reads the position with the moved block already taken out.
    assert.deepEqual(dropPosition(page, 'a1', 'a3', 'before'), { parent: 'a', index: 1 });
    assert.deepEqual(dropPosition(page, 'a1', 'a3', 'after'), { parent: 'a', index: 2 });
    assert.deepEqual(dropPosition(page, 'a3', 'a1', 'before'), { parent: 'a', index: 0 });
    assert.deepEqual(dropPosition(page, 'a3', 'a1', 'after'), { parent: 'a', index: 1 });
    assert.deepEqual(dropPosition(page, 'b', 'a2', 'after'), { parent: 'a', index: 2 });
    assert.deepEqual(dropPosition(page, 'a1', 'c', 'after'), { parent: null, index: 3 });
    assert.deepEqual(dropPosition(page, 'a', 'c', 'after'), { parent: null, index: 2 });
    assert.deepEqual(dropPosition(page, 'b', 'a', 'inside'), { parent: 'a', index: 3 });
    assert.deepEqual(dropPosition(page, 'a1', 'a', 'inside'), { parent: 'a', index: 2 });
    assert.deepEqual(dropPosition(page, 'a', null, 'inside'), { parent: null, index: 2 });
});

test('a block cannot be dropped on itself, inside itself, or where it already is', () => {
    assert.equal(dropPosition(page, 'a', 'a', 'inside'), null);
    assert.equal(dropPosition(page, 'a', 'a', 'before'), null);
    assert.equal(dropPosition(page, 'a', 'a2x', 'after'), null);
    assert.equal(dropPosition(page, 'a2', 'a2x', 'inside'), null);
    assert.equal(dropPosition(page, 'a2', 'a1', 'after'), null);
    assert.equal(dropPosition(page, 'a2', 'a3', 'before'), null);
    assert.equal(dropPosition(page, 'c', null, 'inside'), null);
    assert.equal(dropPosition(page, 'gone', 'a', 'inside'), null);
});
