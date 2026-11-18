/**
 * Helpers for the site styles screen, where people change the design tokens and breakpoints every page uses.
 *
 * The checks here only save a trip to the server for a mistake it would refuse anyway; the server stays the
 * authority, and its reason is shown when it refuses something these let through.
 */

const TOKEN_NAME = /^[a-z][a-z0-9-]*(\.[a-z0-9-]+)*$/;

const words = (name) => {
    const spaced = name.replace(/-/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
};

/**
 * Groups tokens by the part of their name before the first dot, so colours sit together, then spaces, and so on.
 *
 * @param {Record<string, string>} tokens
 * @returns {Array<{title: string, tokens: Array<{name: string, value: string}>}>} Groups by name, with names that
 *   have no dot last, under "Other".
 */
export function tokenGroups(tokens) {
    const groups = new Map();

    for (const name of Object.keys(tokens ?? {}).sort()) {
        const dot = name.indexOf('.');
        const key = dot === -1 ? null : name.slice(0, dot);

        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key).push({ name, value: tokens[name] });
    }

    const named = [...groups.keys()].filter((key) => key !== null).sort();
    const ordered = named.map((key) => ({ title: words(key), tokens: groups.get(key) }));

    return groups.has(null) ? [...ordered, { title: 'Other', tokens: groups.get(null) }] : ordered;
}

/**
 * Tells whether a token holds a colour, so the screen can offer a colour picker beside its text.
 */
export function isColourToken(name) {
    return name.startsWith('color.');
}

/**
 * @returns {?string} What is wrong with a token name, or null when the server would accept it.
 */
export function tokenNameProblem(name) {
    return TOKEN_NAME.test(name)
        ? null
        : 'Use lower-case letters, digits and hyphens, with dots between parts, such as color.accent.';
}

/**
 * @returns {?string} What is wrong with a typed width, or null when it is a whole number of pixels, 1 or more.
 */
export function widthProblem(raw) {
    const text = String(raw).trim();

    return /^[1-9][0-9]*$/.test(text) ? null : 'Use a whole number of pixels, 1 or more.';
}
