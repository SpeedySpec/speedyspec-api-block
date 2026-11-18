/**
 * Helpers for reading a document the way the API sends it: `{id, kind, blocks: [{id, children, ...}]}`.
 */

/**
 * Lists every block in document order.
 *
 * @returns {Array<{block: object, depth: number, parent: ?string, index: number}>}
 */
export function walk(document) {
    const entries = [];
    const visit = (blocks, depth, parent) => {
        blocks.forEach((block, index) => {
            entries.push({ block, depth, parent, index });
            visit(block.children ?? [], depth + 1, block.id);
        });
    };

    visit(document?.blocks ?? [], 0, null);

    return entries;
}

/**
 * Finds a block by id.
 *
 * @returns {?object} Null when the document has no such block.
 */
export function find(document, id) {
    return walk(document).find((entry) => entry.block.id === id)?.block ?? null;
}

/**
 * Tells where a block sits.
 *
 * @returns {?{parent: ?string, index: number}} Null when the document has no such block.
 */
export function positionOf(document, id) {
    const entry = walk(document).find((candidate) => candidate.block.id === id);

    return entry === undefined ? null : { parent: entry.parent, index: entry.index };
}

/**
 * Lists the blocks beside a position's parent: its children, or the document's top-level blocks.
 */
function siblings(document, parent) {
    return parent === null ? document.blocks : (find(document, parent)?.children ?? []);
}

/**
 * Works out where to move a block to shift it among its siblings.
 *
 * The API reads a move's position with the block already taken out, so one step down is simply the next index.
 *
 * @param {number} step -1 for up, 1 for down.
 * @returns {?{parent: ?string, index: number}} Null when the block is already at that edge, or is not there.
 */
export function movePosition(document, id, step) {
    const position = positionOf(document, id);

    if (position === null) {
        return null;
    }

    const index = position.index + step;

    if (index < 0 || index >= siblings(document, position.parent).length) {
        return null;
    }

    return { parent: position.parent, index };
}

/**
 * Works out where to move a block to put it inside the block before it, as its last child.
 *
 * @returns {?{parent: string, index: number}} Null when there is no block before it.
 */
export function indentPosition(document, id) {
    const position = positionOf(document, id);

    if (position === null || position.index === 0) {
        return null;
    }

    const before = siblings(document, position.parent)[position.index - 1];

    return { parent: before.id, index: (before.children ?? []).length };
}

/**
 * Works out where to move a block to take it out of its parent, to just after that parent.
 *
 * @returns {?{parent: ?string, index: number}} Null when the block is already at the top level.
 */
export function outdentPosition(document, id) {
    const position = positionOf(document, id);

    if (position === null || position.parent === null) {
        return null;
    }

    const parent = positionOf(document, position.parent);

    return { parent: parent.parent, index: parent.index + 1 };
}
