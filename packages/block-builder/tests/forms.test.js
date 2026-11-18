import assert from 'node:assert/strict';
import { test } from 'node:test';

import { FieldError, fields, parseField, withValue } from '../src/forms.js';

const schema = {
    type: 'object',
    required: ['key'],
    properties: {
        key: { type: 'string', title: 'Key', description: 'Names the field.' },
        size: { type: 'integer', default: 3, minimum: 1, maximum: 12 },
        ratio: { type: 'number' },
        shown: { type: 'boolean', default: true },
        tone: { enum: ['info', 'warning'], default: 'info', title: 'Tone' },
        to: { type: 'string', 'x-private': true },
        columns: { type: ['integer', 'object'], 'x-control': 'responsive' },
        options: { type: 'array', items: { type: 'string' } },
        anything: {},
    },
};

test('each setting becomes a field with a control that suits its schema', () => {
    const list = fields(schema, { key: 'email', columns: { base: 1, tablet: 2 } });
    const byName = Object.fromEntries(list.map((field) => [field.name, field]));

    assert.deepEqual(list.map((field) => field.name), Object.keys(schema.properties));
    assert.equal(byName.key.control, 'text');
    assert.equal(byName.key.title, 'Key');
    assert.equal(byName.key.description, 'Names the field.');
    assert.equal(byName.key.required, true);
    assert.equal(byName.key.value, 'email');
    assert.equal(byName.size.control, 'number');
    assert.equal(byName.size.value, 3);
    assert.equal(byName.size.isDefault, true);
    assert.equal(byName.ratio.control, 'number');
    assert.equal(byName.shown.control, 'checkbox');
    assert.equal(byName.shown.value, true);
    assert.equal(byName.tone.control, 'select');
    assert.deepEqual(byName.tone.options, ['info', 'warning']);
    assert.equal(byName.to.private, true);
    assert.equal(byName.columns.control, 'json');
    assert.deepEqual(byName.columns.value, { base: 1, tablet: 2 });
    assert.equal(byName.options.control, 'json');
    assert.equal(byName.anything.control, 'json');
    assert.equal(byName.ratio.title, 'ratio');
});

test('a schema with no properties, or none at all, has no fields', () => {
    assert.deepEqual(fields({ type: 'object' }, {}), []);
    assert.deepEqual(fields(undefined, undefined), []);
});

test('what a person typed is turned into the value the schema asks for', () => {
    const byName = Object.fromEntries(fields(schema, {}).map((field) => [field.name, field]));

    assert.equal(parseField(byName.key, 'email'), 'email');
    assert.equal(parseField(byName.size, '7'), 7);
    assert.equal(parseField(byName.ratio, '1.5'), 1.5);
    assert.equal(parseField(byName.shown, false), false);
    assert.equal(parseField(byName.tone, 'warning'), 'warning');
    assert.deepEqual(parseField(byName.columns, '{"base": 2}'), { base: 2 });
    assert.equal(parseField(byName.columns, '3'), 3);
    assert.equal(parseField(byName.size, ''), undefined);
    assert.equal(parseField(byName.columns, '  '), undefined);
    assert.equal(parseField(byName.key, ''), '');
});

test('something that cannot be the value is refused before it is sent', () => {
    const byName = Object.fromEntries(fields(schema, {}).map((field) => [field.name, field]));

    assert.throws(() => parseField(byName.size, 'seven'), FieldError);
    assert.throws(() => parseField(byName.size, '1.5'), FieldError);
    assert.throws(() => parseField(byName.columns, '{not json'), FieldError);
});

test('setting a value gives new settings, and clearing one removes it', () => {
    const settings = { key: 'email', size: 4 };

    assert.deepEqual(withValue(settings, 'size', 5), { key: 'email', size: 5 });
    assert.deepEqual(withValue(settings, 'size', undefined), { key: 'email' });
    assert.deepEqual(withValue(settings, 'tone', 'info'), { key: 'email', size: 4, tone: 'info' });
    assert.deepEqual(settings, { key: 'email', size: 4 });
});

test('a setting that may be text or something else takes plain text as text', () => {
    const style = { properties: { margin: { type: ['string', 'object'] }, count: { type: ['integer', 'object'] } } };
    const [margin, count] = fields(style, {});

    assert.equal(parseField(margin, '0 auto'), '0 auto');
    assert.equal(parseField(margin, '"1rem"'), '1rem');
    assert.deepEqual(parseField(margin, '{"base": "1rem"}'), { base: '1rem' });
    assert.equal(parseField(margin, ''), undefined);
    assert.throws(() => parseField(count, 'many'), FieldError);
});

