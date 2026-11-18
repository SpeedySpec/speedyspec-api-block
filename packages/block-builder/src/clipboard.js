import { positionOf } from './tree.js';
import { pick } from './selection.js';

const TYPE = 'speedyspec/blocks';
const FORMAT = 1;

/**
 * Writes blocks as clipboard text, so they can be pasted into this document, another one, or another tab.
 *
 * The text is marked with its own type and format, so text copied anywhere else is never taken for blocks.
 *
 * @param {Array<object>} blocks As the document export gives them.
 */
export function clipboardText(blocks) {
    return JSON.stringify({ type: TYPE, format: FORMAT, blocks });
}

/**
 * Reads blocks back from clipboard text.
 *
 * Only the exact shape {@link clipboardText} writes is read. Anything else is someone else's text, and the browser's
 * own paste should go ahead with it. The server still checks every block that is pasted.
 *
 * @returns {?Array<object>} Null when the text is not blocks.
 */
export function readClipboard(text) {
    let data;

    try {
        data = JSON.parse(text);
    } catch {
        return null;
    }

    if (data === null || typeof data !== 'object' || data.type !== TYPE || data.format !== FORMAT) {
        return null;
    }

    const { blocks } = data;
    const isBlock = (candidate) => candidate !== null && typeof candidate === 'object'
        && !Array.isArray(candidate) && typeof candidate.type === 'string';

    return Array.isArray(blocks) && blocks.length > 0 && blocks.every(isBlock) ? blocks : null;
}

/**
 * Works out where pasted blocks go: right after the last of the given blocks, or at the end of the page.
 *
 * A block inside another of the given blocks does not count, so pasting after a section and one of its own
 * children lands after the section.
 *
 * @param {string[]} ids Usually the selection.
 * @returns {{parent: ?string, index: number}}
 */
export function pastePosition(document, ids) {
    const last = pick(document, ids).at(-1);
    const here = last === undefined ? null : positionOf(document, last.id);

    return here === null
        ? { parent: null, index: document?.blocks?.length ?? 0 }
        : { parent: here.parent, index: here.index + 1 };
}
