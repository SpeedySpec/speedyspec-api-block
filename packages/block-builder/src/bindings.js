/**
 * Helpers for binding a block's settings to the data sources the server lists in its vocabulary.
 *
 * Only a block's own top-level settings are offered. The server checks every binding against the source and the
 * setting it feeds, so nothing here decides whether a binding fits.
 */

/**
 * Names the settings of a schema that may be bound: all of them except private ones, which never reach the page.
 *
 * @returns {string[]}
 */
export function bindable(schema) {
    return Object.entries(schema?.properties ?? {})
        .filter(([, property]) => property?.['x-private'] !== true)
        .map(([name]) => name);
}

/**
 * Reads the binding on one of a block's settings.
 *
 * @returns {?{source: string, arguments: object, fallback?: *}} Null when the setting is not bound.
 */
export function bindingOf(block, setting) {
    return block?.bindings?.[setting] ?? null;
}

/**
 * Says in words where a bound setting's value comes from, shown in place of its control.
 *
 * @param {Array<{name: string}>} sources The vocabulary's sources.
 */
export function describeBinding(binding, sources) {
    if (!sources.some((source) => source.name === binding.source)) {
        return `From a source this site no longer has (${binding.source})`;
    }

    const pairs = Object.entries(binding.arguments ?? {}).map(([name, value]) => `${name}: ${value}`);

    return pairs.length === 0 ? `From ${binding.source}` : `From ${binding.source} (${pairs.join(', ')})`;
}

/**
 * Builds the binding the API takes from what the binding form holds.
 *
 * An argument left empty is left out, so the source's own default applies, rather than sending an empty string the
 * source might not accept. The fallback is sent only when asked for.
 *
 * @param {{source: string, arguments: object, useFallback: boolean, fallback: *}} draft
 */
export function bindingFrom(draft) {
    const binding = {
        source: draft.source,
        arguments: Object.fromEntries(Object.entries(draft.arguments ?? {}).filter(([, value]) => value !== '')),
    };

    if (draft.useFallback) {
        binding.fallback = draft.fallback;
    }

    return binding;
}
