/**
 * Turns a settings schema into the fields of a form, so a builder needs no code for any particular block.
 */

/**
 * Something typed into a field that cannot be the value the schema asks for.
 */
export class FieldError extends Error {
    constructor(field, message) {
        super(message);
        this.name = 'FieldError';
        this.field = field;
    }
}

/**
 * Picks the control for one setting.
 *
 * Anything without an obvious control (lists, objects, a value that may differ by breakpoint, a schema that
 * allows several types) is edited as JSON; that is always correct, if not always comfortable.
 */
function controlFor(schema) {
    if (Array.isArray(schema.enum)) {
        return 'select';
    }

    switch (schema.type) {
        case 'string':
            return 'text';
        case 'integer':
        case 'number':
            return 'number';
        case 'boolean':
            return 'checkbox';
        default:
            return 'json';
    }
}

/**
 * Lists the fields of a settings form.
 *
 * @param {object} [schema] A settings schema: `{type: 'object', properties: {...}, required: [...]}`.
 * @param {object} [values] The stored settings.
 * @returns {Array<object>} One field per property, in the schema's order, each with the value to show: the stored
 *   one, or the schema's default.
 */
export function fields(schema, values) {
    const required = schema?.required ?? [];

    return Object.entries(schema?.properties ?? {}).map(([name, property]) => {
        const definition = property ?? {};
        const stored = values?.[name];

        return {
            name,
            title: definition.title ?? name,
            description: definition.description ?? '',
            control: controlFor(definition),
            options: Array.isArray(definition.enum) ? definition.enum : [],
            integer: definition.type === 'integer',
            // What the schema suggests for the control, and the kind of value once "object" is set aside: a
            // responsive setting is "a string, or a map of strings", and its control edits the strings.
            hint: typeof definition['x-control'] === 'string' ? definition['x-control'] : '',
            // For a media setting, which kind of media the picker offers.
            mediaKind: typeof definition['x-media-kind'] === 'string' ? definition['x-media-kind'] : '',
            kind: [].concat(definition.type ?? []).find((type) => type !== 'object') ?? '',
            allowsText: Array.isArray(definition.type) && definition.type.includes('string'),
            required: required.includes(name),
            private: definition['x-private'] === true,
            value: stored === undefined ? definition.default : stored,
            isDefault: stored === undefined,
        };
    });
}

/**
 * Turns what a person typed into the value to store.
 *
 * @param {object} field A field from fields().
 * @param {string|boolean} raw The control's raw value.
 * @returns {*} The value, or undefined when the field was cleared and the setting should be removed.
 * @throws {FieldError} When the text cannot be that kind of value.
 */
export function parseField(field, raw) {
    if (field.control === 'checkbox') {
        return raw === true;
    }

    if (field.control === 'text' || field.control === 'select') {
        return raw;
    }

    const text = String(raw).trim();

    if (text === '') {
        return undefined;
    }

    if (field.control === 'number') {
        const number = Number(text);

        if (!Number.isFinite(number) || (field.integer && !Number.isInteger(number))) {
            throw new FieldError(field.name, field.integer ? 'Enter a whole number.' : 'Enter a number.');
        }

        return number;
    }

    try {
        return JSON.parse(text);
    } catch {
        // Where the schema allows text, what was typed is the text; nobody should have to quote a length.
        if (field.allowsText) {
            return text;
        }

        throw new FieldError(field.name, 'This is not valid JSON.');
    }
}

/**
 * Copies settings with one value set, or removed when the value is undefined.
 *
 * @param {object} settings
 * @param {string} path A setting's name, or a dotted path into it such as `rows.1.2`; only what lies along the path
 *   is copied, so the rest is kept exactly.
 * @param {*} value
 */
export function withValue(settings, path, value) {
    const [name, ...rest] = String(path).split('.');
    const next = Array.isArray(settings) ? [...settings] : { ...(settings ?? {}) };

    // A list never gets holes: positions before a new one are filled, so it stays a list when sent as JSON.
    if (Array.isArray(next) && /^\d+$/.test(name)) {
        for (let index = next.length; index < Number(name); index++) {
            next[index] = undefined;
        }
    }

    if (rest.length > 0) {
        const inner = next[name];
        const container = inner !== null && typeof inner === 'object' ? inner : /^\d+$/.test(rest[0]) ? [] : {};
        next[name] = withValue(container, rest.join('.'), value);

        return next;
    }

    if (value === undefined && !Array.isArray(next)) {
        delete next[name];
    } else {
        next[name] = value;
    }

    return next;
}

/**
 * Measures a table's stored rows, so the inspector can say how big it is.
 *
 * @param {Array<Array<string>>} [rows]
 * @returns {{rows: number, columns: number}} The number of rows, and the cells in the widest one.
 */
export function tableShape(rows) {
    const list = Array.isArray(rows) ? rows : [];

    return {
        rows: list.length,
        columns: list.reduce((widest, row) => Math.max(widest, Array.isArray(row) ? row.length : 0), 0),
    };
}

