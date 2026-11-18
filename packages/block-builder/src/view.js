/**
 * What the interface shows, worked out from the document and the catalogue, with no interface of its own.
 */
import { find, positionOf, walk } from './tree.js';

const titleCase = (text) => text.charAt(0).toUpperCase() + text.slice(1);

/**
 * Arranges block types for the "add a block" panel: a section per package that defined them, searchable across all.
 *
 * Sections follow the order of `packages`, which is the order the host loaded them in. Blocks a host registered
 * itself, with no package, come last. Within a section, blocks are grouped by category only when the section has
 * more than one, so a small plugin is a plain list.
 *
 * @param {Array<object>} entries Catalogue entries, each naming its `package`.
 * @param {Array<{name: string, title: string, description: string}>} packages The packages that defined them.
 * @param {string} query What was typed; matched against a block's title, name, keywords and description, and its
 *   package's title.
 * @returns {Array<{package: ?string, title: string, description: string, groups: Array<object>}>} Only sections
 *   with a match.
 */
export function groupCatalogue(entries, packages, query) {
    const wanted = query.trim().toLowerCase();
    const known = new Map(packages.map((entry) => [entry.name, entry]));
    const sections = new Map(packages.map((entry) => [entry.name, []]));
    const loose = [];

    for (const entry of entries) {
        const owner = known.get(entry.package) ?? null;
        const text = [entry.title, entry.name, entry.description, ...(entry.keywords ?? []), owner?.title ?? '']
            .join(' ')
            .toLowerCase();

        if (wanted !== '' && !text.includes(wanted)) {
            continue;
        }

        (owner === null ? loose : sections.get(owner.name)).push(entry);
    }

    const grouped = (list) => {
        const categories = new Map();

        list.forEach((entry) => {
            const category = entry.category ? titleCase(entry.category) : 'Other';
            categories.set(category, [...(categories.get(category) ?? []), entry]);
        });

        return categories.size === 1
            ? [{ category: '', entries: list }]
            : [...categories].map(([category, members]) => ({ category, entries: members }));
    };

    return [
        ...packages.map((entry) => ({ ...entry, list: sections.get(entry.name) })),
        { name: null, title: 'Added by the site', description: '', list: loose },
    ]
        .filter((section) => section.list.length > 0)
        .map((section) => ({
            package: section.name,
            title: section.title,
            description: section.description,
            groups: grouped(section.list),
        }));
}

/**
 * Lists where a new block can go, given what is selected.
 *
 * @returns {Array<{label: string, position: {parent: ?string, index: number}}>} The likeliest place first.
 */
export function insertTargets(document, selectedId, catalogue) {
    const selected = selectedId === null ? null : find(document, selectedId);

    if (selected === null) {
        return [{ label: 'At the end of the page', position: { parent: null, index: document.blocks.length } }];
    }

    const entry = catalogue.find((candidate) => candidate.name === selected.type);
    const title = entry?.title ?? selected.type;
    const here = positionOf(document, selected.id);
    const targets = [{ label: `After ${title}`, position: { parent: here.parent, index: here.index + 1 } }];

    if (entry?.acceptsChildren) {
        targets.unshift({
            label: `Inside ${title}`,
            position: { parent: selected.id, index: (selected.children ?? []).length },
        });
    }

    return targets;
}

/**
 * Lists a block's ancestors and the block itself, outermost first.
 *
 * @returns {Array<object>} Empty when the document has no such block.
 */
export function breadcrumb(document, id) {
    const entries = walk(document);
    const trail = [];
    let entry = entries.find((candidate) => candidate.block.id === id);

    while (entry !== undefined) {
        trail.unshift(entry.block);
        const { parent } = entry;
        entry = parent === null ? undefined : entries.find((candidate) => candidate.block.id === parent);
    }

    return trail;
}

/**
 * Reads a value that may differ by screen width, for one width.
 *
 * @param {*} value A single value, or a map of `base` and breakpoint names.
 * @param {string} at A breakpoint name; empty for the value that applies at every width.
 * @returns {*} An empty string when nothing is set for that width.
 */
