import { createClient } from './api.js';
import { FieldError, fields, parseField, withValue } from './forms.js';
import { createBuilder } from './store.js';
import { find, positionOf, walk } from './tree.js';

const STYLE = `
block-builder { display: grid; grid-template: auto 1fr / 17rem 1fr 20rem; height: 100%; min-height: 32rem;
    font: 14px/1.4 system-ui, sans-serif; color: #1c1e21; background: #f4f5f7; }
block-builder * { box-sizing: border-box; }
block-builder .bb-notice { grid-row: 1; grid-column: 1 / -1; margin: 0; padding: .5rem .9rem; background: #fdecea;
    color: #8a1c13; border-bottom: 1px solid #f5c2bc; }
block-builder .bb-notice ul { margin: 0; padding-left: 1.1rem; }
block-builder .bb-pane { grid-row: 2; overflow: auto; padding: .75rem; background: #fff; }
block-builder .bb-inspector .bb-notice { margin: .5rem 0; border: 1px solid #f5c2bc; border-radius: 4px; }
block-builder .bb-structure { border-right: 1px solid #dfe1e6; }
block-builder .bb-inspector { border-left: 1px solid #dfe1e6; }
block-builder .bb-preview { padding: 0; background: #f4f5f7; }
block-builder .bb-preview iframe { width: 100%; height: 100%; border: 0; background: #fff; }
block-builder h2 { margin: 0 0 .5rem; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase;
    color: #5e6c84; }
block-builder h3 { margin: 1rem 0 .4rem; font-size: .85rem; }
block-builder .bb-row { display: flex; align-items: center; gap: .35rem; width: 100%; padding: .3rem .4rem;
    border: 1px solid transparent; border-radius: 4px; background: none; font: inherit; text-align: left;
    cursor: pointer; }
block-builder .bb-row:hover { background: #f4f5f7; }
block-builder .bb-row[aria-current="true"] { background: #e6efff; border-color: #4c7dff; }
block-builder .bb-row > span { flex: none; }
block-builder .bb-row small { flex: 1; min-width: 0; overflow: hidden; color: #5e6c84; text-overflow: ellipsis;
    white-space: nowrap; }
block-builder .bb-badge { margin-left: auto; padding: 0 .4rem; border-radius: 999px; background: #c9372c; color: #fff;
    font-size: .7rem; }
block-builder .bb-tools { display: flex; flex-wrap: wrap; gap: .3rem; margin: .6rem 0; }
block-builder button.bb-tool, block-builder .bb-add button { padding: .3rem .55rem; border: 1px solid #c1c7d0;
    border-radius: 4px; background: #fff; font: inherit; cursor: pointer; }
block-builder button.bb-tool:hover, block-builder .bb-add button:hover { background: #f4f5f7; }
block-builder button:disabled { opacity: .45; cursor: default; }
block-builder button.bb-danger { color: #a3271e; border-color: #e6aaa4; }
block-builder .bb-add { display: grid; gap: .3rem; margin-top: .4rem; }
block-builder .bb-add button { text-align: left; }
block-builder label.bb-field { display: block; margin: .55rem 0; }
block-builder label.bb-field > span { display: block; margin-bottom: .2rem; font-weight: 600; }
block-builder label.bb-field em { display: block; color: #5e6c84; font-style: normal; font-size: .8rem; }
block-builder label.bb-field .bb-error { color: #a3271e; }
block-builder input[type="text"], block-builder input[type="number"], block-builder select, block-builder textarea {
    width: 100%; padding: .35rem .45rem; border: 1px solid #c1c7d0; border-radius: 4px; font: inherit; }
block-builder textarea { min-height: 3.2rem; font-family: ui-monospace, monospace; font-size: .8rem; }
block-builder .bb-empty { color: #5e6c84; }
`;

/**
 * Creates an element with text and attributes, never markup, so nothing from a document is run as HTML.
 */
function el(tag, { text, ...attributes } = {}, ...children) {
    const node = document.createElement(tag);

    if (text !== undefined) {
        node.textContent = text;
    }

    for (const [name, value] of Object.entries(attributes)) {
        if (name.startsWith('on')) {
            node.addEventListener(name.slice(2), value);
        } else if (value === true) {
            node.setAttribute(name, '');
        } else if (value !== false && value !== null && value !== undefined) {
            node.setAttribute(name, value);
        }
    }

    node.append(...children.filter((child) => child !== null));

    return node;
}

/**
 * A reference builder: the structure of a document, an inspector for the selected block, and a live preview.
 *
 * It is one way to build on the builder API, not the only one. It has no knowledge of any block type: what can
 * be added comes from the catalogue, the settings form from each type's schema, and what is valid from the
 * server, which refuses anything else and says why.
 *
 * Attributes: `api`, the address the API is mounted at, and `document`, the id of the document to edit.
 */
