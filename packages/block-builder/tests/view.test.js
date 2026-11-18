import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    breadcrumb,
    canvasDocument,
    devices,
    fieldRefusal,
    groupCatalogue,
    insertTargets,
    readResponsive,
    styleSections,
    withGroup,
    withRule,
    withRuleChange,
    withoutRule,
    writeResponsive,
} from '../src/view.js';

const catalogue = [
    { name: 'core/section', title: 'Section', description: 'A container.', category: 'layout', keywords: ['group'], acceptsChildren: true },
    { name: 'core/element', title: 'Element', description: 'A heading or paragraph.', category: 'layout', keywords: ['text'], acceptsChildren: true },
    { name: 'shop/price', title: 'Price', description: 'What it costs.', category: 'shop', keywords: [], acceptsChildren: false },
    { name: 'acme/odd', title: 'Odd', description: '', category: '', keywords: [], acceptsChildren: false },
];
const block = (id, type, ...children) => ({ id, type, settings: {}, children });
const page = {
    id: 'home',
    blocks: [block('s', 'core/section', block('e', 'core/element'), block('p', 'shop/price')), block('z', 'acme/odd')],
};

const packages = [
    { name: 'core/structure-types', title: 'Layout', description: 'Sections and columns.' },
    { name: 'shop/blocks', title: 'Shop', description: 'Prices.' },
];
const owned = [
    { ...catalogue[0], package: 'core/structure-types' },
    { ...catalogue[1], package: 'core/structure-types', category: 'text' },
    { ...catalogue[2], package: 'shop/blocks' },
    { ...catalogue[3], package: null },
];
const names = (sections) =>
    sections.flatMap((section) => section.groups.flatMap((group) => group.entries.map((entry) => entry.name)));

test('the add-block panel has a section per plugin, in the order the plugins are listed', () => {
    const sections = groupCatalogue(owned, packages, '');

    assert.deepEqual(sections.map((section) => section.title), ['Layout', 'Shop', 'Added by the site']);
    assert.equal(sections[0].description, 'Sections and columns.');
    assert.deepEqual(sections[0].groups.map((group) => group.category), ['Layout', 'Text']);
    assert.deepEqual(sections[1].groups.map((group) => group.category), ['']);
    assert.deepEqual(names(sections), ['core/section', 'core/element', 'shop/price', 'acme/odd']);
});

test('searching covers every section and matches a plugin by its title', () => {
    assert.deepEqual(groupCatalogue(owned, packages, 'shop').map((section) => section.title), ['Shop']);
    assert.deepEqual(names(groupCatalogue(owned, packages, 'layout')), ['core/section', 'core/element']);
    assert.deepEqual(groupCatalogue(owned, packages, 'nothing like this'), []);
});

test('searching matches a title, a keyword or a description, whatever the case', () => {
    const names = (query) => groupCatalogue(catalogue, [], query)
        .flatMap((section) => section.groups.flatMap((group) => group.entries.map((entry) => entry.name)));

    assert.deepEqual(names('SEC'), ['core/section']);
    assert.deepEqual(names('text'), ['core/element']);
    assert.deepEqual(names('costs'), ['shop/price']);
    assert.deepEqual(names('  '), catalogue.map((entry) => entry.name));
    assert.deepEqual(names('nothing like this'), []);
});

test('a new block goes inside the selected block when it can hold blocks, or after it', () => {
    assert.deepEqual(insertTargets(page, 's', catalogue), [
        { label: 'Inside Section', position: { parent: 's', index: 2 } },
        { label: 'After Section', position: { parent: null, index: 1 } },
    ]);
    assert.deepEqual(insertTargets(page, 'p', catalogue), [
        { label: 'After Price', position: { parent: 's', index: 2 } },
    ]);
    assert.deepEqual(insertTargets(page, null, catalogue), [
        { label: 'At the end of the page', position: { parent: null, index: 2 } },
    ]);
    assert.deepEqual(insertTargets(page, 'gone', catalogue), [
        { label: 'At the end of the page', position: { parent: null, index: 2 } },
    ]);
});

test('the breadcrumb runs from the top of the page to the block', () => {
    assert.deepEqual(breadcrumb(page, 'e').map((entry) => entry.id), ['s', 'e']);
    assert.deepEqual(breadcrumb(page, 'z').map((entry) => entry.id), ['z']);
    assert.deepEqual(breadcrumb(page, 'gone'), []);
});

test('a value that may differ by width is read for one width', () => {
    assert.equal(readResponsive('1rem', ''), '1rem');
    assert.equal(readResponsive('1rem', 'tablet'), '');
    assert.equal(readResponsive({ base: '1rem', tablet: '2rem' }, ''), '1rem');
    assert.equal(readResponsive({ base: '1rem', tablet: '2rem' }, 'tablet'), '2rem');
    assert.equal(readResponsive({ tablet: '2rem' }, ''), '');
    assert.equal(readResponsive(undefined, ''), '');
    assert.equal(readResponsive(3, ''), 3);
});

