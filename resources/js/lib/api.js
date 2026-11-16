const TOKEN_KEY = 'bmh_token';

export const tokenStore = {
    get: () => { try { return localStorage.getItem(TOKEN_KEY); } catch { return null; } },
    set: (t) => { try { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); } catch { /* private mode */ } },
};

export class ApiError extends Error {
    constructor(message, status, errors = {}, data = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
        this.data = data;
    }
}

/** Converts a plain object (with File / arrays / nested objects) to FormData using Laravel's bracket notation. */
export function toFormData(obj, form = new FormData(), prefix = '') {
    Object.entries(obj).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        const name = prefix ? `${prefix}[${key}]` : key;
        if (value instanceof File || value instanceof Blob) form.append(name, value);
        else if (Array.isArray(value)) value.forEach((v, i) => (typeof v === 'object' && !(v instanceof File) ? toFormData(v, form, `${name}[${i}]`) : form.append(`${name}[${i}]`, v instanceof File ? v : (typeof v === 'boolean' ? (v ? '1' : '0') : v))));
        else if (typeof value === 'object') toFormData(value, form, name);
        else form.append(name, typeof value === 'boolean' ? (value ? '1' : '0') : value);
    });
    return form;
}

export async function api(path, { method = 'GET', body, params, form = false, signal } = {}) {
    const url = new URL(`/api/v1${path}`, window.location.origin);
    if (params) Object.entries(params).forEach(([k, v]) => v !== undefined && v !== null && v !== '' && url.searchParams.set(k, v));

    const headers = { Accept: 'application/json' };
    const token = tokenStore.get();
    if (token) headers.Authorization = `Bearer ${token}`;
    let payload;
    if (body !== undefined) {
        if (form) payload = body instanceof FormData ? body : toFormData(body);
        else { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    }

    let res;
    try {
        res = await fetch(url, { method, headers, body: payload, signal });
    } catch (e) {
        if (e.name === 'AbortError') throw e;
        // A network-level failure: no HTTP response reached the browser.
        const msg = navigator.onLine === false
            ? 'You appear to be offline. Check your connection and try again.'
            : `Couldn't reach the server (${url.pathname}). If this keeps happening, the site's API isn't responding — please contact support.`;
        throw new ApiError(msg, 0);
    }
    const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
    if (!res.ok) {
        if (res.status === 401) { tokenStore.set(null); window.dispatchEvent(new Event('bmh:unauthorized')); }
        if (res.status === 403 && data.code === 'account_suspended') { tokenStore.set(null); window.dispatchEvent(new Event('bmh:unauthorized')); }
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new ApiError(first || data.message || 'Something went wrong. Please try again.', res.status, data.errors || {}, data);
    }
    return data;
}

export const get = (p, params) => api(p, { params });
export const post = (p, body, opts = {}) => api(p, { method: 'POST', body, ...opts });
export const put = (p, body) => api(p, { method: 'PUT', body });
export const del = (p) => api(p, { method: 'DELETE' });
