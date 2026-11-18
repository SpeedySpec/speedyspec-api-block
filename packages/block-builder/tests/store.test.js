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

            return [{ name: 'acme/box', title: 'Box' }];
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
