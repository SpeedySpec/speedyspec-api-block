/**
 * What typing on the page needs that does not depend on a page: tidying what the browser wrote, deciding what a key
 * does, and checking a link. Strings and plain objects in, strings out, so it is tested without a browser.
 */

const ALLOWED = new Set(['strong', 'em', 's', 'code', 'sub', 'sup', 'mark', 'a']);
const RENAMED = { b: 'strong', i: 'em', strike: 's', del: 's' };
const BLOCKS = new Set(['div', 'p']);

/**
 * Turns what a browser's editing produced into the allowed formatted-text subset, as closely as a client can.
 *
 * Browsers write `<b>` for bold, wrap new lines in `<div>`, and copy styling in with pasted text. The server cleans
 * whatever arrives; tidying first means what is stored is what the person saw, not what the server had to strip.
 *
 * @param {string} html
 * @returns {string}
 */
export function tidyTyped(html) {
    const tidied = String(html)
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>/g, (whole, closing, rawName, attributes) => {
            const lower = rawName.toLowerCase();
            const name = RENAMED[lower] ?? lower;

            if (name === 'br') {
                return '<br>';
            }

            // A new block-level line is a line break; its closing tag adds nothing.
            if (BLOCKS.has(name)) {
                return closing === '' ? '<br>' : '';
            }

            if (!ALLOWED.has(name)) {
                return '';
            }

            if (closing !== '') {
                return `</${name}>`;
            }

            if (name === 'a') {
                const href = /\bhref\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/i.exec(attributes);
                const value = href === null ? '' : (href[2] ?? href[3] ?? href[4] ?? '');

                return value === '' ? '<a>' : `<a href="${value.replaceAll('"', '&quot;')}">`;
            }

            return `<${name}>`;
        })
        .replace(/&nbsp;| /g, ' ');

    return tidied.replace(/^(?:<br>)+/, '').replace(/(?:<br>)+$/, '');
}

/**
 * Decides what a key does while a person is typing in a block.
 *
 * @param {{key: string, shiftKey?: boolean, metaKey?: boolean, ctrlKey?: boolean, empty?: boolean,
 *   atStart?: boolean, plain?: boolean}} press `empty` and `atStart` describe the element and the cursor; `plain`
 *   is set for plain text such as code, where Enter is a new line.
 * @returns {'commit-split'|'line-break'|'remove'|'stop'|null} Null to let the browser do what it would.
 */
export function keyAction({ key, shiftKey = false, metaKey = false, ctrlKey = false, empty = false, atStart = false, plain = false }) {
    if (metaKey || ctrlKey) {
        return null;
    }

    if (key === 'Enter') {
        return shiftKey || plain ? 'line-break' : 'commit-split';
    }

    if (key === 'Backspace') {
        return empty && atStart ? 'remove' : null;
    }

    return key === 'Escape' ? 'stop' : null;
}

/**
 * Checks a link a person typed, with the same rules the server keeps links by.
 *
 * A bare domain, such as `example.com`, is taken to mean `https://`.
 *
 * @param {string} text
 * @returns {?string} The link to use, or null when it cannot be used.
 */
export function safeLink(text) {
    const link = String(text).trim();

    if (link === '') {
        return null;
    }

    if (link.startsWith('/')) {
        // Browsers read `//` and `/\` as the start of another site's address.
        return link[1] === '/' || link[1] === '\\' ? null : link;
    }

    if (link.startsWith('#')) {
        return link;
    }

    const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(link);

    if (scheme !== null) {
        return ['http', 'https', 'mailto', 'tel'].includes(scheme[1].toLowerCase()) ? link : null;
    }

    return /^[a-z0-9][a-z0-9-]*(\.[a-z0-9-]+)+([/?#]\S*)?$/i.test(link) ? `https://${link}` : null;
}

/**
 * Finds the schema of one setting by its path, so the editor knows whether what is typed there is formatted text.
 *
 * @param {object} [schema] A block type's settings schema.
 * @param {string} path A setting's name or a dotted path such as `rows.1.2`.
 * @returns {?object} Null when the schema says nothing about that path.
 */
export function settingSchema(schema, path) {
    let current = schema ?? null;

    for (const segment of String(path).split('.')) {
        if (current === null || typeof current !== 'object') {
            return null;
        }

        const properties = current.properties ?? {};
        current = segment in properties
            ? properties[segment]
            : /^\d+$/.test(segment) && current.items !== undefined
              ? current.items
              : typeof current.additionalProperties === 'object'
                ? current.additionalProperties
                : null;
    }

    return current ?? null;
}
