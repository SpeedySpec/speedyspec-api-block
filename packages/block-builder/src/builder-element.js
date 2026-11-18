import { createClient } from './api.js';
import {
    FieldError, fields, parseField, tableShape, withColumn, withoutColumn, withoutRow, withRow, withTyped, withValue,
} from './forms.js';
import { createBuilder } from './store.js';
import { accepts, addedMedia, mediaQuery, movedMedia, withoutMediaAt } from './media.js';
import { STYLE } from './styles.js';
import { find, positionOf, walk } from './tree.js';
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
} from './view.js';
import { dropPosition, dropZone } from './drag.js';
import { keyAction, removableWhenEmpty, safeLink, settingSchema, startsNewBlock, tidyTyped } from './inline.js';

const STYLE_NS = 'core/style';
const RULES_NS = 'core/visibility';
const NAMED = 'data-block-id';

// Drawn with strokes on a 16-unit grid; the markup is fixed text written here, never anything from a document.
const ICONS = {
    add: '<path d="M8 3v10M3 8h10"/>',
    outline: '<path d="M3 4h10M6 8h7M6 12h7"/>',
    up: '<path d="M8 13V3M4 7l4-4 4 4"/>',
    down: '<path d="M8 3v10M4 9l4 4 4-4"/>',
    parent: '<path d="M12 13V8a3 3 0 0 0-3-3H4M7 2 4 5l3 3"/>',
    copy: '<rect x="5.5" y="5.5" width="8" height="8" rx="1.5"/><path d="M3 10.5V4a1.5 1.5 0 0 1 1.5-1.5H10"/>',
    remove: '<path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5"/>',
    undo: '<path d="M6 3 3 6l3 3M3 6h6.5a3.5 3.5 0 0 1 0 7H6"/>',
    redo: '<path d="M10 3l3 3-3 3M13 6H6.5a3.5 3.5 0 0 0 0 7H10"/>',
};

/**
 * Creates an element from text and attributes, never markup, so nothing in a document is run as HTML.
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

    node.append(...children.flat(Infinity).filter((child) => child !== null && child !== undefined));

    return node;
}

function icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 16 16');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = ICONS[name];

    return svg;
}

const words = (name) => name.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/^./, (letter) => letter.toUpperCase());

/**
 * A reference builder, laid out the way block editors people already know are: the page in the middle, edited by
 * clicking what you see; blocks added from a searchable panel; settings in tabs on the right.
 *
 * It knows no block type. What can be added comes from the catalogue, the settings from each type's schema, and
 * what is valid from the server, which refuses anything else and says why.
 *
 * Attributes: `api`, the address the API is mounted at; `document`, the id of the document to edit; and `headers`,
 * optionally, a JSON object of headers to send with every request.
 */
export class BlockBuilderElement extends HTMLElement {
    connectedCallback() {
        if (document.getElementById('block-builder-style') === null) {
            document.head.append(el('style', { id: 'block-builder-style', text: STYLE }));
        }

        this.ui = {
            drawer: 'outline',
            query: '',
            target: 0,
            allowed: null,
            allowedFor: '',
            device: 'full',
            tab: 'block',
            width: '',
            errors: {},
            rules: null,
            // The media picker, while one is open: which setting it fills, and the page of the library it shows.
            picker: null,
        };
        // Media items already looked up, by id, so a chosen file shows its picture and name; null while loading.
        this.media = new Map();
        this.typing = null;
        this.keepTyping = false;
        this.focusAfter = null;
        this.api = this.client ?? createClient({ baseUrl: this.getAttribute('api') ?? '/api', headers: this.headers() });
        this.builder = createBuilder({
            client: this.api,
            documentId: this.getAttribute('document') ?? '',
        });

        this.frame = el('iframe', { sandbox: 'allow-same-origin', title: 'The page being edited' });
        this.frame.addEventListener('load', () => this.wireCanvas());
        this.bar = el('div', { class: 'bb-bar', hidden: true });
        this.format = this.formatBar();
        this.page = el('div', { class: 'bb-page' }, this.frame, this.bar, this.format);
        this.toast = el('div', { class: 'bb-toast', role: 'alert', hidden: true });
        this.blank = el('div', {
            class: 'bb-blank',
            hidden: true,
            ondragover: (event) => this.dragOver(event, null, null, false),
            ondrop: (event) => this.dropHere(event),
        });
        this.drag = null;
        this.drop = null;
        this.regions = {
            top: el('header', { class: 'bb-top' }),
            drawer: el('aside', { class: 'bb-drawer' }),
            stage: el('main', { class: 'bb-stage' }, this.page, this.blank, this.toast),
            side: el('aside', { class: 'bb-side' }),
            trail: el('nav', { class: 'bb-trail', 'aria-label': 'Where the selected block sits' }),
        };
        this.replaceChildren(...Object.values(this.regions));

        this.stop = this.builder.subscribe(() => this.render());
        this.resize = () => this.placeBar();
        window.addEventListener('resize', this.resize);
        this.keys = (event) => this.shortcut(event);
        this.addEventListener('keydown', this.keys);
        this.builder.load();
    }

    /**
     * Reads the headers the host asked to send with every request, such as a request-forgery token.
     */
    headers() {
        try {
            const headers = JSON.parse(this.getAttribute('headers') ?? '{}');

            return headers !== null && typeof headers === 'object' && !Array.isArray(headers) ? headers : {};
        } catch {
            return {};
        }
    }

        disconnectedCallback() {
        this.stop?.();
        window.removeEventListener('resize', this.resize);
    }

    /**
     * Redraws the panels around the page. The page's frame is kept, and so are focus and scroll positions, so
     * changing one setting does not throw the person out of the field they were in.
     */
    render() {
        const state = this.builder.state();
        const active = this.contains(document.activeElement) ? document.activeElement : null;
        const focused = active?.dataset.key;
        // Where the cursor was in a text field, so typing carries on where it left off.
        const caret = typeof active?.selectionStart === 'number' ? [active.selectionStart, active.selectionEnd] : null;
        const scroll = [...this.querySelectorAll('.bb-drawer, .bb-side-body')].map((node) => [node.className, node.scrollTop]);

        this.regions.top.replaceChildren(...this.top(state));
        this.regions.drawer.hidden = this.ui.drawer === null || state.document === null;
        this.regions.drawer.replaceChildren(...(this.ui.drawer === 'add' ? this.adder(state) : this.outline(state)));
        this.regions.side.replaceChildren(...this.inspector(state));
        this.regions.trail.replaceChildren(...this.trail(state));
        this.canvas(state);
        this.notices(state);

        scroll.forEach(([name, top]) => {
            const node = this.querySelector(`.${name.split(' ').join('.')}`);

            if (node !== null) {
                node.scrollTop = top;
            }
        });

        if (focused !== undefined) {
            const field = this.querySelector(`[data-key="${CSS.escape(focused)}"]`);
            field?.focus();

            if (caret !== null && typeof field?.setSelectionRange === 'function') {
                field.setSelectionRange(...caret);
            }
        }
    }

    // ---- Top bar -------------------------------------------------------------------------------------------

