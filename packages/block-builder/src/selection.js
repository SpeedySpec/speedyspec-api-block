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

/**
 * The extension a block's lock is kept in; the server refuses moving or removing a block it locks.
 */
export const LOCK_NS = 'core/lock';

/**
 * Reads a block's lock the way the server does, so the editor turns off what the server would refuse.
 *
 * @returns {{move: boolean, remove: boolean}}
 */
export function lockOf(block) {
    const lock = block?.extensions?.[LOCK_NS] ?? {};

    return { move: lock.move === true, remove: lock.remove === true };
}
