import assert from 'node:assert/strict';
import { test } from 'node:test';

import { ApiError, Refused } from '../src/api.js';
import { createBuilder } from '../src/store.js';

const block = (id, ...children) => ({ id, type: 'acme/box', settings: {}, extensions: {}, bindings: {}, children });
const home = () => ({ id: 'home', kind: 'page', blocks: [block('a', block('a1'), block('a2')), block('b')] });

/**
 * Builds a client stand-in that records operations and answers each with the given function.
 */
function fakeClient(answer = (operation, document) => ({ document, affected: [], issues: [] })) {
    const calls = [];
    let document = home();

    return {
        calls,
        document: async () => ({
            document,
            issues: [{ block: 'b', kind: 'invalid-settings', message: 'x', path: null }],
        }),
        catalogue: async (position) => {
            calls.push(['catalogue', position]);

            return { packages: [{ name: 'acme/blocks', title: 'Acme', description: '' }], blocks: [{ name: 'acme/box', title: 'Box' }] };
        },
        vocabulary: async () => ({ breakpoints: [{ name: 'tablet', minWidth: 768 }] }),
        render: async (id, mode) => {
            calls.push(['render', mode]);

            return { output: `<p>${document.blocks.length}</p>`, css: '.x{}', issues: [], assets: [] };
        },
        operate: async (id, operation) => {
            calls.push(['operate', operation]);
            const result = answer(operation, document);
            document = result.document;

            return result;
        },
    };
}

test('loading brings the document, its issues, the catalogue, the vocabulary and a preview', async () => {
    const builder = createBuilder({ client: fakeClient(), documentId: 'home' });
    const seen = [];
    builder.subscribe((state) => seen.push(state.status));

    await builder.load();
    const state = builder.state();

    assert.equal(state.status, 'ready');
    assert.equal(state.document.id, 'home');
    assert.equal(state.issues.length, 1);
    assert.deepEqual(state.catalogue.map((entry) => entry.name), ['acme/box']);
    assert.equal(state.vocabulary.breakpoints[0].name, 'tablet');
    assert.equal(state.preview.output, '<p>2</p>');
    assert.equal(state.preview.css, '.x{}');
    assert.deepEqual(seen, ['loading', 'ready']);
});

test('a failed load is shown, not thrown', async () => {
    const client = fakeClient();
    client.document = async () => {
        throw new ApiError(403, 'You are not allowed to do that.');
    };
    const builder = createBuilder({ client, documentId: 'home' });

    await builder.load();

    assert.equal(builder.state().status, 'failed');
    assert.equal(builder.state().error, 'You are not allowed to do that.');
});

test('inserting sends the operation, takes the new document and selects the new block', async () => {
    const client = fakeClient((operation, document) => ({
        document: { ...document, blocks: [...document.blocks, block('new')] },
        affected: ['new'],
        issues: [],
    }));
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.insert('acme/box', { parent: null, index: 2 });

    assert.deepEqual(client.calls.find((call) => call[0] === 'operate')[1], {
        operation: 'insert',
        type: 'acme/box',
        position: { parent: null, index: 2 },
        settings: {},
    });
    assert.equal(builder.state().document.blocks.length, 3);
    assert.equal(builder.state().selected, 'new');
    assert.equal(builder.state().preview.output, '<p>3</p>');
    assert.deepEqual(builder.state().refusals, []);
});

test('a refused edit keeps the document and shows every reason, and the next good edit clears them', async () => {
    let refuse = true;
    const client = fakeClient((operation, document) => {
        if (refuse) {
            throw new Refused([{ kind: 'invalid-settings', message: 'Too long.', block: 'b', path: 'text' }]);
        }

        return { document, affected: ['b'], issues: [] };
    });
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.updateSettings('b', { text: 'x'.repeat(99) });

    assert.equal(builder.state().refusals[0].message, 'Too long.');
    assert.equal(builder.state().document.blocks.length, 2);
    assert.equal(builder.state().status, 'ready');

    refuse = false;
    await builder.updateSettings('b', { text: 'ok' });

    assert.deepEqual(builder.state().refusals, []);
});

test('a failure that is not a refusal is shown as an error and the builder stays usable', async () => {
    const client = fakeClient(() => {
        throw new ApiError(500, 'The request could not be completed.');
    });
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.remove('b');

    assert.equal(builder.state().error, 'The request could not be completed.');
    assert.equal(builder.state().status, 'ready');
    assert.equal(builder.state().document.blocks.length, 2);
});

