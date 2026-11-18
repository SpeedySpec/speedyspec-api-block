/**
 * A problem reported by the builder API, or a request that never reached it.
 */
export class ApiError extends Error {
    /**
     * @param {number} status The HTTP status; 0 when the server could not be reached.
     * @param {string} message What the server said, safe to show.
     */
    constructor(status, message) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

/**
 * An edit the server refused, with every reason, so they can all be shown at once.
 */
export class Refused extends ApiError {
    /**
     * @param {Array<{kind: string, message: string, block: ?string, path: ?string}>} refusals
     */
    constructor(refusals) {
        super(422, refusals.map((refusal) => refusal.message).join(' '));
        this.name = 'Refused';
        this.refusals = refusals;
    }
}

/**
 * Builds a client for the builder API.
 *
 * It only knows the API's routes. Signing in, and whatever the host needs on each request to tell who is asking
 * (a token, a request-forgery header), are passed in as headers.
 *
 * @param {{baseUrl: string, fetch?: typeof fetch, headers?: Record<string, string>}} options
 */
export function createClient({ baseUrl, fetch = globalThis.fetch, headers = {} }) {
    const base = baseUrl.replace(/\/+$/, '');
    const segment = encodeURIComponent;

    /**
     * Sends one request and gives back the decoded body.
     *
     * @param {*} [body] Sent as JSON, unless `raw` is set.
     * @param {?string} [raw] The body's own type: the body is sent as it is, such as a file being uploaded.
     */
    async function send(method, path, body, raw = null) {
        let response;

        try {
            response = await fetch(base + path, {
                method,
                headers: body === undefined
                    ? { ...headers }
                    : { ...headers, 'Content-Type': raw ?? 'application/json' },
                body: body === undefined || raw !== null ? body : JSON.stringify(body),
            });
        } catch {
            throw new ApiError(0, 'The server could not be reached.');
        }

        const text = await response.text();
        let data = {};

        try {
            data = text === '' ? {} : JSON.parse(text);
        } catch {
            data = {};
        }

        if (response.status === 422 && Array.isArray(data.refusals)) {
            throw new Refused(data.refusals);
        }

        if (!response.ok) {
            throw new ApiError(response.status, data.error ?? `The request failed (${response.status}).`);
        }

        return data;
    }

    return {
        /**
         * Lists block types, and the packages that defined them; with a position, only those that may go there.
         *
         * @param {{document: string, parent: ?string, index: number}} [position]
         */
        async catalogue(position) {
            const query = new URLSearchParams();

            if (position !== undefined) {
                query.set('document', position.document);

                if (position.parent !== null && position.parent !== undefined) {
                    query.set('parent', position.parent);
                }

                query.set('index', String(position.index));
            }

            const suffix = query.size === 0 ? '' : `?${query}`;

            const answer = await send('GET', `/catalogue${suffix}`);

            return { packages: answer.packages ?? [], blocks: answer.blocks ?? [] };
        },

        vocabulary: () => send('GET', '/vocabulary'),

        documents: async () => (await send('GET', '/documents')).documents,

        createDocument: (id, kind) => send('POST', '/documents', { id, kind }),

        document: (id) => send('GET', `/documents/${segment(id)}`),

        deleteDocument: (id) => send('DELETE', `/documents/${segment(id)}`),

        /**
         * Runs one editing operation.
         *
         * @throws {Refused} When the server refuses the edit.
         */
        operate: (id, operation) => send('POST', `/documents/${segment(id)}/operations`, operation),

        preview: (id, block, mode = 'admin') => send('POST', `/documents/${segment(id)}/preview`, { block, mode }),

        render: (id, mode = 'front') => send('GET', `/documents/${segment(id)}/render?mode=${segment(mode)}`),

        /**
         * Lists the template libraries, and whether the person may save and remove templates.
         *
         * @returns {Promise<{libraries: Array<object>, manage: boolean}>}
         */
        async templates() {
            const answer = await send('GET', '/templates');

            return { libraries: answer.libraries ?? [], manage: answer.manage === true };
        },

        saveTemplate: (template) => send('POST', '/templates', template),

        // A template name is `vendor/name`, and the route takes the two parts as two segments.
        removeTemplate: (name) => send('DELETE', `/templates/${name.split('/').map(segment).join('/')}`),

        /**
         * Sets one design token.
         *
         * @returns {Promise<Record<string, string>>} Every token, as they now are.
         */
        setToken: async (name, value) => (await send('PUT', `/tokens/${segment(name)}`, { value })).tokens,

        removeToken: async (name) => (await send('DELETE', `/tokens/${segment(name)}`)).tokens,

        /**
         * Sets one breakpoint.
         *
         * @returns {Promise<Array<{name: string, minWidth: number}>>} Every breakpoint, as they now are.
         */
        setBreakpoint: async (name, minWidth) =>
            (await send('PUT', `/breakpoints/${segment(name)}`, { minWidth })).breakpoints,

        removeBreakpoint: async (name) => (await send('DELETE', `/breakpoints/${segment(name)}`)).breakpoints,

        /**
         * Lists one page of the media library, newest first.
         *
         * @param {{search?: string, kind?: string, page?: number, perPage?: number}} [filters]
         * @returns {Promise<{items: Array<object>, total: number, page: number, perPage: number}>}
         */
        media(filters = {}) {
            const query = new URLSearchParams();

            for (const key of ['search', 'kind', 'page', 'perPage']) {
                if (filters[key] !== undefined && filters[key] !== '') {
                    query.set(key, String(filters[key]));
                }
            }

            return send('GET', `/media${query.size === 0 ? '' : `?${query}`}`);
        },

        /**
         * Describes one media item.
         */
        mediaItem: async (id) => (await send('GET', `/media/${segment(id)}`)).item,

        /**
         * Uploads a file to the media library; the server decides what it is from its content.
         *
         * @param {File} file
         */
        upload: async (file) => (await send(
            'POST',
            `/media?${new URLSearchParams({ name: file.name })}`,
            file,
            file.type || 'application/octet-stream',
        )).item,
    };
}
