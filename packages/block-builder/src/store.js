import { ApiError, Refused } from './api.js';
import { templateName } from './templates.js';
import { toggleInSelection, withLock } from './selection.js';
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
        // Every block selected, in document order; it holds `selected` whenever that is set.
        selection: [],
        refusals: [],
        error: null,
        saving: false,
        canUndo: false,
        canRedo: false,
        preview: { output: '', css: '', styles: [] },
        // The template libraries and whether the person may save and remove templates; null until first asked.
        templates: null,
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
                const kept = state.selection.filter((id) => find(result.document, id) !== null);
                const selection = selected === null || kept.includes(selected) ? kept : [selected];

                remember(before);
                set({
                    document: result.document,
                    issues: result.issues,
                    refusals: [],
                    error: null,
                    selected,
                    selection,
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

    /**
     * Runs a change to the site rather than to the document, such as saving a template: it is not in the
     * document's history, and a failure is shown rather than thrown.
     *
     * @returns {Promise<boolean>} Whether it worked.
     */
    async function siteChange(run) {
        try {
            await run();

            return true;
        } catch (error) {
            set({ error: error.message });

            return false;
        }
    }

    /**
     * Changes a site style and puts what the server answers into the vocabulary, then draws the page again with it.
     *
     * Site styles are not part of the document, so the change is not in its history.
     *
     * @param {'tokens'|'breakpoints'} key The part of the vocabulary the server answered with.
     * @returns {Promise<?string>} Null when it worked; otherwise why not, safe to show.
     */
    async function styleChange(key, run) {
        try {
            const answer = await run();
            set({ vocabulary: { ...state.vocabulary, [key]: answer } });
            await refreshPreview();

            return null;
        } catch (error) {
            if (!(error instanceof ApiError) || (error.status !== 422 && error.status !== 403)) {
                set({ error: error.message });
            }

            return error.message;
        }
    }

    const loadTemplates = () => siteChange(async () => set({ templates: await client.templates() }));

    const moveTo = (id, position) =>
        position === null ? Promise.resolve() : edit({ operation: 'move', block: id, position });
    const selectFirst = (result) => (
        result.affected.length === 0 ? {} : { selected: result.affected[0], selection: [result.affected[0]] }
    );

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
            set({ selected: id, selection: id === null ? [] : [id] });
        },

        /**
         * Adds a block to the selection or takes it out, as a Shift- or Ctrl-click does.
         */
        toggle(id) {
            if (state.document === null) {
                return;
            }

            const selection = toggleInSelection(state.document, state.selection, id);
            const selected = selection.includes(id) ? id : (selection.at(-1) ?? null);

            set({ selection, selected });
        },

        /**
         * Locks or unlocks several blocks as one edit, so one undo puts every lock back as it was.
         *
         * @param {string[]} ids
         * @param {{move: boolean, remove: boolean}} lock
         */
        lockMany: (ids, lock) => edit(() => ({ operation: 'restore', blocks: withLock(state.document.blocks, ids, lock) })),

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

        /**
         * Removes several blocks as one edit, so one undo puts them all back.
         *
         * @param {string[]} ids
         */
        removeMany: (ids) => (ids.length === 1
            ? edit({ operation: 'remove', block: ids[0] })
            : edit({ operation: 'removeBlocks', ids })),

        /**
         * Adds copied blocks, which the server gives new ids, and selects the first.
         *
         * @param {Array<object>} blocks As the clipboard holds them.
         * @param {{parent: ?string, index: number}} position
         */
        paste: (blocks, position) => edit({ operation: 'insertBlocks', blocks, position }, selectFirst),

        duplicate: (id) => edit({ operation: 'duplicate', block: id }, selectFirst),

        /**
         * Turns a block into another type its own type declares; it stays the same block, so it stays selected.
         */
        transform: (id, type) => edit({ operation: 'transform', block: id, type }),

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
         * Binds a setting to a data source, so its value comes from the source when the page is drawn.
         *
         * @param {string} path The setting, such as `text`.
         * @param {{source: string, arguments: object, fallback?: *}} binding
         */
        setBinding: (id, path, binding) => edit({ operation: 'setBinding', block: id, path, binding }),

        clearBinding: (id, path) => edit({ operation: 'clearBinding', block: id, path }),

        /**
         * Takes the document back to how it was before the last edit.
         */
        undo: () => step(past, future),

        /**
         * Puts back the edit that was last undone.
         */
        redo: () => step(future, past),

        refreshPreview,

        loadTemplates,

        setToken: (name, value) => styleChange('tokens', () => client.setToken(name, value)),

        removeToken: (name) => styleChange('tokens', () => client.removeToken(name)),

        setBreakpoint: (name, minWidth) => styleChange('breakpoints', () => client.setBreakpoint(name, minWidth)),

        removeBreakpoint: (name) => styleChange('breakpoints', () => client.removeBreakpoint(name)),

        /**
         * Saves a block, with everything inside it, as a template under a name made from its title.
         *
         * @param {{block: string, title: string, description: string}} template
         * @returns {Promise<boolean>} Whether it was saved.
         */
        saveTemplate: ({ block, title, description }) => siteChange(async () => {
            await client.saveTemplate({ document: documentId, block, name: templateName(title), title, description });
            set({ templates: await client.templates() });
        }),

        removeTemplate: (name) => siteChange(async () => {
            await client.removeTemplate(name);
            set({ templates: await client.templates() });
        }),
    };
}
