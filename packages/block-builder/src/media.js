/**
 * Working with media settings: what to ask the library for, and changing a list of chosen ids.
 *
 * Settings store ids only, so nothing here touches files; the picker in the interface uses these.
 */

const PER_PAGE = 24;
const ALL = 'image/*,video/*,audio/*,application/pdf';

/**
 * Builds what the client's `media()` takes for one page of the picker.
 *
 * @param {string} kind The setting's `x-media-kind`, or empty for every kind.
 * @param {string} search What the person typed; spaces around it are ignored.
 * @param {number} page Counted from 1.
 * @returns {{kind?: string, search?: string, page: number, perPage: number}}
 */
export function mediaQuery(kind, search, page) {
    const query = {};
    const text = String(search ?? '').trim();

    if (kind) {
        query.kind = kind;
    }

    if (text !== '') {
        query.search = text;
    }

    return { ...query, page, perPage: PER_PAGE };
}

/**
 * Copies a list of ids with one added at the end, unless it is already there.
 *
 * @param {Array<string>} [list]
 * @param {string} id
 */
export function withMedia(list, id) {
    const ids = Array.isArray(list) ? [...list] : [];

    return ids.includes(id) ? ids : [...ids, id];
}

/**
 * Copies a list of ids without the one at a position.
 *
 * @param {Array<string>} list
 * @param {number} index
 */
export function withoutMediaAt(list, index) {
    return list.filter((id, at) => at !== index);
}

/**
 * Copies a list of ids with one moved to another position; a move past either end leaves the order as it was.
 *
 * @param {Array<string>} list
 * @param {number} from
 * @param {number} to
 */
export function movedMedia(list, from, to) {
    const ids = [...list];

    if (from < 0 || from >= ids.length || to < 0 || to >= ids.length) {
        return ids;
    }

    const [id] = ids.splice(from, 1);
    ids.splice(to, 0, id);

    return ids;
}

/**
 * Says which files the file chooser should offer for a kind of media; the server checks again by content.
 *
 * @param {string} kind
 */
export function accepts(kind) {
    return kind === 'image' || kind === 'video' || kind === 'audio' ? `${kind}/*` : ALL;
}
