import assert from 'node:assert/strict';
import { test } from 'node:test';

import { ApiError, Refused, createClient } from '../src/api.js';

/**
 * Builds a fetch stand-in that records each call and answers from a queue.
 */
function fakeFetch(...answers) {
    const calls = [];
    const fetch = async (url, options = {}) => {
        calls.push({ url, method: options.method ?? 'GET', body: options.body, headers: options.headers });
        const answer = answers.shift() ?? { status: 200, body: {} };

        return {
            status: answer.status,
            ok: answer.status >= 200 && answer.status < 300,
            text: async () => (answer.body === undefined ? '' : JSON.stringify(answer.body)),
        };
    };

    return { fetch, calls };
}

test('reads go to the right address and give back the body', async () => {
    const { fetch, calls } = fakeFetch(
        { status: 200, body: { packages: [{ name: 'acme/blocks', title: 'Acme' }], blocks: [{ name: 'acme/box' }] } },
        { status: 200, body: { document: { id: 'a b' }, issues: [] } },
        { status: 200, body: { documents: ['home'] } },
    );
    const client = createClient({ baseUrl: '/api/', fetch, headers: { 'X-Token': 't' } });

    const blocks = await client.catalogue();
    const read = await client.document('a b');
    const list = await client.documents();

    assert.deepEqual(blocks, { packages: [{ name: 'acme/blocks', title: 'Acme' }], blocks: [{ name: 'acme/box' }] });
    assert.equal(read.document.id, 'a b');
    assert.deepEqual(list, ['home']);
    assert.deepEqual(calls.map((call) => `${call.method} ${call.url}`), [
        'GET /api/catalogue',
        'GET /api/documents/a%20b',
        'GET /api/documents',
    ]);
    assert.equal(calls[0].headers['X-Token'], 't');
});

test('the catalogue can be narrowed to a position', async () => {
    const { fetch, calls } = fakeFetch({ status: 200, body: { blocks: [] } }, { status: 200, body: { blocks: [] } });
    const client = createClient({ baseUrl: '/api', fetch });

    await client.catalogue({ document: 'home', parent: 'b/1', index: 2 });
    await client.catalogue({ document: 'home', parent: null, index: 0 });

    assert.equal(calls[0].url, '/api/catalogue?document=home&parent=b%2F1&index=2');
    assert.equal(calls[1].url, '/api/catalogue?document=home&index=0');
});

test('an operation is posted as JSON and answers with the new document', async () => {
    const answer = { document: { id: 'home' }, affected: ['x'], issues: [] };
    const { fetch, calls } = fakeFetch({ status: 200, body: answer });
    const client = createClient({ baseUrl: '/api', fetch });

    const result = await client.operate('home', { operation: 'remove', block: 't' });

    assert.deepEqual(result.affected, ['x']);
    assert.equal(calls[0].method, 'POST');
    assert.equal(calls[0].url, '/api/documents/home/operations');
    assert.deepEqual(JSON.parse(calls[0].body), { operation: 'remove', block: 't' });
    assert.equal(calls[0].headers['Content-Type'], 'application/json');
});

test('a refused operation is an error that carries every refusal', async () => {
    const refusals = [{ kind: 'invalid-settings', message: 'Too long.', block: 't', path: 'text' }];
    const { fetch } = fakeFetch({ status: 422, body: { refusals } });
    const client = createClient({ baseUrl: '/api', fetch });

    await assert.rejects(client.operate('home', { operation: 'remove', block: 't' }), (error) => {
        assert.ok(error instanceof Refused);
        assert.ok(error instanceof ApiError);
        assert.equal(error.status, 422);
        assert.deepEqual(error.refusals, refusals);

        return true;
    });
});

test('any other failure is an error with its status and the message the server gave', async () => {
    const { fetch } = fakeFetch({ status: 403, body: { error: 'You are not allowed to do that.' } }, { status: 500 });
    const client = createClient({ baseUrl: '/api', fetch });

    await assert.rejects(client.document('home'), (error) => {
        assert.ok(error instanceof ApiError);
        assert.ok(!(error instanceof Refused));
        assert.equal(error.status, 403);
        assert.equal(error.message, 'You are not allowed to do that.');

        return true;
    });
    await assert.rejects(client.document('home'), (error) => error.status === 500 && error.message !== '');
});

