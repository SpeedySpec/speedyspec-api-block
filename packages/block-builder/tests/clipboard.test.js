import assert from 'node:assert/strict';
import { test } from 'node:test';

import { clipboardText, pastePosition, readClipboard } from '../src/clipboard.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, extensions: {}, bindings: {}, children });
const home = () => ({ id: 'home', kind: 'page', blocks: [block('a', block('a1'), block('a2')), block('b')] });

test('blocks written to the clipboard read back as the same blocks', () => {
    const blocks = [block('a', block('a1'))];

    assert.deepEqual(readClipboard(clipboardText(blocks)), blocks);
    assert.deepEqual(JSON.parse(clipboardText(blocks)), { type: 'speedyspec/blocks', format: 1, blocks });
});

test('clipboard text that is not our blocks is not read as blocks', () => {
    const ours = (fields) => JSON.stringify({ type: 'speedyspec/blocks', format: 1, ...fields });

    for (const text of [
        'just some words',
        '{"type": "speedyspec/blocks", "format": 1, "blocks": [',
        JSON.stringify({ blocks: [block('a')] }),
        JSON.stringify({ type: 'other/tool', format: 1, blocks: [block('a')] }),
        JSON.stringify({ type: 'speedyspec/blocks', format: 2, blocks: [block('a')] }),
        ours({ blocks: [] }),
        ours({ blocks: 'a' }),
        ours({ blocks: [{ id: 'a', type: 3 }] }),
        ours({ blocks: [null] }),
        'null',
        '[1, 2]',
        '',
    ]) {
        assert.equal(readClipboard(text), null, text);
    }
});

test('pasting goes right after the last copied block, or at the end of the page', () => {
    assert.deepEqual(pastePosition(home(), ['a1']), { parent: 'a', index: 1 });
    assert.deepEqual(pastePosition(home(), ['a2']), { parent: 'a', index: 2 });
    assert.deepEqual(pastePosition(home(), ['b', 'a']), { parent: null, index: 2 });
    assert.deepEqual(pastePosition(home(), ['a', 'a2']), { parent: null, index: 1 });
    assert.deepEqual(pastePosition(home(), []), { parent: null, index: 2 });
    assert.deepEqual(pastePosition(home(), ['gone']), { parent: null, index: 2 });
});
