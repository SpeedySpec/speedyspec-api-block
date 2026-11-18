/**
 * The arithmetic behind the interactive blocks' script, kept apart from the page so it can be tested alone.
 */

/**
 * Reads a number the page gave, kept within its bounds; anything that is not a number gives the fallback.
 *
 * @param {*} value
 * @param {number} min
 * @param {number} max
 * @param {number} fallback
 */
export function clamp(value, min, max, fallback) {
    const number = typeof value === 'string' && value.trim() === '' ? NaN : Number(value);

    return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : fallback;
}

/**
 * Says which tab a key moves to, wrapping at the ends, as the WAI-ARIA tabs pattern asks.
 *
 * @param {number} index The tab with focus.
 * @param {number} count How many tabs there are.
 * @param {string} key The key pressed.
 * @returns {?number} The tab to move to, or null for a key that does not move.
 */
export function nextTab(index, count, key) {
    switch (key) {
        case 'ArrowRight':
            return (index + 1) % count;
        case 'ArrowLeft':
            return (index - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

/**
 * Splits the time until a moment into days, hours, minutes and seconds, never below zero.
 *
 * @param {number} targetMs
 * @param {number} nowMs
 */
export function remaining(targetMs, nowMs) {
    const left = Math.max(0, Math.floor((targetMs - nowMs) / 1000));

    return {
        days: Math.floor(left / 86_400),
        hours: Math.floor((left % 86_400) / 3600),
        minutes: Math.floor((left % 3600) / 60),
        seconds: left % 60,
        ended: left === 0,
    };
}

/**
 * Gives a counter's value part way through its count, slowing as it nears the end, and exactly the end once done.
 *
 * @param {number} from
 * @param {number} to
 * @param {number} elapsedMs
 * @param {number} durationMs
 */
export function countAt(from, to, elapsedMs, durationMs) {
    if (durationMs <= 0 || elapsedMs >= durationMs) {
        return to;
    }

    const t = Math.max(0, elapsedMs / durationMs);

    return from + (to - from) * (1 - (1 - t) ** 3);
}

/**
 * Writes a number as the page does, with grouping and a fixed number of decimals.
 *
 * @param {number} value
 * @param {number} decimals
 * @param {string} prefix
 * @param {string} suffix
 */
export function formatNumber(value, decimals, prefix, suffix) {
    const digits = value.toLocaleString('en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    return `${prefix}${digits}${suffix}`;
}

/**
 * Names a tab by its label, or by its place when the label is empty, so no tab is left without a name.
 *
 * @param {?string} text
 * @param {number} index Counted from 0.
 */
export function tabLabel(text, index) {
    const label = String(text ?? '').trim();

    return label === '' ? `Tab ${index + 1}` : label;
}

/**
 * Names a slide that has no name of its own by its place.
 *
 * @param {number} index Counted from 0.
 * @param {number} count
 */
export function slideLabel(index, count) {
    return `Slide ${index + 1} of ${count}`;
}
