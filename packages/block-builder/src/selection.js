import { positionOf, walk } from './tree.js';

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

/**
 * Tells whether removing a block would be refused: the server refuses when the block, or any block inside it, is
 * locked against removal, so the editor checks the same subtree before offering Remove.
 */
export function lockedAgainstRemoval(block) {
    return lockOf(block).remove || (block?.children ?? []).some((child) => lockedAgainstRemoval(child));
}

/**
 * Adds a block to the selection, or takes it out when it is already there.
 *
 * A selection holds siblings only, so what is done to it (copying, removing, locking) acts on blocks side by side.
 * A block beside none of those selected starts a new selection.
 *
 * @param {string[]} selection
 * @returns {string[]} In document order.
 */
export function toggleInSelection(document, selection, id) {
    if (selection.includes(id)) {
        return selection.filter((selected) => selected !== id);
    }

    const parentOf = (blockId) => positionOf(document, blockId)?.parent;
    const next = selection.length === 0 || parentOf(selection[0]) === parentOf(id) ? [...selection, id] : [id];

    return pick(document, next).map((block) => block.id);
}