    top(state) {
        const toggle = (name, label, glyph) =>
            el(
                'button',
                {
                    class: 'bb-topbtn',
                    type: 'button',
                    'aria-pressed': String(this.ui.drawer === name),
                    onclick: () => this.openDrawer(this.ui.drawer === name ? null : name),
                },
                icon(glyph),
                el('span', { text: label }),
            );
        const history = (label, glyph, possible, run) =>
            el(
                'button',
                {
                    class: 'bb-topbtn bb-topicon',
                    type: 'button',
                    title: label,
                    'aria-label': label,
                    disabled: !possible,
                    onclick: run,
                },
                icon(glyph),
            );
        const sizes = devices(state.vocabulary).map((device) =>
            el('button', {
                type: 'button',
                text: device.label,
                'aria-pressed': String(this.ui.device === device.name),
                onclick: () => {
                    this.ui.device = device.name;
                    this.render();
                },
            }),
        );
        const problems = state.issues.length;
        const status = state.status === 'failed'
            ? ['bad', state.error ?? 'The page could not be opened.']
            : state.error !== null
              ? ['bad', state.error]
              : state.status !== 'ready'
                ? ['', 'Opening…']
                : state.saving
                  ? ['', 'Saving…']
                  : ['', 'All changes saved'];

        return [
            el(
                'div',
                { class: 'bb-top-left' },
                el('span', { class: 'bb-name', text: state.document?.id ?? this.getAttribute('document') ?? '' }),
                toggle('add', 'Add block', 'add'),
                toggle('outline', 'Outline', 'outline'),
                history('Undo', 'undo', state.canUndo, () => this.builder.undo()),
                history('Redo', 'redo', state.canRedo, () => this.builder.redo()),
            ),
            el('div', { class: 'bb-seg', role: 'group', 'aria-label': 'Preview width' }, sizes),
            el(
                'div',
                { class: 'bb-top-right' },
                problems === 0
                    ? null
                    : el('button', {
                          class: 'bb-problems',
                          type: 'button',
                          text: problems === 1 ? '1 problem' : `${problems} problems`,
                          onclick: () => {
                              this.openDrawer('outline');
                              this.select(state.issues[0].block);
                          },
                      }),
                el('span', { class: 'bb-status', 'data-kind': status[0], role: 'status', text: status[1] }),
            ),
        ];
    }

    openDrawer(name) {
        this.ui.drawer = name;
        this.ui.target = 0;
        this.render();
    }

    // ---- Drawer: add a block -------------------------------------------------------------------------------

    adder(state) {
        const targets = insertTargets(state.document, state.selected, state.catalogue);
        const target = targets[Math.min(this.ui.target, targets.length - 1)];
        this.askAllowed(target.position);
        const allowed = this.ui.allowed;
        const sections = groupCatalogue(state.catalogue, state.packages, this.ui.query);

        return [
            el(
                'div',
                { class: 'bb-drawer-head' },
                el('h2', { text: 'Add a block' }),
                targets.length === 1
                    ? el('p', { class: 'bb-where-only', text: target.label })
                    : el(
                          'div',
                          { class: 'bb-where', role: 'group', 'aria-label': 'Where to add it' },
                          targets.map((option, index) =>
                              el('button', {
                                  type: 'button',
                                  text: option.label,
                                  'aria-pressed': String(option === target),
                                  onclick: () => {
                                      this.ui.target = index;
                                      this.render();
                                  },
                              }),
                          ),
                      ),
                el('input', {
                    class: 'bb-input',
                    type: 'search',
                    placeholder: 'Search blocks',
                    'aria-label': 'Search blocks',
                    'data-key': 'search',
                    value: this.ui.query,
                    oninput: (event) => {
                        this.ui.query = event.target.value;
                        this.render();
                    },
                }),
            ),
            el(
                'div',
                { class: 'bb-drawer-body' },
                sections.length === 0 ? el('p', { class: 'bb-none', text: 'No block matches that search.' }) : null,
                sections.map((section) =>
                    el(
                        'section',
                        { class: 'bb-section', 'aria-label': section.title },
                        el('h3', { class: 'bb-section-title', text: section.title }),
                        section.description ? el('p', { class: 'bb-section-text', text: section.description }) : null,
                        section.groups.flatMap((group) => [
                    group.category === '' ? null : el('h4', { text: group.category }),
                    el(
                        'div',
                        { class: 'bb-tiles' },
                        group.entries.map((entry) => {
                            const fits = allowed === null || allowed.has(entry.name);

                            return el(
                                'button',
                                {
                                    class: 'bb-tile',
                                    draggable: fits ? 'true' : null,
                                    ondragstart: (event) => this.startDrag(event, { type: entry.name }),
                                    ondragend: () => this.endDrag(),
                                    type: 'button',
                                    disabled: !fits,
                                    title: fits ? entry.description : `${entry.title} cannot go there.`,
                                    onclick: () => this.builder.insert(entry.name, target.position),
                                },
                                el('strong', { text: entry.title }),
                                el('span', { text: entry.description }),
                            );
                        }),
                    ),
                        ]),
                    ),
                ),
            ),
        ];
    }

    /**
     * Asks the server which blocks may go at a place, once per place and per version of the document.
     */
    askAllowed(position) {
        const state = this.builder.state();
        const key = JSON.stringify([position, walk(state.document).map((entry) => entry.block.id)]);

        if (this.ui.allowedFor === key) {
            return;
        }

        this.ui.allowedFor = key;
        this.ui.allowed = null;
        this.builder
            .placeable(position)
            .then((entries) => new Set(entries.map((entry) => entry.name)))
            .catch(() => new Set())
            .then((names) => {
                if (this.ui.allowedFor === key) {
                    this.ui.allowed = names;
                    this.render();
                }
            });
    }

    // ---- Drawer: outline -----------------------------------------------------------------------------------

    outline(state) {
        if (state.document === null) {
            return [];
        }

        const titles = this.titles(state);
        const troubled = new Set(state.issues.map((issue) => issue.block));
        const rows = walk(state.document).map(({ block, depth }) =>
            el(
                'button',
                {
                    class: 'bb-row',
                    draggable: 'true',
                    ondragstart: (event) => this.startDrag(event, { id: block.id }),
                    ondragend: () => this.endDrag(),
                    ondragover: (event) => this.dragOver(event, event.currentTarget, block.id, false),
                    ondrop: (event) => this.dropHere(event),
                    type: 'button',
                    style: `padding-left: ${0.4 + depth * 1.1}rem`,
                    'aria-current': String(state.selected === block.id),
                    onclick: () => this.select(block.id, true),
                },
                el('b', { text: titles[block.type] ?? block.type }),
                el('small', { text: this.hint(block) }),
                troubled.has(block.id) ? el('span', { class: 'bb-dot', title: 'This block has a problem' }) : null,
            ),
        );

        return [
            el('div', { class: 'bb-drawer-head' }, el('h2', { text: 'Outline' })),
            el(
                'div',
                { class: 'bb-drawer-body' },
                rows.length === 0 ? el('p', { class: 'bb-none', text: 'Nothing on this page yet.' }) : rows,
            ),
        ];
    }

    titles(state) {
        return Object.fromEntries(state.catalogue.map((entry) => [entry.name, entry.title]));
    }