export class BlockBuilderElement extends HTMLElement {
    connectedCallback() {
        if (document.getElementById('block-builder-style') === null) {
            document.head.append(el('style', { id: 'block-builder-style', text: STYLE }));
        }

        this.adding = null;
        this.fieldErrors = {};
        this.builder = createBuilder({
            client: this.client ?? createClient({ baseUrl: this.getAttribute('api') ?? '/api' }),
            documentId: this.getAttribute('document') ?? '',
        });
        this.stop = this.builder.subscribe(() => this.render());
        this.builder.load();
    }

    disconnectedCallback() {
        this.stop?.();
    }

    render() {
        const state = this.builder.state();

        // The preview is kept between renders so the page does not flash on every keystroke elsewhere.
        const preview = this.querySelector('.bb-preview') ?? el('section', { class: 'bb-pane bb-preview' });
        this.updatePreview(preview, state.preview);

        this.replaceChildren(
            this.notice(state),
            el('section', { class: 'bb-pane bb-structure' }, ...this.structure(state)),
            preview,
            el('section', { class: 'bb-pane bb-inspector' }, ...this.inspector(state)),
        );
    }

    notice(state) {
        if (state.status === 'failed' || state.error !== null) {
            return el('p', { class: 'bb-notice', role: 'alert', text: state.error });
        }

        if (state.refusals.length === 0) {
            return el('span', { hidden: true });
        }

        return el(
            'div',
            { class: 'bb-notice', role: 'alert' },
            el('ul', {}, ...state.refusals.map((refusal) => el('li', { text: refusal.message }))),
        );
    }

    structure(state) {
        if (state.document === null) {
            return [el('p', { class: 'bb-empty', text: state.status === 'failed' ? '' : 'Loading…' })];
        }

        const titles = Object.fromEntries(state.catalogue.map((entry) => [entry.name, entry.title]));
        const counts = {};
        state.issues.forEach((issue) => {
            counts[issue.block] = (counts[issue.block] ?? 0) + 1;
        });
        const rows = walk(state.document).map(({ block, depth }) =>
            el(
                'button',
                {
                    class: 'bb-row',
                    type: 'button',
                    style: `padding-left: ${0.4 + depth * 1.1}rem`,
                    'aria-current': state.selected === block.id ? 'true' : 'false',
                    onclick: () => this.select(block.id),
                },
                el('span', { text: titles[block.type] ?? block.type }),
                el('small', { text: this.hint(block) }),
                counts[block.id] ? el('span', { class: 'bb-badge', text: String(counts[block.id]) }) : null,
            ),
        );
        const selected = state.selected === null ? null : find(state.document, state.selected);
        const here = selected === null ? null : positionOf(state.document, selected.id);
        const tool = (text, run, extra = {}) =>
            el('button', {
                class: 'bb-tool',
                type: 'button',
                text,
                disabled: selected === null,
                onclick: run,
                ...extra,
            });

        return [
            el('h2', { text: 'Structure' }),
            ...(rows.length === 0 ? [el('p', { class: 'bb-empty', text: 'This document has no blocks yet.' })] : rows),
            el(
                'div',
                { class: 'bb-tools' },
                tool('Up', () => this.builder.moveBy(selected.id, -1)),
                tool('Down', () => this.builder.moveBy(selected.id, 1)),
                tool('Indent', () => this.builder.indent(selected.id)),
                tool('Outdent', () => this.builder.outdent(selected.id)),
                tool('Duplicate', () => this.builder.duplicate(selected.id)),
                tool('Remove', () => this.builder.remove(selected.id), { class: 'bb-tool bb-danger' }),
            ),
            el('h2', { text: 'Add a block' }),
            el(
                'div',
                { class: 'bb-tools' },
                selected === null
                    ? null
                    : el('button', {
                          class: 'bb-tool',
                          type: 'button',
                          text: 'Inside',
                          onclick: () => this.offer({ parent: selected.id, index: (selected.children ?? []).length }),
                      }),
                selected === null
                    ? null
                    : el('button', {
                          class: 'bb-tool',
                          type: 'button',
                          text: 'After',
                          onclick: () => this.offer({ parent: here.parent, index: here.index + 1 }),
                      }),
                el('button', {
                    class: 'bb-tool',
                    type: 'button',
                    text: 'At the end',
                    onclick: () => this.offer({ parent: null, index: state.document.blocks.length }),
                }),
            ),
            this.offered(),
        ];
    }

    /**
     * Picks a few words to tell blocks of one type apart in the structure: their text, title or label if they
     * have one, otherwise their first piece of text.
     */
    hint(block) {
        const settings = block.settings ?? {};
        const text = [settings.text, settings.title, settings.label, ...Object.values(settings)]
            .find((value) => typeof value === 'string' && value.trim() !== '');

        return text === undefined ? '' : text.slice(0, 40);
    }

    /**
     * Shows the block types the server allows at the position being added to.
     */
    offered() {
        if (this.adding === null) {
            return null;
        }

        if (this.adding.entries.length === 0) {
            return el('p', { class: 'bb-empty', text: 'Nothing can be added there.' });
        }

        return el(
            'div',
            { class: 'bb-add' },
            ...this.adding.entries.map((entry) =>
                el('button', {
                    type: 'button',
                    text: entry.title,
                    title: entry.description,
                    onclick: () => {
                        const { position } = this.adding;
                        this.adding = null;
                        this.builder.insert(entry.name, position);
                    },
                }),
            ),
        );
    }