test('removing the selected block clears the selection', async () => {
    const client = fakeClient((operation, document) => ({
        document: { ...document, blocks: document.blocks.filter((entry) => entry.id !== operation.block) },
        affected: [],
        issues: [],
    }));
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();
    builder.select('b');

    await builder.remove('b');

    assert.equal(builder.state().selected, null);
});

test('moving by one step, indenting and outdenting send the right position, or nothing at an edge', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.moveBy('a2', -1);
    await builder.moveBy('a1', -1);
    await builder.indent('b');
    await builder.outdent('a1');
    await builder.outdent('a');

    assert.deepEqual(
        client.calls.filter((call) => call[0] === 'operate').map((call) => call[1]),
        [
            { operation: 'move', block: 'a2', position: { parent: 'a', index: 0 } },
            { operation: 'move', block: 'b', position: { parent: 'a', index: 2 } },
            { operation: 'move', block: 'a1', position: { parent: null, index: 1 } },
        ],
    );
});

test('the other edits send their operations', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.duplicate('a');
    await builder.updateExtension('a', 'core/style', { margin: '0' });
    await builder.insertTemplate('acme/hero', { parent: 'a', index: 0 });

    assert.deepEqual(
        client.calls.filter((call) => call[0] === 'operate').map((call) => call[1]),
        [
            { operation: 'duplicate', block: 'a' },
            { operation: 'updateExtension', block: 'a', namespace: 'core/style', settings: { margin: '0' } },
            { operation: 'insertTemplate', template: 'acme/hero', position: { parent: 'a', index: 0 } },
        ],
    );
});

test('edits made at once reach the server one after another, in order', async () => {
    const order = [];
    const client = fakeClient();
    client.operate = async (id, operation) => {
        order.push(`start ${operation.block}`);
        await new Promise((resolve) => setTimeout(resolve, operation.block === 'a' ? 20 : 0));
        order.push(`end ${operation.block}`);

        return { document: home(), affected: [], issues: [] };
    };
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await Promise.all([builder.duplicate('a'), builder.duplicate('b')]);

    assert.deepEqual(order, ['start a', 'end a', 'start b', 'end b']);
});

test('what may be added at a position is asked of the server', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    const entries = await builder.placeable({ parent: 'a', index: 0 });

    assert.deepEqual(entries.map((entry) => entry.name), ['acme/box']);
    assert.deepEqual(client.calls.at(-1), ['catalogue', { document: 'home', parent: 'a', index: 0 }]);
});

test('a subscriber stops hearing after it unsubscribes', async () => {
    const builder = createBuilder({ client: fakeClient(), documentId: 'home' });
    let count = 0;
    const stop = builder.subscribe(() => count++);

    await builder.load();
    const heard = count;
    stop();
    builder.select('a');

    assert.equal(count, heard);
});

test('the store says when an edit is on its way and when it has landed', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();
    const seen = [];
    builder.subscribe((state) => seen.push(state.saving));

    await builder.duplicate('a');

    assert.equal(seen[0], true);
    assert.equal(seen.at(-1), false);
    assert.equal(builder.state().saving, false);
});

test('what the server refused can be put away once it has been read', async () => {
    const client = fakeClient(() => {
        throw new Refused([{ kind: 'disallowed-placement', message: 'Not there.', block: null, path: null }]);
    });
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();
    await builder.move('b', { parent: 'a', index: 0 });

    builder.dismiss();

    assert.deepEqual(builder.state().refusals, []);
    assert.equal(builder.state().error, null);
});

/**
 * Builds a client stand-in that keeps a document and applies remove, duplicate and restore to it.
 */
function editingClient() {
    const client = fakeClient((operation, document) => {
        if (operation.operation === 'restore') {
            return { document: { ...document, blocks: operation.blocks }, affected: [], issues: [] };
        }

        if (operation.operation === 'remove' || operation.operation === 'removeBlocks') {
            const gone = operation.ids ?? [operation.block];
            const blocks = document.blocks.filter((entry) => !gone.includes(entry.id));

            return { document: { ...document, blocks }, affected: [], issues: [] };
        }

        return { document: { ...document, blocks: [...document.blocks, block(`copy${document.blocks.length}`)] }, affected: [], issues: [] };
    });

    return client;
}

const ids = (builder) => builder.state().document.blocks.map((entry) => entry.id);

