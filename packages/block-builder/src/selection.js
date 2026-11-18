import { walk } from './tree.js';

/**
 * Gives the blocks with the given ids, in document order.
 *
 * A block inside another picked block is left out: its ancestor already carries it, and copying or removing it
 * twice would duplicate it or refuse the edit.
 *
 * @param {string[]} ids Ids not in the document are ignored.
 * @returns {Array<object>}
 */
export function pick(document, ids) {
    const wanted = new Set(ids);
    const picked = [];
    const inside = new Set();

    for (const { block, parent } of walk(document)) {
        if (parent !== null && inside.has(parent)) {
            inside.add(block.id);

            continue;
        }

        if (wanted.has(block.id)) {
            picked.push(block);
            inside.add(block.id);
        }
    }

    return picked;
}
