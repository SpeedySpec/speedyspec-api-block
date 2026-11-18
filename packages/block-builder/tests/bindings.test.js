import assert from 'node:assert/strict';
import { test } from 'node:test';

import { bindable, bindingFrom, bindingOf, describeBinding } from '../src/bindings.js';

const schema = {
    type: 'object',
    properties: { text: { type: 'string' }, level: { type: 'integer' }, secret: { type: 'string', 'x-private': true } },
};

test('every setting may be bound except private ones', () => {
    assert.deepEqual(bindable(schema), ['text', 'level']);
    assert.deepEqual(bindable(undefined), []);
});

test('a block\'s binding is read by setting', () => {
    const block = { bindings: { text: { source: 'demo/site', arguments: { key: 'tagline' } } } };

    assert.deepEqual(bindingOf(block, 'text'), { source: 'demo/site', arguments: { key: 'tagline' } });
    assert.equal(bindingOf(block, 'level'), null);
    assert.equal(bindingOf({}, 'text'), null);
});

test('a binding is described by its source and arguments, and says so when the source has gone', () => {
    const sources = [{ name: 'demo/site', arguments: {}, value: {} }];

    assert.equal(
        describeBinding({ source: 'demo/site', arguments: { key: 'tagline', lang: 'en' } }, sources),
        'From demo/site (key: tagline, lang: en)',
    );
    assert.equal(describeBinding({ source: 'demo/site', arguments: {} }, sources), 'From demo/site');
    assert.equal(
        describeBinding({ source: 'gone/away', arguments: {} }, sources),
        'From a source this site no longer has (gone/away)',
    );
});

test('the binding form becomes the binding the API takes', () => {
    assert.deepEqual(
        bindingFrom({ source: 'demo/site', arguments: { key: 'tagline', lang: '' }, useFallback: false, fallback: 'x' }),
        { source: 'demo/site', arguments: { key: 'tagline' } },
    );
    assert.deepEqual(
        bindingFrom({ source: 'demo/site', arguments: { key: 'name' }, useFallback: true, fallback: 'Hello' }),
        { source: 'demo/site', arguments: { key: 'name' }, fallback: 'Hello' },
    );
});
