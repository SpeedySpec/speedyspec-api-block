import { Refused } from './api.js';
import { find, indentPosition, movePosition, outdentPosition } from './tree.js';

/**
 * Builds the state of one builder session: a document being edited through the API.
 *
 * It holds no rules of its own. Every edit is sent to the server, which answers with the new document or the
 * reasons it refused; the store shows whichever came back. Edits are sent one at a time, in the order they were
 * made, so the document on screen is always the one the server last answered with.
 *
 * @param {{client: object, documentId: string}} options
 */
export function createBuilder({ client, documentId }) {
    let state = {
        status: 'idle',
        document: null,
        issues: [],
        catalogue: [],
        vocabulary: null,
        selected: null,
        refusals: [],
        error: null,
        saving: false,
        preview: { output: '', css: '' },
    };
    const listeners = new Set();
    let queue = Promise.resolve();

    function set(changes) {
        state = { ...state, ...changes };
        listeners.forEach((listener) => listener(state));
    }

    async function refreshPreview() {
        try {
            const { output, css } = await client.render(documentId, 'admin');
            set({ preview: { output, css } });
        } catch (error) {
            set({ error: error.message });
        }
    }

    /**
     * Sends one operation after any already under way, and shows the answer.
     *
     * @param {object} operation The operation as the API takes it.
     * @param {(result: object) => object} [after] Extra state to set once the edit is accepted.
     */
    function edit(operation, after = () => ({})) {
        queue = queue.then(async () => {
            set({ saving: true });

            try {
                const result = await client.operate(documentId, operation);
                const selected = find(result.document, state.selected) === null ? null : state.selected;

                set({
                    document: result.document,
                    issues: result.issues,
                    refusals: [],
                    error: null,
                    selected,
                    ...after(result),
                });
                await refreshPreview();
            } catch (error) {
                if (error instanceof Refused) {
                    set({ refusals: error.refusals, error: null });
                } else {
                    set({ error: error.message });
                }
            } finally {
                set({ saving: false });
            }
        });

        return queue;
    }

    const moveTo = (id, position) =>
        position === null ? Promise.resolve() : edit({ operation: 'move', block: id, position });
    const selectFirst = (result) => (result.affected.length === 0 ? {} : { selected: result.affected[0] });

    return {
        state: () => state,

        /**
         * Hears every change of state.
         *
         * @returns {() => void} Stops hearing.
         */
        subscribe(listener) {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },

        async load() {
            set({ status: 'loading', error: null });

            try {
                const [read, catalogue, vocabulary] = await Promise.all([
                    client.document(documentId),
                    client.catalogue(),
                    client.vocabulary(),
                ]);
                const { output, css } = await client.render(documentId, 'admin');

                set({
                    status: 'ready',
                    document: read.document,
                    issues: read.issues,
                    catalogue,
                    vocabulary,
                    preview: { output, css },
                });
            } catch (error) {
                set({ status: 'failed', error: error.message });
            }
        },

        select(id) {
            set({ selected: id });
        },

        /**
         * Asks which block types may be placed at a position.
         */
        placeable: (position) => client.catalogue({ document: documentId, ...position }),

        insert: (type, position, settings = {}) => edit({ operation: 'insert', type, position, settings }, selectFirst),

        insertTemplate: (template, position) => edit({ operation: 'insertTemplate', template, position }, selectFirst),

        remove: (id) => edit({ operation: 'remove', block: id }),

        duplicate: (id) => edit({ operation: 'duplicate', block: id }, selectFirst),

        move: (id, position) => moveTo(id, position),

        moveBy: (id, step) => moveTo(id, movePosition(state.document, id, step)),

        indent: (id) => moveTo(id, indentPosition(state.document, id)),

        outdent: (id) => moveTo(id, outdentPosition(state.document, id)),

        updateSettings: (id, settings) => edit({ operation: 'updateSettings', block: id, settings }),

        updateExtension: (id, namespace, settings) =>
            edit({ operation: 'updateExtension', block: id, namespace, settings }),

        refreshPreview,
    };
}
