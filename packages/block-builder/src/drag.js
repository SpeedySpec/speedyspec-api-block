/**
 * Where a dragged block lands, worked out from the document alone so it can be tested without an interface.
 */
import { find, positionOf, walk } from './tree.js';

/**
 * Decides which part of a block the pointer is over.
 *
 * A block that can hold others is split in three: its edges mean before and after, its middle means inside. One
 * that cannot is split in two.
 *
 * @param {number} offset How far the pointer is from the block's leading edge.
 * @param {number} size The block's size along the same axis.
 * @param {boolean} holds Whether the block can hold other blocks.
 * @returns {'before'|'inside'|'after'}
 */
export function dropZone(offset, size, holds) {
    if (size <= 0) {
        return holds ? 'inside' : 'after';
    }

    const share = offset / size;

    if (holds) {
        return share < 0.25 ? 'before' : share > 0.75 ? 'after' : 'inside';
    }

    return share < 0.5 ? 'before' : 'after';
}

/**
 * Works out the position to send for a drop.
 *
 * @param {object} document The document as the API sends it.
 * @param {?string} draggedId The block being moved; null when a new block is being added.
 * @param {?string} targetId The block dropped on; null for the page itself, which means "at the end".
 * @param {'before'|'inside'|'after'} zone The part of the target the pointer was over.
 * @returns {?{parent: ?string, index: number}} Null when the drop means nothing: onto the block itself or
 *   something inside it, where it already is, or onto a block that is not there.
 */
export function dropPosition(document, draggedId, targetId, zone) {
    const dragged = draggedId === null ? null : positionOf(document, draggedId);

    if (draggedId !== null && dragged === null) {
        return null;
    }

    let parent = null;
    let index = document.blocks.length;

    if (targetId !== null) {
        const target = find(document, targetId);
        const at = positionOf(document, targetId);

        if (target === null) {
            return null;
        }

        if (zone === 'inside') {
            parent = targetId;
            index = (target.children ?? []).length;
        } else {
            parent = at.parent;
            index = at.index + (zone === 'after' ? 1 : 0);
        }
    }

    if (draggedId === null) {
        return { parent, index };
    }

    const inside = new Set(walk({ blocks: [find(document, draggedId)] }).map((entry) => entry.block.id));

    if (inside.has(targetId) || inside.has(parent)) {
        return null;
    }

    // The API reads a move's position with the block already taken out, so a gap before the landing place
    // shifts it down by one.
    if (parent === dragged.parent && dragged.index < index) {
        index -= 1;
    }

    return parent === dragged.parent && index === dragged.index ? null : { parent, index };
}
