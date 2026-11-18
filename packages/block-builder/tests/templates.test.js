import assert from 'node:assert/strict';
import { test } from 'node:test';

import { SAVED_LIBRARY, matchTemplates, templateName } from '../src/templates.js';

test('a template is saved under a name made from its title', () => {
    assert.equal(templateName('Hero — Café 2'), 'saved/hero-cafe-2');
    assert.equal(templateName('2 columns'), 'saved/t-2-columns');
    assert.equal(templateName('  Spaced   out  '), 'saved/spaced-out');
    assert.equal(templateName('!!!'), null);
    assert.equal(templateName(''), null);
    assert.equal(SAVED_LIBRARY, 'core/saved');
});

test('a long title gives a name of at most 64 characters after the prefix, never ending in a hyphen', () => {
    const name = templateName(`${'word '.repeat(12)}${'x'.repeat(40)}`);

    assert.ok(name.length <= 'saved/'.length + 64, name);
    assert.ok(!name.endsWith('-'), name);
    assert.match(name, /^saved\/[a-z][a-z0-9-]*$/);
});

const libraries = () => [
    {
        name: 'core/registered',
        title: 'Patterns',
        templates: [
            { name: 'acme/hero', title: 'Hero', description: 'A big banner', category: 'Headers' },
            { name: 'acme/posts', title: 'Post list', description: '', category: '' },
        ],
    },
    { name: 'core/saved', title: 'Saved', templates: [{ name: 'saved/mine', title: 'Mine', description: '', category: '' }] },
];

test('templates are found by title, description, category or their library\'s title', () => {
    const names = (query) => matchTemplates(libraries(), query)
        .map((library) => [library.name, library.templates.map((template) => template.name)]);

    assert.deepEqual(names('BANNER'), [['core/registered', ['acme/hero']]]);
    assert.deepEqual(names('head'), [['core/registered', ['acme/hero']]]);
    assert.deepEqual(names('saved'), [['core/saved', ['saved/mine']]]);
    assert.deepEqual(names(''), [['core/registered', ['acme/hero', 'acme/posts']], ['core/saved', ['saved/mine']]]);
    assert.deepEqual(names('nothing like it'), []);
});
