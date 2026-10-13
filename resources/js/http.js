/**
 * Small JSON client for in-page (AJAX) actions.
 *
 * Always sends the current CSRF token and asks Laravel for JSON, so
 * validation failures come back as { message, errors } instead of a redirect.
 */
export class HttpError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.errors = errors;
    }

    /** First message for a field, or for the whole request. */
    first(field = null) {
        if (field && this.errors[field]) {
            return this.errors[field][0];
        }

        const all = Object.values(this.errors).flat();

        return all[0] ?? this.message;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function request(url, { method = 'GET', data = null, signal } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = csrfToken();
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            headers,
            body: data && method !== 'GET' ? JSON.stringify(data) : undefined,
            credentials: 'same-origin',
            signal,
        });
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }

        throw new HttpError('No connection to the server. Check the network and try again.', 0);
    }

    const body = (response.headers.get('Content-Type') ?? '').includes('application/json') ? await response.json() : {};

    if (response.ok) {
        return body;
    }

    const fallback = {
        401: 'Your session ended. Please log in again.',
        403: 'You are not allowed to do this.',
        419: 'This page expired. Reload the page and try again.',
        422: 'Please check the highlighted fields.',
    }[response.status] ?? 'Something went wrong on the server. Please try again.';

    throw new HttpError(body.message || fallback, response.status, body.errors ?? {});
}

export const http = {
    get: (url, options = {}) => request(url, { ...options, method: 'GET' }),
    post: (url, data, options = {}) => request(url, { ...options, method: 'POST', data }),
};