    async offer(position) {
        try {
            this.adding = { position, entries: await this.builder.placeable(position) };
        } catch {
            this.adding = { position, entries: [] };
        }

        this.render();
    }

    select(id) {
        this.adding = null;
        this.fieldErrors = {};
        this.builder.select(id);
    }

    inspector(state) {
        const block = state.selected === null || state.document === null ? null : find(state.document, state.selected);

        if (block === null) {
            return [
                el('h2', { text: 'Settings' }),
                el('p', { class: 'bb-empty', text: 'Select a block to change it.' }),
            ];
        }

        const entry = state.catalogue.find((candidate) => candidate.name === block.type);
        const issues = state.issues.filter((issue) => issue.block === block.id);
        const decorators = (state.vocabulary?.decorators ?? []).filter(
            (decorator) => decorator.appliesTo === null || decorator.appliesTo.includes(block.type),
        );

        return [
            el('h2', { text: entry?.title ?? block.type }),
            entry?.description ? el('p', { class: 'bb-empty', text: entry.description }) : null,
            issues.length === 0
                ? null
                : el(
                      'div',
                      { class: 'bb-notice', role: 'status' },
                      el('ul', {}, ...issues.map((issue) => el('li', { text: issue.message }))),
                  ),
            ...this.form(fields(entry?.settings, block.settings), 'settings', (name, value) =>
                this.builder.updateSettings(block.id, withValue(block.settings, name, value)),
            ),
            ...decorators.flatMap((decorator) => [
                el('h3', { text: decorator.namespace }),
                ...this.form(
                    fields(decorator.settings, block.extensions?.[decorator.namespace]),
                    decorator.namespace,
                    (name, value) =>
                        this.builder.updateExtension(
                            block.id,
                            decorator.namespace,
                            withValue(block.extensions?.[decorator.namespace] ?? {}, name, value),
                        ),
                ),
            ]),
        ].filter((node) => node !== null);
    }

    /**
     * Draws one group of fields; a change is sent when the person leaves the field.
     */
    form(list, group, save) {
        if (list.length === 0) {
            return [el('p', { class: 'bb-empty', text: 'Nothing to set here.' })];
        }

        return list.map((field) => {
            const key = `${group}:${field.name}`;
            const commit = (raw) => {
                try {
                    const value = parseField(field, raw);
                    delete this.fieldErrors[key];
                    save(field.name, value);
                } catch (error) {
                    if (!(error instanceof FieldError)) {
                        throw error;
                    }

                    this.fieldErrors[key] = error.message;
                    this.render();
                }
            };

            return el(
                'label',
                { class: 'bb-field' },
                el('span', { text: field.private ? `${field.title} (private)` : field.title }),
                this.control(field, commit),
                field.description ? el('em', { text: field.description }) : null,
                this.fieldErrors[key] ? el('em', { class: 'bb-error', text: this.fieldErrors[key] }) : null,
            );
        });
    }

    control(field, commit) {
        if (field.control === 'checkbox') {
            return el('input', {
                type: 'checkbox',
                checked: field.value === true,
                onchange: (event) => commit(event.target.checked),
            });
        }

        if (field.control === 'select') {
            const select = el(
                'select',
                { onchange: (event) => commit(field.options[Number(event.target.value)]) },
                ...field.options.map((option, index) =>
                    el('option', { value: String(index), text: String(option), selected: option === field.value }),
                ),
            );

            return select;
        }

        if (field.control === 'json') {
            const shown = field.value === undefined || field.isDefault
                ? ''
                : typeof field.value === 'string'
                  ? field.value
                  : JSON.stringify(field.value);

            return el('textarea', { text: shown, onchange: (event) => commit(event.target.value) });
        }

        return el('input', {
            type: field.control === 'number' ? 'number' : 'text',
            value: field.value === undefined || field.value === null ? '' : String(field.value),
            onchange: (event) => commit(event.target.value),
        });
    }

    /**
     * Shows the page in a frame that can run no script, whatever the document contains.
     */
    updatePreview(pane, preview) {
        const page = `<!doctype html><meta charset="utf-8"><style>body{margin:1rem;font:16px/1.5 system-ui,sans-serif}`
            + `.block-placeholder{min-height:2rem;border:1px dashed #c9372c;background:#fdecea}</style>`
            + `<style>${preview.css.replaceAll('</', '<\\/')}</style>${preview.output}`;
        let frame = pane.querySelector('iframe');

        if (frame === null) {
            frame = el('iframe', { sandbox: '', title: 'Preview' });
            pane.append(frame);
        }

        if (frame.srcdoc !== page) {
            frame.srcdoc = page;
        }
    }
}

/**
 * Registers the element as `<block-builder>`, once.
 */
export function defineBlockBuilder(name = 'block-builder') {
    if (customElements.get(name) === undefined) {
        customElements.define(name, BlockBuilderElement);
    }
}