test('an edit can be undone and redone, by restoring the state the server gave before', async () => {
    const client = editingClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [false, false]);

    await builder.remove('b');
    await builder.duplicate('a');

    assert.deepEqual(ids(builder), ['a', 'copy1']);
    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [true, false]);

    await builder.undo();

    assert.deepEqual(ids(builder), ['a']);
    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [true, true]);
    assert.deepEqual(client.calls.at(-2), ['operate', { operation: 'restore', blocks: [home().blocks[0]] }]);

    await builder.undo();

    assert.deepEqual(ids(builder), ['a', 'b']);
    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [false, true]);

    await builder.redo();
    await builder.redo();

    assert.deepEqual(ids(builder), ['a', 'copy1']);
    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [true, false]);
});

test('a new edit after undoing forgets what could have been redone', async () => {
    const builder = createBuilder({ client: editingClient(), documentId: 'home' });
    await builder.load();
    await builder.remove('b');
    await builder.undo();

    await builder.remove('a');

    assert.deepEqual(ids(builder), ['b']);
    assert.equal(builder.state().canRedo, false);

    await builder.undo();

    assert.deepEqual(ids(builder), ['a', 'b']);
});

test('undo and redo with nothing to do send nothing', async () => {
    const client = editingClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.undo();
    await builder.redo();

    assert.equal(client.calls.filter((call) => call[0] === 'operate').length, 0);
});

test('a refused edit is not something to undo, and a refused undo keeps its place in the history', async () => {
    let refuse = true;
    const client = editingClient();
    const answer = client.operate;
    client.operate = async (id, operation) => {
        if (refuse && operation.operation !== 'remove') {
            throw new Refused([{ kind: 'unknown-type', message: 'That block type is gone.', block: null, path: null }]);
        }

        return answer(id, operation);
    };
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.duplicate('a');

    assert.equal(builder.state().canUndo, false);

    await builder.remove('b');
    await builder.undo();

    assert.deepEqual(ids(builder), ['a']);
    assert.equal(builder.state().refusals[0].message, 'That block type is gone.');
    assert.deepEqual([builder.state().canUndo, builder.state().canRedo], [true, false]);

    refuse = false;
    await builder.undo();

    assert.deepEqual(ids(builder), ['a', 'b']);
});

test('the history keeps a limited number of steps', async () => {
    const builder = createBuilder({ client: editingClient(), documentId: 'home', historyLimit: 3 });
    await builder.load();

    for (let step = 0; step < 5; step++) {
        await builder.duplicate('a');
    }

    for (let step = 0; step < 5; step++) {
        await builder.undo();
    }

    assert.equal(ids(builder).length, 4);
    assert.equal(builder.state().canUndo, false);
});

test('undoing keeps the selection when the block is still there, and drops it when it is not', async () => {
    const builder = createBuilder({ client: editingClient(), documentId: 'home' });
    await builder.load();
    await builder.duplicate('a');
    builder.select('copy2');

    await builder.undo();

    assert.equal(builder.state().selected, null);

    builder.select('a');
    await builder.redo();

    assert.equal(builder.state().selected, 'a');
});

test('loading keeps the packages that head the add-block panel sections', async () => {
    const builder = createBuilder({ client: fakeClient(), documentId: 'home' });

    await builder.load();

    assert.deepEqual(builder.state().packages, [{ name: 'acme/blocks', title: 'Acme', description: '' }]);
});

test('a block of the same type can be added right after another and selected', async () => {
    const client = fakeClient((operation, document) => ({
        document: { ...document, blocks: [document.blocks[0], document.blocks[1], block('new')] },
        affected: ['new'],
        issues: [],
    }));
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.insertAfter('b', 'acme/box');

    assert.deepEqual(client.calls.find((call) => call[0] === 'operate')[1], {
        operation: 'insert',
        type: 'acme/box',
        position: { parent: null, index: 2 },
        settings: {},
    });
    assert.equal(builder.state().selected, 'new');
});

test('adding after a block that is not there sends nothing', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.insertAfter('gone', 'acme/box');

    assert.equal(client.calls.filter((call) => call[0] === 'operate').length, 0);
});


test('a settings change is worked out when its turn comes, so it builds on the change sent before it', async () => {
    const client = fakeClient((operation, document) => ({
        document: {
            ...document,
            blocks: document.blocks.map((entry) => (entry.id === operation.block ? { ...entry, settings: operation.settings } : entry)),
        },
        affected: [operation.block],
        issues: [],
    }));
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    const first = builder.changeSettings('b', (settings) => ({ ...settings, a: 1 }));
    const second = builder.changeSettings('b', (settings) => ({ ...settings, b: 2 }));
    await Promise.all([first, second]);

    assert.deepEqual(builder.state().document.blocks[1].settings, { a: 1, b: 2 });
});

