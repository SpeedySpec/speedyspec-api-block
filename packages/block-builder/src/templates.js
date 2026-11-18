/**
 * The library the server keeps saved templates in; only its templates can be removed.
 */
export const SAVED_LIBRARY = 'core/saved';

const PREFIX = 'saved/';
const MAX = 64;

/**
 * Makes the name a template is saved under from its title, so people only ever have to give a title.
 *
 * The server's names are `vendor/name`, each part starting with a letter, so the title is reduced to lower-case
 * letters, digits and hyphens, with `t-` in front when it would start with anything else.
 *
 * @returns {?string} Null when nothing usable is left of the title.
 */
export function templateName(title) {
    let slug = String(title)
        .normalize('NFKD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    if (slug === '') {
        return null;
    }

    if (!/^[a-z]/.test(slug)) {
        slug = `t-${slug}`;
    }

    return PREFIX + slug.slice(0, MAX).replace(/-+$/, '');
}

/**
 * Keeps the templates that match what the person typed, by library, dropping libraries with nothing left.
 *
 * @param {Array<{name: string, title: string, templates: Array<object>}>} libraries
 * @returns {Array<{name: string, title: string, templates: Array<object>}>}
 */
export function matchTemplates(libraries, query) {
    const wanted = query.trim().toLowerCase();
    const has = (text) => String(text ?? '').toLowerCase().includes(wanted);

    return libraries
        .map((library) => ({
            ...library,
            templates: wanted === '' || has(library.title)
                ? library.templates
                : library.templates.filter((template) =>
                      has(template.title) || has(template.description) || has(template.category)),
        }))
        .filter((library) => library.templates.length > 0);
}