    /**
     * Picks a few words to tell blocks of one type apart: their text, title or label, else their first text.
     */
    hint(block) {
        const settings = block.settings ?? {};
        const text = [settings.text, settings.title, settings.label, ...Object.values(settings)]
            .find((value) => typeof value === 'string' && value.trim() !== '');

        return text === undefined ? '' : text.slice(0, 48);
    }

    // ---- Canvas --------------------------------------------------------------------------------------------

    canvas(state) {
        const device = devices(state.vocabulary).find((entry) => entry.name === this.ui.device);
        this.page.style.width = device?.width ? `${device.width}px` : '';
        this.page.style.maxWidth = device?.width ? 'none' : '';

        const empty = state.document !== null && state.document.blocks.length === 0;
        this.page.hidden = state.document === null || empty;
        this.blank.hidden = !empty;

        if (empty) {
            this.blank.replaceChildren(
                el('p', { text: 'This page is empty.' }),
                el('button', {
                    class: 'bb-primary',
                    type: 'button',
                    text: 'Add the first block',
                    onclick: () => this.openDrawer('add'),
                }),
            );
        }

        const source = canvasDocument(state.preview);

        // Redrawing under the cursor would throw away what is being typed; the page is redrawn once typing stops.
        if (this.typing !== null) {
            return;
        }

        if (this.frame.srcdoc !== source) {
            this.frame.srcdoc = source;
        } else {
            this.markPicked();
        }
    }

    /**
     * Makes the freshly drawn page respond to the pointer. The frame may run no script of its own, so the page's
     * content can do nothing; the builder listens from outside.
     */
    wireCanvas() {
        const page = this.frame.contentDocument;

        if (page === null) {
            return;
        }

        const named = (event) => event.target.closest?.(`[${NAMED}]`) ?? null;

        page.addEventListener('click', (event) => {
            const editable = event.target.closest?.('[data-block-setting]') ?? null;

            // A click inside the element being typed into moves the cursor, as it would anywhere else.
            if (this.typing !== null && editable === this.typing.node) {
                return;
            }

            event.preventDefault();
            const block = named(event);

            if (block !== null) {
                this.select(block.getAttribute(NAMED));
            }

            if (editable !== null && block !== null) {
                this.startTyping(editable, block.getAttribute(NAMED));
            }
        });
        page.addEventListener('keydown', (event) => this.typingKey(event));
        page.addEventListener('selectionchange', () => this.placeFormatBar());
        page.addEventListener('focusout', (event) => {
            if (this.typing !== null && event.target === this.typing.node && !this.keepTyping) {
                this.stopTyping(true);
            }
        });
        page.addEventListener('mouseover', (event) => {
            page.querySelectorAll('[data-bb-over]').forEach((node) => node.removeAttribute('data-bb-over'));
            named(event)?.setAttribute('data-bb-over', '');
        });
        // Blocks are dragged on the page itself. Whether "before" and "after" run across or down depends on how the
        // block's parent lays its children out, so a column is dropped beside its neighbour, not above it.
        page.querySelectorAll(`[${NAMED}]`).forEach((node) => node.setAttribute('draggable', 'true'));
        page.addEventListener('dragstart', (event) => {
            const block = named(event);

            if (block !== null) {
                this.startDrag(event, { id: block.getAttribute(NAMED) });
            }
        });
        page.addEventListener('dragover', (event) => {
            const block = named(event);

            if (block === null) {
                this.dragOver(event, null, null, false);

                return;
            }

            const layout = page.defaultView.getComputedStyle(block.parentElement);
            const across = (layout.display.includes('flex') && layout.flexDirection.startsWith('row'))
                || (layout.display.includes('grid') && layout.gridTemplateColumns.trim().split(/\s+/).length > 1);

            this.dragOver(event, block, block.getAttribute(NAMED), across);
        });
        page.addEventListener('keydown', (event) => this.shortcut(event));
        page.addEventListener('drop', (event) => this.dropHere(event));
        page.addEventListener('dragend', () => this.endDrag());
        page.addEventListener('mouseleave', () => {
            page.querySelectorAll('[data-bb-over]').forEach((node) => node.removeAttribute('data-bb-over'));
        });

        this.frame.style.height = `${Math.max(page.documentElement.scrollHeight, 288)}px`;
        this.markPicked();

        // After Enter added a block, the cursor goes into it once the page shows it.
        if (this.focusAfter !== null) {
            const id = this.focusAfter;
            this.focusAfter = null;
            const block = page.querySelector(`[${NAMED}="${CSS.escape(id)}"]`);
            const editable = block?.matches('[data-block-setting]') ? block : block?.querySelector('[data-block-setting]');

            if (editable) {
                this.startTyping(editable, id);
            }
        }
    }

    // ---- Typing on the page --------------------------------------------------------------------------------

    /**
     * Lets a person type into one marked element of the page.
     *
     * The frame still runs no script: the builder makes the element editable from outside, and reads it back when the
     * person is done. Formatted text is edited as markup; anything else as plain text.
     */
    startTyping(node, blockId) {
        if (this.typing !== null && this.typing.node !== node) {
            this.stopTyping(true);
        }

        const state = this.builder.state();
        const block = find(state.document, blockId);
        const entry = state.catalogue.find((candidate) => candidate.name === block?.type);
        const path = node.getAttribute('data-block-setting');
        const rich = settingSchema(entry?.settings, path)?.['x-control'] === 'rich-text';
        const page = this.frame.contentDocument;

        // Dragging would take over a click meant to place the cursor or select words.
        page.querySelectorAll('[draggable]').forEach((element) => element.setAttribute('draggable', 'false'));
        node.setAttribute('contenteditable', rich ? 'true' : 'plaintext-only');
        node.setAttribute('data-bb-typing', '');
        this.typing = { node, blockId, path, rich, schema: entry?.settings, type: block?.type, before: rich ? tidyTyped(node.innerHTML) : node.innerText };
        node.focus();
    }

    /**
     * Stops typing, and sends what was typed when it changed and `save` is set.
     */
    stopTyping(save) {
        const typing = this.typing;

        if (typing === null) {
            return Promise.resolve();
        }

        this.typing = null;
        this.format.hidden = true;
        typing.node.removeAttribute('contenteditable');
        typing.node.removeAttribute('data-bb-typing');
        const value = typing.rich ? tidyTyped(typing.node.innerHTML) : typing.node.innerText.replace(/\n$/, '');
        const block = find(this.builder.state().document, typing.blockId);

        if (!save || block === null || value === typing.before) {
            // Nothing is sent, so the page is redrawn from what the server last gave, putting back anything undone.
            this.frame.srcdoc = '';
            this.canvas(this.builder.state());

            return Promise.resolve();
        }

        // Worked out when its turn comes, so a cell typed while the last one is still saving does not undo it.
        return this.builder.changeSettings(typing.blockId, (settings) => withTyped(settings, typing.schema, typing.path, value));
    }