/**
 * Copies a table's rows with an empty row put in at a position, as wide as the widest row.
 *
 * @param {Array<Array<string>>} rows
 * @param {number} at
 */
export function withRow(rows, at) {
    const list = Array.isArray(rows) ? rows.map((row) => [...row]) : [];
    list.splice(at, 0, Array(Math.max(1, tableShape(list).columns)).fill(''));

    return list;
}

/**
 * Copies a table's rows without the row at a position; a table keeps at least one row.
 *
 * @param {Array<Array<string>>} rows
 * @param {number} at
 */
export function withoutRow(rows, at) {
    const list = rows.map((row) => [...row]);

    if (list.length > 1) {
        list.splice(at, 1);
    }

    return list;
}

/**
 * Copies a table's rows with an empty cell put in at a position in every row; short rows are padded first so the
 * new column lines up.
 *
 * @param {Array<Array<string>>} rows
 * @param {number} at
 */
export function withColumn(rows, at) {
    const list = Array.isArray(rows) && rows.length > 0 ? rows : [[]];
    const width = tableShape(list).columns;

    return list.map((row) => {
        const padded = [...row, ...Array(width - row.length).fill('')];
        padded.splice(at, 0, '');

        return padded;
    });
}

/**
 * Copies a table's rows without the cell at a position in each row; a table keeps at least one column.
 *
 * @param {Array<Array<string>>} rows
 * @param {number} at
 */
export function withoutColumn(rows, at) {
    if (tableShape(rows).columns <= 1) {
        return rows.map((row) => [...row]);
    }

    return rows.map((row) => row.filter((cell, index) => index !== at));
}

/**
 * Copies settings with what a person typed into one setting, or into one place inside it such as `rows.1.2`.
 *
 * A block added a moment ago stores no settings, yet the page shows the defaults, so typing into a place inside a
 * setting that is not stored starts from its default; otherwise one cell typed into a new table would replace the
 * whole table. Places skipped over in a list are filled with the empty value the schema expects there, so the result
 * is still valid.
 *
 * @param {object} settings The stored settings.
 * @param {object} [schema] The block type's settings schema.
 * @param {string} path
 * @param {*} value
 */
export function withTyped(settings, schema, path, value) {
    const [name, ...rest] = String(path).split('.');

    if (rest.length === 0) {
        return withValue(settings, name, value);
    }

    const property = schema?.properties?.[name] ?? {};
    const stored = settings?.[name];
    const start = stored === undefined && property.default !== undefined ? structuredClone(property.default) : stored;

    return withValue(settings, name, typedInto(start, property, rest, value));
}

function typedInto(container, schema, segments, value) {
    const [segment, ...rest] = segments;
    const inList = /^\d+$/.test(segment);
    const inner = inList ? schema?.items : (schema?.properties?.[segment] ?? schema?.additionalProperties);
    const next = inList
        ? (Array.isArray(container) ? [...container] : [])
        : (container !== null && typeof container === 'object' && !Array.isArray(container) ? { ...container } : {});

    if (inList) {
        for (let index = next.length; index < Number(segment); index++) {
            next[index] = emptyOf(inner);
        }
    }

    next[segment] = rest.length === 0 ? value : typedInto(next[segment], inner, rest, value);

    return next;
}

function emptyOf(schema) {
    const types = [].concat(schema?.type ?? []);

    if (types.includes('string')) {
        return '';
    }

    return types.includes('array') ? [] : types.includes('object') ? {} : null;
}

/**
 * Copies a list of text with an item put in at a position, such as a new feature of a plan.
 *
 * @param {Array<string>} [list]
 * @param {number} at
 * @param {string} [text]
 */
export function withListItem(list, at, text = '') {
    const items = Array.isArray(list) ? [...list] : [];
    items.splice(Math.max(0, Math.min(at, items.length)), 0, text);

    return items;
}

/**
 * Copies a list of text without the item at a position; the last item may go, leaving an empty list.
 *
 * @param {Array<string>} [list]
 * @param {number} at
 */
export function withoutListItem(list, at) {
    return Array.isArray(list) ? list.filter((item, index) => index !== at) : [];
}

/**
 * Copies a list of text with an item moved to another position; a move past either end changes nothing.
 *
 * @param {Array<string>} [list]
 * @param {number} from
 * @param {number} to
 */
export function movedListItem(list, from, to) {
    const items = Array.isArray(list) ? [...list] : [];

    if (from < 0 || from >= items.length || to < 0 || to >= items.length) {
        return items;
    }

    const [item] = items.splice(from, 1);
    items.splice(to, 0, item);

    return items;
}

/**
 * Copies a list of text with one item's text replaced; a position past the end changes nothing.
 *
 * @param {Array<string>} [list]
 * @param {number} at
 * @param {string} text
 */
export function withListText(list, at, text) {
    const items = Array.isArray(list) ? [...list] : [];

    if (at >= 0 && at < items.length) {
        items[at] = text;
    }

    return items;
}
