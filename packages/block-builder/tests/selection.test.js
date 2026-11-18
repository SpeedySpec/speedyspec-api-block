import assert from 'node:assert/strict';
import { test } from 'node:test';

import { pick } from '../src/selection.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, extensions: {}, bindings: {}, children });
const home = () => ({ id: 'home', kind: 'page', blocks: [block('a', block('a1'), block('a2')), block('b')] });

test('picking gives the blocks in document order, without any inside another picked block', () => {
    assert.deepEqual(pick(home(), ['b', 'a1', 'a']).map((picked) => picked.id), ['a', 'b']);
    assert.deepEqual(pick(home(), ['a2', 'gone', 'a1']).map((picked) => picked.id), ['a1', 'a2']);
    assert.deepEqual(pick(home(), []), []);
});
