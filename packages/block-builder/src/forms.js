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
 */
export function withValue(settings, name, value) {
    const next = { ...settings };

    if (value === undefined) {
        delete next[name];
    } else {
        next[name] = value;
    }

    return next;
}
