import assert from 'node:assert/strict';
import { test } from 'node:test';

import { clipboardIntent, clipboardText, pastePosition, readClipboard } from '../src/clipboard.js';

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

test('copied blocks pasted while typing are pasted as blocks, never as their text', () => {
    const blocks = [block('a')];
    const text = clipboardText(blocks);
    const at = (over) => clipboardIntent({ kind: 'paste', text, inField: false, typing: false, textSelected: false, chosen: 1, ...over });

    assert.deepEqual(at({}), { act: 'paste', blocks });
    assert.deepEqual(at({ typing: true }), { act: 'paste', blocks, stopTyping: true });
    assert.deepEqual(at({ inField: true }), { act: 'refuse' });
    assert.deepEqual(at({ text: 'just words', typing: true }), { act: 'browser' });
    assert.deepEqual(at({ text: 'just words' }), { act: 'browser' });
});

test('copying takes blocks only when nothing else is being copied', () => {
    const at = (over) => clipboardIntent({ kind: 'copy', text: '', inField: false, typing: false, textSelected: false, chosen: 2, ...over });

    assert.deepEqual(at({}), { act: 'copy' });
    assert.deepEqual(at({ kind: 'cut' }), { act: 'copy' });
    assert.deepEqual(at({ typing: true }), { act: 'browser' });
    assert.deepEqual(at({ inField: true }), { act: 'browser' });
    assert.deepEqual(at({ textSelected: true }), { act: 'browser' });
    assert.deepEqual(at({ chosen: 0 }), { act: 'browser' });
});