test('a value that may differ by width is written in its shortest form', () => {
    assert.equal(writeResponsive(undefined, '', '1rem'), '1rem');
    assert.deepEqual(writeResponsive('1rem', 'tablet', '2rem'), { base: '1rem', tablet: '2rem' });
    assert.deepEqual(writeResponsive(undefined, 'tablet', '2rem'), { tablet: '2rem' });
    assert.equal(writeResponsive({ base: '1rem', tablet: '2rem' }, 'tablet', ''), '1rem');
    assert.deepEqual(writeResponsive({ base: '1rem', tablet: '2rem' }, '', ''), { tablet: '2rem' });
    assert.equal(writeResponsive('1rem', '', ''), undefined);
    assert.equal(writeResponsive({ base: 1 }, '', 3), 3);
});

test('the widths a page can be previewed at come from the breakpoints', () => {
    const vocabulary = { breakpoints: [{ name: 'tablet', minWidth: 768 }, { name: 'desktop', minWidth: 1024 }] };

    assert.deepEqual(devices(vocabulary), [
        { name: 'phone', label: 'Phone', width: 390 },
        { name: 'tablet', label: 'Tablet', width: 768 },
        { name: 'full', label: 'Full width', width: null },
    ]);
    assert.deepEqual(devices(null).map((device) => device.name), ['phone', 'full']);
});

test('style settings are arranged into named sections, with anything unknown at the end', () => {
    const fields = ['color', 'padding', 'textAlign', 'mystery', 'borderRadius', 'width'].map((name) => ({ name }));

    assert.deepEqual(styleSections(fields).map((section) => [section.title, section.fields.map((field) => field.name)]), [
        ['Spacing', ['padding']],
        ['Size', ['width']],
        ['Text', ['textAlign']],
        ['Colour', ['color']],
        ['Corners', ['borderRadius']],
        ['More', ['mystery']],
    ]);
});

test('visibility rules are edited without ever leaving an empty group behind', () => {
    const one = withGroup([], 'acme/signed-in');
    const two = withRule(one, 0, 'acme/role');
    const changed = withRuleChange(two, 0, 1, { negate: true, arguments: { role: 'editor' } });
    const alternative = withGroup(changed, 'acme/admin');

    assert.deepEqual(one, [[{ condition: 'acme/signed-in', arguments: {}, negate: false }]]);
    assert.equal(two[0].length, 2);
    assert.deepEqual(changed[0][1], { condition: 'acme/role', arguments: { role: 'editor' }, negate: true });
    assert.equal(alternative.length, 2);
    assert.deepEqual(withoutRule(alternative, 1, 0), changed);
    assert.deepEqual(withoutRule(one, 0, 0), []);
    assert.deepEqual(one, [[{ condition: 'acme/signed-in', arguments: {}, negate: false }]]);
});

test('a refusal is matched to the field it is about', () => {
    const refusals = [
        { kind: 'invalid-settings', message: 'Too long.', block: 't', path: 'text' },
        { kind: 'invalid-settings', message: 'Bad colour.', block: 't', path: '@core/style:color' },
        { kind: 'invalid-settings', message: 'Other block.', block: 'x', path: 'text' },
        { kind: 'invalid-settings', message: 'Deep.', block: 't', path: '@core/style:padding.tablet' },
    ];

    assert.equal(fieldRefusal(refusals, 't', 'settings', 'text'), 'Too long.');
    assert.equal(fieldRefusal(refusals, 't', 'core/style', 'color'), 'Bad colour.');
    assert.equal(fieldRefusal(refusals, 't', 'core/style', 'padding'), 'Deep.');
    assert.equal(fieldRefusal(refusals, 't', 'settings', 'color'), null);
    assert.equal(fieldRefusal(refusals, 'y', 'settings', 'text'), null);
});

test('a refusal about a format is put in words a person can act on', () => {
    const refusals = [{ kind: 'invalid-settings', message: 'Does not match the required pattern.', block: 't', path: '@core/style:color' }];

    assert.equal(fieldRefusal(refusals, 't', 'core/style', 'color'), 'That is not a value this setting accepts.');
});


test('the canvas document places linked, then inline, then collected styles before the page', () => {
    const html = canvasDocument({
        output: '<p>page</p>',
        css: '.b-1{color:blue}',
        styles: [
            { name: 'a', href: '/assets/a.css' },
            { name: 'b', content: '.b{color:red}</style><script>x</script>' },
            { name: 'c', href: 'https://cdn.test/c.css' },
        ],
    }, '');

    const linkA = html.indexOf('<link rel="stylesheet" href="/assets/a.css">');
    const linkC = html.indexOf('<link rel="stylesheet" href="https://cdn.test/c.css">');
    const inline = html.indexOf('.b{color:red}<\\/style><script>x<\\/script>');
    const collected = html.indexOf('.b-1{color:blue}');

    assert.ok(linkA > 0 && linkC > linkA && inline > linkC && collected > inline);
    assert.ok(html.endsWith('<p>page</p>'));
    assert.equal(html.includes('</style><script>x'), false);
});

test('a stylesheet address that is not safe is left out of the canvas', () => {
    const html = canvasDocument({
        output: '',
        css: '',
        styles: [
            { name: 'a', href: 'javascript:alert(1)' },
            { name: 'b', href: '//evil.test/x.css' },
            { name: 'c', href: '"><script>x</script>' },
        ],
    }, '');

    assert.equal(html.includes('<link'), false);
    assert.equal(html.includes('<script>x'), false);
});

test('a preview without styles still makes a canvas', () => {
    assert.ok(canvasDocument({ output: '<p>x</p>', css: '' }, 'body{}').includes('<style>body{}</style>'));
});
