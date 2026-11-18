import { Refused } from './api.js';
import { find, indentPosition, movePosition, outdentPosition, positionOf } from './tree.js';

/**
 * Builds the state of one builder session: a document being edited through the API.
 *
 * It holds no rules of its own. Every edit is sent to the server, which answers with the new document or the
 * reasons it refused; the store shows whichever came back. Edits are sent one at a time, in the order they were
 * made, so the document on screen is always the one the server last answered with.
 *
 * Undo and redo work on whole states: the store keeps the blocks the server gave it before each edit and asks
 * the server to restore them, which checks them like any other edit. It keeps no inverse of each operation.
 *
 * @param {{client: object, documentId: string, historyLimit?: number}} options `historyLimit` is how many steps
 *   can be undone.
 */
export function createBuilder({ client, documentId, historyLimit = 50 }) {
    let state = {
        status: 'idle',
        document: null,
        issues: [],
        catalogue: [],
        packages: [],
        vocabulary: null,
        selected: null,
        refusals: [],
        error: null,
        saving: false,
        canUndo: false,
        canRedo: false,
        preview: { output: '', css: '', styles: [] },
    };
    const listeners = new Set();
    let queue = Promise.resolve();

    // Earlier and later states of the document's blocks, nearest last. Kept out of the state: they are large, and
    // an interface only needs to know whether there is anything to go back or forward to.
    const past = [];
    const future = [];

    function set(changes) {
        state = { ...state, ...changes };
        listeners.forEach((listener) => listener(state));
    }

    async function refreshPreview() {
        try {
            const { output, css, styles = [] } = await client.render(documentId, 'admin');
            set({ preview: { output, css, styles } });
        } catch (error) {
            set({ error: error.message });
        }
    }

    /**
     * Sends one operation after any already under way, and shows the answer.
     *
     * @param {object|(() => ?object)} operation The operation as the API takes it, or a function that decides it
     *   when its turn comes; returning null sends nothing.
     * @param {(result: object) => object} [after] Extra state to set once the edit is accepted.
     * @param {(before: Array<object>) => void} [remember] What to do with the blocks as they were before, once
     *   the edit is accepted. An ordinary edit makes them the next thing to undo to.
     */
    function edit(operation, after = () => ({}), remember = record) {
        queue = queue.then(async () => {
            const wanted = typeof operation === 'function' ? operation() : operation;

            if (wanted === null) {
                return;
            }

            set({ saving: true });

            try {
                const before = state.document.blocks;
                const result = await client.operate(documentId, wanted);
                const selected = find(result.document, state.selected) === null ? null : state.selected;

                remember(before);
                set({
                    document: result.document,
                    issues: result.issues,
                    refusals: [],
                    error: null,
                    selected,
                    canUndo: past.length > 0,
                    canRedo: future.length > 0,
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

    function record(before) {
        past.push(before);
        future.length = 0;

        if (past.length > historyLimit) {
            past.shift();
        }
    }

    /**
     * Restores the nearest state from one pile and, once the server accepts it, moves the current one to the other.
     */
    function step(from, to) {
        return edit(
            () => (from.length === 0 ? null : { operation: 'restore', blocks: from.at(-1) }),
            () => ({}),
            (before) => {
                from.pop();
                to.push(before);
            },
        );
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
                const { output, css, styles = [] } = await client.render(documentId, 'admin');

                set({
                    status: 'ready',
                    document: read.document,
                    issues: read.issues,
                    catalogue: catalogue.blocks,
                    packages: catalogue.packages,
                    vocabulary,
                    preview: { output, css, styles },
                });
            } catch (error) {
                set({ status: 'failed', error: error.message });
            }
        },

        select(id) {
            set({ selected: id });
        },

        /**
         * Puts away what the server last refused, once the person has read it.
         */
        dismiss() {
            if (state.refusals.length > 0 || state.error !== null) {
                set({ refusals: [], error: null });
            }
        },

        /**
         * Asks which block types may be placed at a position.
         */
        placeable: async (position) => (await client.catalogue({ document: documentId, ...position })).blocks,

        insert: (type, position, settings = {}) => edit({ operation: 'insert', type, position, settings }, selectFirst),

        insertTemplate: (template, position) => edit({ operation: 'insertTemplate', template, position }, selectFirst),

        /**
         * Adds a block of a type directly after another, and selects it: what Enter does while typing.
         */
        insertAfter(id, type) {
            const here = positionOf(state.document, id);

            if (here === null) {
                return Promise.resolve();
            }

            const position = { parent: here.parent, index: here.index + 1 };

            return edit({ operation: 'insert', type, position, settings: {} }, selectFirst);
        },

        remove: (id) => edit({ operation: 'remove', block: id }),

        duplicate: (id) => edit({ operation: 'duplicate', block: id }, selectFirst),

        move: (id, position) => moveTo(id, position),

        moveBy: (id, step) => moveTo(id, movePosition(state.document, id, step)),

        indent: (id) => moveTo(id, indentPosition(state.document, id)),

        outdent: (id) => moveTo(id, outdentPosition(state.document, id)),

        updateSettings: (id, settings) => edit({ operation: 'updateSettings', block: id, settings }),

        /**
         * Changes a block's settings from what they are when the change's turn comes, so a change sent while an
         * earlier one is still under way builds on it instead of putting back what it replaced.
         *
         * @param {string} id
         * @param {(settings: object) => object} change
         */
        changeSettings: (id, change) => edit(() => {
            const block = find(state.document, id);

            return block === null ? null : { operation: 'updateSettings', block: id, settings: change(block.settings ?? {}) };
        }),

        updateExtension: (id, namespace, settings) =>
            edit({ operation: 'updateExtension', block: id, namespace, settings }),

        /**
         * Takes the document back to how it was before the last edit.
         */
        undo: () => step(past, future),

        /**
         * Puts back the edit that was last undone.
         */
        redo: () => step(future, past),

        refreshPreview,
    };
}