    typingKey(event) {
        const typing = this.typing;

        if (typing === null || event.target !== typing.node) {
            return;
        }

        const selection = this.frame.contentDocument.getSelection();
        const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
        const atStart = range !== null && range.collapsed && this.offsetIn(typing.node, range) === 0;
        const action = keyAction({
            key: event.key,
            shiftKey: event.shiftKey,
            metaKey: event.metaKey,
            ctrlKey: event.ctrlKey,
            // Only a block's main text adds or removes whole blocks; a cell, caption or citation is part of one.
            empty: typing.node.textContent === ''
                && removableWhenEmpty(find(this.builder.state().document, typing.blockId), typing.schema, typing.path),
            atStart,
            plain: !typing.rich,
            split: startsNewBlock(typing.path),
        });

        if (action === null) {
            return;
        }

        event.preventDefault();

        if (action === 'line-break') {
            this.frame.contentDocument.execCommand(typing.rich ? 'insertLineBreak' : 'insertText', false, typing.rich ? null : '\n');
        } else if (action === 'stop') {
            // Escape leaves the text, keeping what was typed, as leaving it any other way does.
            this.stopTyping(true);
        } else if (action === 'remove') {
            const before = this.previousBlock(typing.blockId);
            this.typing = null;
            this.builder.remove(typing.blockId).then(() => this.select(before));
        } else if (typing.type !== undefined) {
            // Enter: keep what was typed, then start a new block of the same type below it.
            this.stopTyping(true)
                .then(() => this.builder.insertAfter(typing.blockId, typing.type))
                .then(() => {
                    const added = this.builder.state().selected;

                    if (added !== typing.blockId) {
                        this.focusAfter = added;
                    }
                });
        }
    }

    /**
     * Counts the characters of an element before the start of a range, to tell whether the cursor is at the start.
     */
    offsetIn(node, range) {
        const before = range.cloneRange();
        before.selectNodeContents(node);
        before.setEnd(range.startContainer, range.startOffset);

        return before.toString().length;
    }

    previousBlock(id) {
        const order = walk(this.builder.state().document).map((entry) => entry.block.id);
        const index = order.indexOf(id);

        return index > 0 ? order[index - 1] : null;
    }

    /**
     * Builds the small bar of formatting buttons that floats over a selection in formatted text.
     */
    formatBar() {
        const command = (label, glyph, run) =>
            el(
                'button',
                {
                    type: 'button',
                    title: label,
                    'aria-label': label,
                    // Pressing a button must not take the selection away from the text it applies to.
                    onmousedown: (event) => event.preventDefault(),
                    onclick: run,
                },
                el('span', { text: glyph }),
            );
        const run = (name, value = null) => this.frame.contentDocument?.execCommand(name, false, value);

        return el(
            'div',
            { class: 'bb-format', hidden: true, role: 'toolbar', 'aria-label': 'Format text' },
            command('Bold', 'B', () => run('bold')),
            command('Italic', 'I', () => run('italic')),
            command('Code', '</>', () => {
                const page = this.frame.contentDocument;
                const text = page.getSelection().toString();
                const safe = text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
                run('insertHTML', `<code>${safe}</code>`);
            }),
            command('Link', 'Link', () => {
                // The prompt takes focus away from the page in some browsers; that must not end the typing.
                this.keepTyping = true;
                let asked;

                try {
                    asked = window.prompt('Link to (a web address, an email or a page on this site)');
                } finally {
                    this.keepTyping = false;
                }

                this.typing?.node.focus();
                const link = safeLink(asked ?? '');

                if (link === null) {
                    return;
                }

                run('createLink', link);
            }),
        );
    }

    placeFormatBar() {
        const typing = this.typing;
        const selection = this.frame.contentDocument?.getSelection();

        if (typing === null || !typing.rich || selection === undefined || selection.rangeCount === 0 || selection.isCollapsed
            || !typing.node.contains(selection.anchorNode)) {
            this.format.hidden = true;

            return;
        }

        const box = selection.getRangeAt(0).getBoundingClientRect();
        this.format.hidden = false;
        this.format.style.top = `${Math.max(4, box.top - 40)}px`;
        this.format.style.left = `${Math.max(4, box.left)}px`;
    }

    /**
     * Shows which block is selected on the page, and puts its toolbar on it.
     */
    markPicked() {
        const page = this.frame.contentDocument;
        const selected = this.builder.state().selected;

        if (page === null) {
            return;
        }

        page.querySelectorAll('[data-bb-picked]').forEach((node) => node.removeAttribute('data-bb-picked'));

        if (selected !== null) {
            page.querySelector(`[${NAMED}="${CSS.escape(selected)}"]`)?.setAttribute('data-bb-picked', '');
        }

        this.placeBar();
    }

    placeBar() {
        const state = this.builder.state();
        const block = state.selected === null || state.document === null ? null : find(state.document, state.selected);

        if (block === null) {
            this.bar.hidden = true;

            return;
        }

        const here = positionOf(state.document, block.id);
        const beside = here.parent === null ? state.document.blocks : find(state.document, here.parent).children;
        const tool = (label, glyph, run, disabled = false) =>
            el('button', { type: 'button', title: label, 'aria-label': label, disabled, onclick: run }, icon(glyph));

        this.bar.replaceChildren(
            el('strong', { text: this.titles(state)[block.type] ?? block.type }),
            tool('Select the block this is inside', 'parent', () => this.select(here.parent), here.parent === null),
            tool('Move up', 'up', () => this.builder.moveBy(block.id, -1), here.index === 0),
            tool('Move down', 'down', () => this.builder.moveBy(block.id, 1), here.index === beside.length - 1),
            tool('Duplicate', 'copy', () => this.builder.duplicate(block.id)),
            tool('Add a block', 'add', () => this.openDrawer('add')),
            tool('Remove', 'remove', () => this.builder.remove(block.id)),
        );
        this.bar.hidden = false;

        const node = this.frame.contentDocument?.querySelector(`[${NAMED}="${CSS.escape(block.id)}"]`);
        const box = node?.getBoundingClientRect();
        const top = box === undefined ? 4 : Math.max(4, box.top - 38);
        const left = box === undefined ? 4 : Math.max(4, Math.min(box.left, this.page.clientWidth - this.bar.offsetWidth - 4));

        this.bar.style.top = `${top}px`;
        this.bar.style.left = `${left}px`;
    }

    /**
     * Undoes or redoes on the usual keys, unless the person is typing: a text field has its own undo, and taking
     * the keys from it would undo a block edit when they meant to undo a letter.
     */
    shortcut(event) {
        const typing = Boolean(event.target.closest?.('input, textarea, select, [contenteditable]'));
        const key = event.key.toLowerCase();

        if (typing || !(event.metaKey || event.ctrlKey) || event.altKey || (key !== 'z' && key !== 'y')) {
            return;
        }

        event.preventDefault();

        if (key === 'y' || event.shiftKey) {
            this.builder.redo();
        } else {
            this.builder.undo();
        }
    }

    // ---- Dragging ------------------------------------------------------------------------------------------

    /**
     * Begins dragging a block that is on the page (`id`) or a new one from the add-block panel (`type`).
     */
    startDrag(event, drag) {
        this.drag = drag;
        event.stopPropagation();
        event.dataTransfer.effectAllowed = drag.id === undefined ? 'copy' : 'move';
        event.dataTransfer.setData('text/plain', drag.id ?? drag.type);
    }

    endDrag() {
        this.drag = null;
        this.clearDropMarks();
    }