test('a request that never reaches the server is an error too', async () => {
    const client = createClient({
        baseUrl: '/api',
        fetch: async () => {
            throw new TypeError('network down');
        },
    });

    await assert.rejects(client.documents(), (error) => error instanceof ApiError && error.status === 0);
});

test('the remaining routes are reachable', async () => {
    const { fetch, calls } = fakeFetch(
        { status: 200, body: { breakpoints: [] } },
        { status: 201, body: { document: { id: 'new' } } },
        { status: 204 },
        { status: 200, body: { output: '<p>', css: '' } },
        { status: 200, body: { output: '<p>', css: '' } },
        { status: 200, body: { libraries: [{ name: 'core/saved' }], manage: true } },
        { status: 201, body: { template: { name: 'acme/x' } } },
        { status: 204 },
    );
    const client = createClient({ baseUrl: '/api', fetch });

    await client.vocabulary();
    await client.createDocument('new', 'page');
    await client.deleteDocument('new');
    await client.preview('home', 't');
    await client.render('home', 'admin');
    const templates = await client.templates();
    await client.saveTemplate({ name: 'acme/x', title: 'X', document: 'home', block: 'b' });
    await client.removeTemplate('acme/x');

    assert.deepEqual(calls.map((call) => `${call.method} ${call.url}`), [
        'GET /api/vocabulary',
        'POST /api/documents',
        'DELETE /api/documents/new',
        'POST /api/documents/home/preview',
        'GET /api/documents/home/render?mode=admin',
        'GET /api/templates',
        'POST /api/templates',
        'DELETE /api/templates/acme/x',
    ]);
    assert.deepEqual(JSON.parse(calls[3].body), { block: 't', mode: 'admin' });
    assert.deepEqual(templates, { libraries: [{ name: 'core/saved' }], manage: true });
});

test('media are listed with only the filters given', async () => {
    const { fetch, calls } = fakeFetch(
        { status: 200, body: { items: [{ id: '1' }], total: 1, page: 1, perPage: 24 } },
        { status: 200, body: { items: [], total: 0, page: 2, perPage: 20 } },
        { status: 200, body: { item: { id: 'a/b' } } },
    );
    const client = createClient({ baseUrl: '/api', fetch });

    const first = await client.media({ search: 'cat & dog', kind: 'image', page: 1, perPage: 24 });
    await client.media({ page: 2 });
    const item = await client.mediaItem('a/b');

    assert.deepEqual(first.items, [{ id: '1' }]);
    assert.equal(item.id, 'a/b');
    assert.deepEqual(calls.map((call) => call.url), [
        '/api/media?search=cat+%26+dog&kind=image&page=1&perPage=24',
        '/api/media?page=2',
        '/api/media/a%2Fb',
    ]);
});

test('an upload sends the file itself, named in the query, with its own type', async () => {
    const { fetch, calls } = fakeFetch({ status: 201, body: { item: { id: 'm1', title: 'a b.png' } } });
    const client = createClient({ baseUrl: '/api', fetch, headers: { 'X-Token': 't' } });
    const file = new File(['png bytes'], 'a b.png', { type: 'image/png' });

    const item = await client.upload(file);

    assert.equal(item.id, 'm1');
    assert.equal(calls[0].method, 'POST');
    assert.equal(calls[0].url, '/api/media?name=a+b.png');
    assert.equal(calls[0].body, file);
    assert.equal(calls[0].headers['Content-Type'], 'image/png');
    assert.equal(calls[0].headers['X-Token'], 't');
});

test('a refused upload comes back with the server\'s reason', async () => {
    const { fetch } = fakeFetch({ status: 415, body: { error: 'Only images, video, audio and PDF files can be uploaded.' } });
    const client = createClient({ baseUrl: '/api', fetch });

    await assert.rejects(
        client.upload(new File(['<p>'], 'x.jpg', { type: 'image/jpeg' })),
        (error) => error instanceof ApiError && error.status === 415 && error.message.startsWith('Only images'),
    );
});
