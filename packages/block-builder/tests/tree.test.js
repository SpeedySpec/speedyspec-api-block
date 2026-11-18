import assert from 'node:assert/strict';
import { test } from 'node:test';

import { find, indentPosition, movePosition, outdentPosition, positionOf, walk } from '../src/tree.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, children });
const document = {
    id: 'home',
    blocks: [block('a', block('a1'), block('a2', block('a2x')), block('a3')), block('b')],
};

test('a block is found anywhere in the document, with where it sits', () => {
    assert.equal(find(document, 'a2x').id, 'a2x');
    assert.equal(find(document, 'nope'), null);
    assert.deepEqual(positionOf(document, 'a'), { parent: null, index: 0 });
    assert.deepEqual(positionOf(document, 'a2x'), { parent: 'a2', index: 0 });
    assert.equal(positionOf(document, 'nope'), null);
});

test('walking lists every block in order with its depth', () => {
    assert.deepEqual(
        walk(document).map((entry) => `${'-'.repeat(entry.depth)}${entry.block.id}`),
        ['a', '-a1', '-a2', '--a2x', '-a3', 'b'],
    );
    assert.deepEqual(walk({ id: 'empty', blocks: [] }), []);
});

test('moving up or down gives the position to move to, or nothing at the edge', () => {
    assert.deepEqual(movePosition(document, 'a2', -1), { parent: 'a', index: 0 });
    assert.deepEqual(movePosition(document, 'a2', 1), { parent: 'a', index: 2 });
    assert.equal(movePosition(document, 'a1', -1), null);
    assert.equal(movePosition(document, 'a3', 1), null);
    assert.deepEqual(movePosition(document, 'a', 1), { parent: null, index: 1 });
    assert.equal(movePosition(document, 'nope', 1), null);
});

test('indenting moves a block to the end of the block before it', () => {
    assert.deepEqual(indentPosition(document, 'a3'), { parent: 'a2', index: 1 });
    assert.deepEqual(indentPosition(document, 'b'), { parent: 'a', index: 3 });
    assert.equal(indentPosition(document, 'a1'), null);
});

test('outdenting moves a block to just after its parent', () => {
    assert.deepEqual(outdentPosition(document, 'a2x'), { parent: 'a', index: 2 });
    assert.deepEqual(outdentPosition(document, 'a1'), { parent: null, index: 1 });
    assert.equal(outdentPosition(document, 'a'), null);
});