    clearDropMarks() {
        this.drop = null;

        for (const root of [this, this.frame.contentDocument]) {
            root?.querySelectorAll('[data-bb-drop]').forEach((node) => node.removeAttribute('data-bb-drop'));
        }
    }

    /**
     * Works out where a drop would land while something is dragged over a block, and shows it.
     *
     * @param {?Element} node The block's element, in the outline or on the page; null over the page itself.
     * @param {?string} id The block under the pointer; null for the end of the page.
     * @param {boolean} across Whether the block's siblings run across rather than down.
     */
    dragOver(event, node, id, across) {
        if (this.drag === null) {
            return;
        }

        const state = this.builder.state();
        const block = id === null ? null : find(state.document, id);
        const holds = state.catalogue.find((entry) => entry.name === block?.type)?.acceptsChildren === true;
        const box = node?.getBoundingClientRect();
        const zone = box === undefined
            ? 'inside'
            : across
              ? dropZone(event.clientX - box.left, box.width, holds)
              : dropZone(event.clientY - box.top, box.height, holds);
        const position = dropPosition(state.document, this.drag.id ?? null, id, zone);

        this.clearDropMarks();
        event.stopPropagation();

        // Leaving the event alone tells the browser this is not a place to drop.
        if (position === null) {
            return;
        }

        event.preventDefault();
        this.drop = position;
        node?.setAttribute('data-bb-drop', zone === 'inside' || !across ? zone : `${zone}-x`);
    }

    dropHere(event) {
        const { drag, drop } = this;

        if (drag === null || drop === null) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        this.endDrag();

        if (drag.id === undefined) {
            this.builder.insert(drag.type, drop);
        } else {
            this.builder.move(drag.id, drop);
        }
    }