test('the preview keeps the page\'s stylesheets, and has none when the server sends none', async () => {
    const client = fakeClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    assert.deepEqual(builder.state().preview.styles, []);

    client.render = async () => ({ output: '<p>x</p>', css: '', styles: [{ name: 'a', href: '/a.css' }], issues: [], assets: [] });
    await builder.updateSettings('b', { text: 'x' });

    assert.deepEqual(builder.state().preview.styles, [{ name: 'a', href: '/a.css' }]);
});

test('pasting sends the blocks to insert and selects the first one added', async () => {
    const client = fakeClient((operation, document) => ({
        document: { ...document, blocks: [...document.blocks, block('p1'), block('p2')] },
        affected: ['p1', 'p2'],
        issues: [],
    }));
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.paste([block('a')], { parent: null, index: 2 });

    assert.deepEqual(client.calls.filter((call) => call[0] === 'operate').at(-1)[1], {
        operation: 'insertBlocks',
        blocks: [block('a')],
        position: { parent: null, index: 2 },
    });
    assert.equal(builder.state().selected, 'p1');
});

test('removing several blocks is one edit, and one undo puts them all back', async () => {
    const client = editingClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.removeMany(['b']);
    await builder.undo();
    await builder.removeMany(['a', 'b']);

    const operations = client.calls.filter((call) => call[0] === 'operate').map((call) => call[1]);

    assert.deepEqual(operations[0], { operation: 'remove', block: 'b' });
    assert.deepEqual(operations[2], { operation: 'removeBlocks', ids: ['a', 'b'] });
    assert.deepEqual(ids(builder), []);

    await builder.undo();

    assert.deepEqual(ids(builder), ['a', 'b']);
    assert.equal(builder.state().canUndo, false);
});

test('saving a template sends a name made from its title and the document, then lists templates again', async () => {
    const client = fakeClient();
    const lists = [];
    client.templates = async () => {
        lists.push('templates');

        return { libraries: [{ name: 'core/saved', title: 'Saved', templates: [] }], manage: true };
    };
    client.saveTemplate = async (template) => {
        client.calls.push(['saveTemplate', template]);

        return { template };
    };
    client.removeTemplate = async (name) => {
        client.calls.push(['removeTemplate', name]);
    };
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();
    await builder.duplicate('a');
    const canUndo = builder.state().canUndo;

    await builder.loadTemplates();
    await builder.saveTemplate({ block: 'a', title: 'My hero', description: 'Big' });
    await builder.removeTemplate('saved/my-hero');

    assert.deepEqual(client.calls.find((call) => call[0] === 'saveTemplate')[1], {
        document: 'home',
        block: 'a',
        name: 'saved/my-hero',
        title: 'My hero',
        description: 'Big',
    });
    assert.deepEqual(client.calls.find((call) => call[0] === 'removeTemplate')[1], 'saved/my-hero');
    assert.equal(lists.length, 3);
    assert.equal(builder.state().templates.manage, true);
    assert.equal(builder.state().canUndo, canUndo);
});

test('a template that cannot be saved is shown as an error', async () => {
    const client = fakeClient();
    client.templates = async () => ({ libraries: [], manage: true });
    client.saveTemplate = async () => {
        throw new ApiError(403, 'You are not allowed to do that.');
    };
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    const saved = await builder.saveTemplate({ block: 'a', title: 'X', description: '' });

    assert.equal(saved, false);
    assert.equal(builder.state().error, 'You are not allowed to do that.');
});

test('binding and unbinding a setting send their operations and can be undone', async () => {
    const client = editingClient();
    const builder = createBuilder({ client, documentId: 'home' });
    await builder.load();

    await builder.setBinding('b', 'text', { source: 'demo/site', arguments: { key: 'name' } });
    await builder.clearBinding('b', 'text');

    assert.deepEqual(
        client.calls.filter((call) => call[0] === 'operate').map((call) => call[1]),
        [
            { operation: 'setBinding', block: 'b', path: 'text', binding: { source: 'demo/site', arguments: { key: 'name' } } },
            { operation: 'clearBinding', block: 'b', path: 'text' },
        ],
    );
    assert.equal(builder.state().canUndo, true);
});