export function readResponsive(value, at) {
    const key = at === '' ? 'base' : at;

    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return value[key] ?? '';
    }

    return at === '' ? (value ?? '') : '';
}

/**
 * Sets a value that may differ by screen width, for one width, and gives it back in its shortest form.
 *
 * @param {*} next The new value; an empty string clears that width.
 * @returns {*} A single value when only the base is set, a map otherwise, undefined when nothing is left.
 */
export function writeResponsive(value, at, next) {
    const map = value !== null && typeof value === 'object' && !Array.isArray(value)
        ? { ...value }
        : value === undefined || value === null || value === '' ? {} : { base: value };
    const key = at === '' ? 'base' : at;

    if (next === '' || next === undefined) {
        delete map[key];
    } else {
        map[key] = next;
    }

    const keys = Object.keys(map);

    if (keys.length === 0) {
        return undefined;
    }

    return keys.length === 1 && keys[0] === 'base' ? map.base : map;
}

/**
 * Lists the widths the page can be previewed at: a phone, the first breakpoint, and the full width.
 *
 * @returns {Array<{name: string, label: string, width: ?number}>} A null width fills the space there is.
 */
export function devices(vocabulary) {
    const first = vocabulary?.breakpoints?.[0];

    return [
        { name: 'phone', label: 'Phone', width: 390 },
        ...(first === undefined ? [] : [{ name: first.name, label: titleCase(first.name), width: first.minWidth }]),
        { name: 'full', label: 'Full width', width: null },
    ];
}

const SECTIONS = [
    ['Spacing', ['padding', 'margin', 'gap']],
    ['Size', ['width', 'maxWidth', 'minHeight']],
    ['Text', ['textAlign', 'fontSize', 'fontWeight', 'lineHeight']],
    ['Colour', ['color', 'background']],
    ['Corners', ['borderRadius']],
];

/**
 * Arranges style fields into the sections people look for them under.
 *
 * @returns {Array<{title: string, fields: Array<object>}>} Sections with at least one field; unknown fields last.
 */
export function styleSections(fields) {
    const known = new Set(SECTIONS.flatMap(([, names]) => names));
    const sections = SECTIONS.map(([title, names]) => ({
        title,
        fields: names.map((name) => fields.find((field) => field.name === name)).filter((field) => field !== undefined),
    }));

    sections.push({ title: 'More', fields: fields.filter((field) => !known.has(field.name)) });

    return sections.filter((section) => section.fields.length > 0);
}

const rule = (condition) => ({ condition, arguments: {}, negate: false });
const tidy = (groups) => groups.filter((group) => group.length > 0);

/**
 * Adds an alternative: a new group holding one rule. The block shows when any one group holds.
 */
export function withGroup(groups, condition) {
    return [...groups, [rule(condition)]];
}

/**
 * Adds a rule to a group; every rule of a group must hold.
 */
export function withRule(groups, group, condition) {
    return groups.map((rules, index) => (index === group ? [...rules, rule(condition)] : rules));
}

/**
 * Changes one rule.
 */
export function withRuleChange(groups, group, index, changes) {
    return groups.map((rules, g) =>
        g === group ? rules.map((entry, r) => (r === index ? { ...entry, ...changes } : entry)) : rules,
    );
}

/**
 * Removes one rule, and its group with it when that was the last: an empty group is never stored.
 */
export function withoutRule(groups, group, index) {
    return tidy(groups.map((rules, g) => (g === group ? rules.filter((entry, r) => r !== index) : rules)));
}

/**
 * Finds what the server said about one field, so it can be shown beside that field.
 *
 * @param {string} group `settings` for the block's own settings, otherwise a decorator's namespace.
 * @returns {?string} Null when no refusal is about the field.
 */
export function fieldRefusal(refusals, blockId, group, name) {
    const path = group === 'settings' ? name : `@${group}:${name}`;
    const match = refusals.find(
        (refusal) => refusal.block === blockId && (refusal.path === path || refusal.path?.startsWith(`${path}.`)),
    );

    if (match === undefined) {
        return null;
    }

    // The server's wording for a format check describes the schema, not what to do about it.
    return match.message.includes('required pattern') ? 'That is not a value this setting accepts.' : match.message;
}