    select(id, reveal = false) {
        this.ui.errors = {};
        this.ui.target = 0;
        this.ui.rules = null;
        this.builder.dismiss();
        this.builder.select(id);

        if (reveal && id !== null) {
            this.frame.contentDocument
                ?.querySelector(`[${NAMED}="${CSS.escape(id)}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Shows what the server refused that no field on screen can show, and anything else that went wrong.
     */
    notices(state) {
        const loose = state.refusals.filter((refusal) => refusal.path === null || refusal.block !== state.selected);

        if (loose.length === 0) {
            this.toast.hidden = true;

            return;
        }

        this.toast.hidden = false;
        this.toast.replaceChildren(
            el('button', { class: 'bb-link', type: 'button', text: 'Dismiss', style: 'float:right', onclick: () => this.builder.dismiss() }),
            loose.length === 1
                ? el('p', { text: loose[0].message })
                : el('ul', {}, loose.map((refusal) => el('li', { text: refusal.message }))),
        );
    }

    // ---- Inspector -----------------------------------------------------------------------------------------

    inspector(state) {
        const block = state.selected === null || state.document === null ? null : find(state.document, state.selected);

        if (block === null) {
            return [
                el(
                    'div',
                    { class: 'bb-side-head' },
                    el('h2', { text: 'Nothing selected' }),
                    el('p', { text: 'Click a block on the page to change it.' }),
                ),
            ];
        }

        const entry = state.catalogue.find((candidate) => candidate.name === block.type);
        const applies = (decorator) => decorator.appliesTo === null || decorator.appliesTo.includes(block.type);
        const decorators = (state.vocabulary?.decorators ?? []).filter(applies);
        const style = decorators.find((decorator) => decorator.namespace === STYLE_NS);
        const rules = decorators.find((decorator) => decorator.namespace === RULES_NS);
        const others = decorators.filter((decorator) => decorator !== style && decorator !== rules);
        const tabs = [
            ['block', 'Block', true],
            ['style', 'Style', style !== undefined],
            ['visibility', 'Visibility', rules !== undefined],
        ].filter(([, , shown]) => shown);
        const tab = tabs.some(([name]) => name === this.ui.tab) ? this.ui.tab : 'block';
        const issues = state.issues.filter((issue) => issue.block === block.id);

        if (this.ui.picker !== null && this.ui.picker.block === block.id) {
            return this.picker(block);
        }

        const body = tab === 'style'
            ? this.styleTab(state, block, style)
            : tab === 'visibility'
              ? this.rulesTab(state, block)
              : this.blockTab(state, block, entry, others);

        return [
            el(
                'div',
                { class: 'bb-side-head' },
                el('h2', { text: entry?.title ?? block.type }),
                entry?.description ? el('p', { text: entry.description }) : null,
            ),
            el(
                'div',
                { class: 'bb-tabs', role: 'tablist' },
                tabs.map(([name, label]) =>
                    el('button', {
                        type: 'button',
                        role: 'tab',
                        text: label,
                        'aria-selected': String(tab === name),
                        onclick: () => {
                            this.ui.tab = name;
                            this.render();
                        },
                    }),
                ),
            ),
            el(
                'div',
                { class: 'bb-side-body', role: 'tabpanel' },
                issues.length === 0
                    ? null
                    : el('div', { class: 'bb-toast', role: 'status' }, el('ul', {}, issues.map((issue) => el('li', { text: issue.message })))),
                body,
            ),
        ];
    }

    blockTab(state, block, entry, others) {
        const own = fields(entry?.settings, block.settings);
        const save = (name, value) => this.builder.updateSettings(block.id, withValue(block.settings, name, value));

        return [
            own.length === 0
                ? el('p', { class: 'bb-none', text: 'This block has nothing of its own to set. Try the Style tab.' })
                : el('div', { style: 'padding-top:.75rem' }, own.map((field) => this.field(state, block, 'settings', field, save, ''))),
            others.map((decorator) =>
                this.group(
                    words(decorator.namespace.split('/').pop()),
                    fields(decorator.settings, block.extensions?.[decorator.namespace]).map((field) =>
                        this.field(state, block, decorator.namespace, field, (name, value) =>
                            this.extend(block, decorator.namespace, name, value), ''),
                    ),
                ),
            ),
        ];
    }

    styleTab(state, block, decorator) {
        const stored = block.extensions?.[STYLE_NS] ?? {};
        const points = state.vocabulary?.breakpoints ?? [];
        const widths = [['', 'All widths'], ...points.map((point) => [point.name, `From ${point.name}`])];
        const at = widths.some(([name]) => name === this.ui.width) ? this.ui.width : '';
        const save = (name, value) => this.extend(block, STYLE_NS, name, value);

        return [
            el(
                'div',
                { class: 'bb-choice bb-widths', role: 'group', 'aria-label': 'Which widths these styles apply to' },
                widths.map(([name, label]) =>
                    el('button', {
                        type: 'button',
                        text: label,
                        'aria-pressed': String(at === name),
                        onclick: () => {
                            this.ui.width = name;
                            this.render();
                        },
                    }),
                ),
            ),
            el('span', {
                class: 'bb-help',
                text: at === '' ? 'These apply at every width unless a wider setting replaces them.' : `These replace the general styles from ${words(at).toLowerCase()} width up.`,
            }),
            styleSections(fields(decorator.settings, stored)).map((section) =>
                this.group(section.title, section.fields.map((field) => this.field(state, block, STYLE_NS, field, save, at)), true),
            ),
        ];
    }

    extend(block, namespace, name, value) {
        return this.builder.updateExtension(block.id, namespace, withValue(block.extensions?.[namespace] ?? {}, name, value));
    }

    group(title, children, open = false) {
        return el('details', { class: 'bb-group', open }, el('summary', { text: title }), el('div', {}, children));
    }

    /**
     * Draws one setting with the control that suits it; a change is sent when the person leaves the control.
     *
     * @param {string} at For a setting that may differ by width, the width being edited; empty for every width.
     */
    field(state, block, group, field, save, at) {
        const key = `${group}:${field.name}:${at}`;
        const responsive = field.hint === 'responsive';
        const shown = responsive ? readResponsive(field.isDefault ? undefined : field.value, at) : field.value;
        const commit = (raw) => {
            try {
                const parsed = parseField({ ...field, control: this.controlKind(field) }, raw);
                delete this.ui.errors[key];
                save(field.name, responsive ? writeResponsive(field.isDefault ? undefined : field.value, at, parsed ?? '') : parsed);
            } catch (error) {
                if (!(error instanceof FieldError)) {
                    throw error;
                }

                this.ui.errors[key] = error.message;
                this.render();
            }
        };
        const wrong = this.ui.errors[key] ?? fieldRefusal(state.refusals, block.id, group, field.name);
        const overridden = responsive && at === '' && field.value !== null && typeof field.value === 'object'
            && Object.keys(field.value).some((name) => name !== 'base');
        const label = field.title === field.name ? words(field.name) : field.title;
        const control = this.control(state, field, shown, commit, key, wrong !== null);

        if (control.type === 'checkbox') {
            return el('label', { class: 'bb-field bb-check' }, control, el('span', { text: label }));
        }

        return el(
            'div',
            { class: 'bb-field' },
            el(
                'label',
                { class: 'bb-label', for: `bb-${key}` },
                el('span', { text: field.private ? `${label} (only editors see this)` : label }),
                overridden ? el('i', { title: 'Has a different value at some widths' }) : null,
            ),
            control,
            field.description ? el('span', { class: 'bb-help', text: field.description }) : null,
            wrong === null ? null : el('span', { class: 'bb-wrong', text: wrong }),
        );
    }

    /**
     * Decides how a field's text is turned into a value: a responsive setting is edited one width at a time, as
     * the plain value it holds at that width.
     */
    controlKind(field) {
        if (field.hint !== 'responsive') {
            return field.control;
        }

        return field.kind === 'integer' || field.kind === 'number' ? 'number' : 'text';
    }

    control(state, field, shown, commit, key, invalid) {
        const common = { id: `bb-${key}`, 'data-key': key, 'aria-invalid': invalid ? 'true' : null };
        const text = shown === undefined || shown === null ? '' : typeof shown === 'object' ? JSON.stringify(shown) : String(shown);
        const kind = this.controlKind(field);

        if (field.name === 'textAlign' && field.hint === 'responsive') {
            return el(
                'div',
                { class: 'bb-choice', role: 'group' },
                ['left', 'center', 'right', 'justify'].map((side) =>
                    el('button', {
                        type: 'button',
                        text: words(side),
                        'aria-pressed': String(text === side),
                        onclick: () => commit(text === side ? '' : side),
                    }),
                ),
            );
        }

        if (field.name === 'fontWeight' && field.hint === 'responsive') {
            const weights = [['', 'As inherited'], ['400', 'Regular'], ['500', 'Medium'], ['600', 'Semibold'], ['700', 'Bold']];

            return el(
                'select',
                { class: 'bb-input', ...common, onchange: (event) => commit(event.target.value) },
                weights.map(([value, label]) => el('option', { value, text: label, selected: text === value })),
            );
        }

        if ((field.name === 'color' || field.name === 'background') && field.hint === 'responsive') {
            return this.colour(state, text, commit, common);
        }

        if (field.hint === 'breakpoint') {
            const points = state.vocabulary?.breakpoints ?? [];

            return el(
                'select',
                { class: 'bb-input', ...common, onchange: (event) => commit(event.target.value) },
                el('option', { value: '', text: 'Always side by side', selected: text === '' }),
                points.map((point) =>
                    el('option', { value: point.name, text: `From ${words(point.name).toLowerCase()} width`, selected: text === point.name }),
                ),
            );
        }

        if (field.hint === 'table') {
            return this.tableControl(Array.isArray(shown) ? shown : [], commit);
        }

        if (field.hint === 'media') {
            return this.mediaControl(state, field, typeof shown === 'string' ? shown : '', commit);
        }

        if (field.hint === 'media-list') {
            return this.mediaListControl(state, field, Array.isArray(shown) ? shown : [], commit);
        }

        if (field.hint === 'code') {
            return el('textarea', {
                class: 'bb-input bb-code',
                ...common,
                rows: 8,
                spellcheck: 'false',
                text,
                onchange: (event) => commit(event.target.value),
            });
        }

        if (kind === 'checkbox') {
            return el('input', { type: 'checkbox', ...common, checked: shown === true, onchange: (event) => commit(event.target.checked) });
        }

        if (kind === 'select') {
            return el(
                'select',
                { class: 'bb-input', ...common, onchange: (event) => commit(field.options[Number(event.target.value)]) },
                field.options.map((option, index) =>
                    el('option', { value: String(index), text: words(String(option)), selected: option === shown }),
                ),
            );
        }

        if (kind === 'json') {
            return el('textarea', {
                class: 'bb-input bb-code',
                ...common,
                text: field.isDefault ? '' : text,
                onchange: (event) => commit(event.target.value),
            });
        }

        if (kind === 'text' && (field.name === 'text' || text.length > 60)) {
            return el('textarea', { class: 'bb-input', ...common, text, onchange: (event) => commit(event.target.value) });
        }

        return el('input', {
            class: 'bb-input',
            type: kind === 'number' ? 'number' : 'text',
            ...common,
            value: text,
            placeholder: kind === 'number' || field.hint !== 'responsive' ? null : 'For example 1rem',
            onchange: (event) => commit(event.target.value),
        });
    }

    // ---- Media ---------------------------------------------------------------------------------------------

    /**
     * Looks up a chosen media item once, so its picture and name can be shown; the inspector is drawn again when
     * the answer comes.
     *
     * @returns {?object} The item, `{missing: true}` when the library no longer has it, or null while loading.
     */
    mediaItem(id) {
        if (!this.media.has(id)) {
            this.media.set(id, null);
            this.api.mediaItem(id)
                .then((item) => this.media.set(id, item))
                .catch(() => this.media.set(id, { missing: true }))
                .finally(() => this.render());
        }

        return this.media.get(id);
    }

    /**
     * A small picture of an image, or the name of any other file.
     */
    mediaThumb(id) {
        const item = this.mediaItem(id);

        if (item === null) {
            return el('span', { class: 'bb-thumb', text: 'Loading…' });
        }

        if (item.missing) {
            return el('span', { class: 'bb-thumb bb-thumb-missing', text: 'No longer in the library' });
        }

        if (item.kind === 'image') {
            const small = [...(item.sizes ?? [])].sort((a, b) => a.width - b.width).find((size) => size.width >= 120);

            return el('img', { class: 'bb-thumb', src: small?.url ?? item.url, alt: item.alt || item.title });
        }

        return el('span', { class: 'bb-thumb', text: item.title });
    }

    /**
     * One media setting: what is chosen, with buttons to choose another or clear it.
     */
    mediaControl(state, field, id, commit) {
        const noun = { image: 'image', video: 'video', audio: 'audio file' }[field.mediaKind] ?? 'file';

        return el(
            'div',
            { class: 'bb-media' },
            id === '' ? null : this.mediaThumb(id),
            el(
                'div',
                { class: 'bb-media-buttons' },
                el('button', {
                    type: 'button',
                    text: id === '' ? `Choose ${noun}` : 'Replace',
                    // Sent against the settings as they are then, not as they were when the picker opened.
                    onclick: () => this.openPicker(state, field, false, (chosen, block) =>
                        this.builder.changeSettings(block, (settings) => withValue(settings, field.name, chosen))),
                }),
                id === '' ? null : el('button', { type: 'button', text: 'Remove', onclick: () => commit('') }),
            ),
        );
    }

    /**
     * A list of media, such as a gallery's images, in order, each with buttons to move or remove it.
     */
    mediaListControl(state, field, ids, commit) {
        const send = (next) => commit(JSON.stringify(next));

        return el(
            'div',
            { class: 'bb-media-list' },
            el(
                'ol',
                {},
                ids.map((id, index) =>
                    el(
                        'li',
                        {},
                        this.mediaThumb(id),
                        el(
                            'div',
                            { class: 'bb-media-buttons' },
                            el('button', {
                                type: 'button',
                                text: 'Earlier',
                                disabled: index === 0,
                                onclick: () => send(movedMedia(ids, index, index - 1)),
                            }),
                            el('button', {
                                type: 'button',
                                text: 'Later',
                                disabled: index === ids.length - 1,
                                onclick: () => send(movedMedia(ids, index, index + 1)),
                            }),
                            el('button', { type: 'button', text: 'Remove', onclick: () => send(withoutMediaAt(ids, index)) }),
                        ),
                    ),
                ),
            ),
            el('button', {
                type: 'button',
                class: 'bb-media-add',
                text: ids.length === 0 ? 'Choose images' : 'Add images',
                // Each pick is worked out when its turn comes, so several picks in a row all stay.
                onclick: () => this.openPicker(state, field, true, (chosen, block) =>
                    this.builder.changeSettings(block, (settings) => addedMedia(settings, field.name, chosen))),
            }),
        );
    }

    /**
     * Opens the picker for one setting, in place of the inspector's tabs.
     *
     * @param {boolean} multiple Whether it stays open after a choice, for adding several.
     * @param {(id: string, block: string) => void} choose Given the chosen id and the block the picker is for.
     */
    openPicker(state, field, multiple, choose) {
        this.ui.picker = {
            block: state.selected,
            title: field.title === field.name ? words(field.name) : field.title,
            kind: field.mediaKind,
            multiple,
            choose,
            search: '',
            page: 1,
            items: [],
            total: 0,
            busy: false,
            error: null,
        };
        this.loadPicker(1);
    }

    /**
     * Loads a page of the library into the picker; page 1 starts over, later pages are added below.
     */
    loadPicker(page) {
        const picker = this.ui.picker;

        if (picker === null) {
            return;
        }

        picker.busy = true;
        picker.error = null;
        this.render();
        this.api.media(mediaQuery(picker.kind, picker.search, page))
            .then((answer) => {
                if (this.ui.picker !== picker) {
                    return;
                }

                picker.items = page === 1 ? answer.items : [...picker.items, ...answer.items];
                picker.total = answer.total;
                picker.page = page;
                answer.items.forEach((item) => this.media.set(item.id, item));
            })
            .catch((error) => {
                picker.error = error.message;
            })
            .finally(() => {
                picker.busy = false;
                this.render();
            });
    }

    /**
     * Uploads a file from the person's computer and chooses it; a refusal is shown with the server's reason.
     */
    uploadToPicker(file) {
        const picker = this.ui.picker;

        if (picker === null || file === undefined) {
            return;
        }

        picker.busy = true;
        picker.error = null;
        this.render();
        this.api.upload(file)
            .then((item) => {
                this.media.set(item.id, item);
                this.choose(item.id);
                this.loadPicker(1);
            })
            .catch((error) => {
                picker.error = error.message;
                picker.busy = false;
                this.render();
            });
    }

    choose(id) {
        const picker = this.ui.picker;

        if (picker === null) {
            return;
        }

        picker.choose(id, picker.block);

        if (!picker.multiple) {
            this.ui.picker = null;
        }

        this.render();
    }

    /**
     * The picker: a search, an upload button and a grid of the library's items of the setting's kind.
     */
    picker(block) {
        const picker = this.ui.picker;
        const close = () => {
            this.ui.picker = null;
            this.render();
        };
        let timer = null;

        return [
            el(
                'div',
                { class: 'bb-side-head bb-picker-head' },
                el('h2', { text: picker.title }),
                el('button', { type: 'button', class: 'bb-link', text: picker.multiple ? 'Done' : 'Cancel', onclick: close }),
            ),
            el(
                'div',
                { class: 'bb-side-body bb-picker' },
                el('input', {
                    class: 'bb-input',
                    type: 'search',
                    // Lets the inspector put the cursor back here when it is drawn again while the person types.
                    'data-key': 'picker-search',
                    placeholder: 'Search by name',
                    'aria-label': 'Search the media library',
                    value: picker.search,
                    oninput: (event) => {
                        picker.search = event.target.value;
                        clearTimeout(timer);
                        timer = setTimeout(() => this.loadPicker(1), 250);
                    },
                }),
                el(
                    'label',
                    { class: 'bb-upload' },
                    el('span', { text: picker.busy ? 'Working…' : 'Upload from your computer' }),
                    el('input', {
                        type: 'file',
                        accept: accepts(picker.kind),
                        disabled: picker.busy,
                        onchange: (event) => this.uploadToPicker(event.target.files?.[0]),
                    }),
                ),
                picker.error === null ? null : el('p', { class: 'bb-wrong', role: 'alert', text: picker.error }),
                picker.items.length === 0 && !picker.busy
                    ? el('p', { class: 'bb-none', text: picker.search === '' ? 'The library has nothing of this kind yet.' : 'Nothing matches.' })
                    : null,
                el(
                    'div',
                    { class: 'bb-picker-grid' },
                    picker.items.map((item) =>
                        el(
                            'button',
                            {
                                type: 'button',
                                title: item.title,
                                'aria-label': `Choose ${item.title}`,
                                onclick: () => this.choose(item.id),
                            },
                            this.mediaThumb(item.id),
                        ),
                    ),
                ),
                picker.items.length < picker.total
                    ? el('button', { type: 'button', class: 'bb-media-add', text: 'More', disabled: picker.busy, onclick: () => this.loadPicker(picker.page + 1) })
                    : null,
            ),
        ];
    }

    /**
     * A table's size, with buttons that add or take away a row or column at the end; the cells themselves are
     * typed into on the page, so their text is kept whatever the size becomes.
     */
    tableControl(rows, commit) {
        const shape = tableShape(rows);
        const send = (next) => commit(JSON.stringify(next));
        const count = (number, noun) => `${number} ${noun}${number === 1 ? '' : 's'}`;
        const button = (text, disabled, onclick) => el('button', { type: 'button', text, disabled, onclick });

        return el(
            'div',
            { class: 'bb-table-size' },
            el('span', { text: `${count(shape.rows, 'row')}, ${count(shape.columns, 'column')}` }),
            el(
                'div',
                { class: 'bb-table-buttons' },
                button('Add row', false, () => send(withRow(rows, shape.rows))),
                button('Remove row', shape.rows <= 1, () => send(withoutRow(rows, shape.rows - 1))),
                button('Add column', false, () => send(withColumn(rows, shape.columns))),
                button('Remove column', shape.columns <= 1, () => send(withoutColumn(rows, shape.columns - 1))),
            ),
        );
    }

    /**
     * A colour: the site's own colours to pick from first, then any colour.
     */
    colour(state, text, commit, common) {
        const tokens = Object.entries(state.vocabulary?.tokens ?? {}).filter(([name]) => name.startsWith('color.'));
        const hex = /^#[0-9a-f]{6}$/i.test(text) ? text : '#000000';

        return el(
            'div',
            {},
            el(
                'div',
                { class: 'bb-colour' },
                el('input', { type: 'color', value: hex, 'aria-label': 'Pick a colour', onchange: (event) => commit(event.target.value) }),
                el('input', {
                    class: 'bb-input',
                    type: 'text',
                    ...common,
                    value: text,
                    placeholder: 'A colour, such as #1b6ac9',
                    onchange: (event) => commit(event.target.value),
                }),
            ),
            tokens.length === 0
                ? null
                : el(
                      'div',
                      { class: 'bb-chips' },
                      tokens.map(([name, value]) =>
                          el(
                              'button',
                              {
                                  type: 'button',
                                  'aria-pressed': String(text === `token:${name}`),
                                  title: `The site's ${words(name.slice(6)).toLowerCase()} colour`,
                                  onclick: () => commit(text === `token:${name}` ? '' : `token:${name}`),
                              },
                              el('i', { style: `background:${/^#[0-9a-f]{3,8}$/i.test(value) ? value : 'transparent'}` }),
                              el('span', { text: words(name.slice(6)) }),
                          ),
                      ),
                  ),
        );
    }

    // ---- Visibility ----------------------------------------------------------------------------------------

    rulesTab(state, block) {
        const conditions = state.vocabulary?.conditions ?? [];
        // Rules are kept as the person left them until the server accepts them: a rule that still needs filling
        // in is refused, and must stay on screen to be finished rather than vanish.
        const draft = this.ui.rules?.block === block.id ? this.ui.rules.groups : null;
        const groups = draft ?? block.extensions?.[RULES_NS]?.groups ?? [];
        const save = (next) => {
            this.ui.rules = { block: block.id, groups: next };
            this.extend(block, RULES_NS, 'groups', next.length === 0 ? undefined : next).then(() => {
                if (this.builder.state().refusals.length === 0) {
                    this.ui.rules = null;
                }

                this.render();
            });
            this.render();
        };
        const label = (name) => words(name.split('/').pop().replaceAll('-', ' '));

        if (conditions.length === 0) {
            return [
                el('p', {
                    class: 'bb-none',
                    text: 'This block is always shown. Rules for when to show it need conditions, and this site has none yet.',
                }),
            ];
        }

        const chooser = (text, key, pick) =>
            el(
                'select',
                {
                    class: 'bb-input',
                    'aria-label': text,
                    'data-key': key,
                    onchange: (event) => event.target.value !== '' && pick(event.target.value),
                },
                el('option', { value: '', text }),
                conditions.map((condition) => el('option', { value: condition.name, text: label(condition.name) })),
            );

        return [
            el('p', {
                class: 'bb-none',
                text: groups.length === 0 ? 'This block is always shown.' : 'This block is shown only when the rules below say so.',
            }),
            groups.flatMap((rules, g) => [
                g === 0 ? null : el('p', { class: 'bb-or', text: 'or' }),
                el(
                    'div',
                    { class: 'bb-rules' },
                    el('p', { text: 'Show when all of these are true' }),
                    rules.map((rule, r) => this.rule(state, block, conditions, groups, g, r, rule, save, label)),
                    chooser('Add a rule…', `rule-add-${g}`, (name) => save(withRule(groups, g, name))),
                ),
            ]),
            el('div', { style: 'margin-top:.6rem' }, chooser(
                groups.length === 0 ? 'Show only when…' : 'Or show when…',
                'group-add',
                (name) => save(withGroup(groups, name)),
            )),
        ];
    }

    rule(state, block, conditions, groups, g, r, rule, save, label) {
        const condition = conditions.find((candidate) => candidate.name === rule.condition);
        const change = (changes) => save(withRuleChange(groups, g, r, changes));
        const wrong = fieldRefusal(state.refusals, block.id, RULES_NS, `groups.${g}.${r}`)
            ?? state.issues.find((issue) => issue.block === block.id && issue.path === `@${RULES_NS}:groups.${g}.${r}`)?.message
            ?? null;

        return el(
            'div',
            { class: 'bb-rule' },
            el(
                'div',
                { class: 'bb-rule-top' },
                el(
                    'select',
                    {
                        class: 'bb-input',
                        'aria-label': 'Condition',
                        'data-key': `rule-${g}-${r}`,
                        onchange: (event) => change({ condition: event.target.value, arguments: {} }),
                    },
                    condition === undefined ? el('option', { value: rule.condition, text: `${rule.condition} (missing)`, selected: true }) : null,
                    conditions.map((option) =>
                        el('option', { value: option.name, text: label(option.name), selected: option.name === rule.condition }),
                    ),
                ),
                el('button', { class: 'bb-icon', type: 'button', title: 'Remove this rule', 'aria-label': 'Remove this rule', onclick: () => save(withoutRule(groups, g, r)) }, icon('remove')),
            ),
            fields(condition?.arguments, rule.arguments).map((field) =>
                this.field(state, block, `rule-${g}-${r}`, field, (name, value) =>
                    change({ arguments: withValue(rule.arguments ?? {}, name, value) }), ''),
            ),
            el(
                'label',
                { class: 'bb-check' },
                el('input', { type: 'checkbox', checked: rule.negate === true, onchange: (event) => change({ negate: event.target.checked }) }),
                el('span', { text: 'Show when this is not true' }),
            ),
            wrong === null
                ? null
                : el('span', {
                      class: 'bb-wrong',
                      text: wrong.includes('do not match its schema') ? 'Fill in what this rule needs.' : wrong,
                  }),
        );
    }

    // ---- Trail ---------------------------------------------------------------------------------------------

    trail(state) {
        if (state.document === null) {
            return [];
        }

        const titles = this.titles(state);
        const steps = state.selected === null ? [] : breadcrumb(state.document, state.selected);

        return [
            el('button', { type: 'button', text: 'Page', onclick: () => this.select(null) }),
            ...steps.flatMap((block) => [
                el('span', { text: '›', 'aria-hidden': 'true' }),
                el('button', { type: 'button', text: titles[block.type] ?? block.type, onclick: () => this.select(block.id, true) }),
            ]),
        ];
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
