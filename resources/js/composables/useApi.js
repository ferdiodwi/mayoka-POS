/**
 * Shared API helper for making authenticated requests with CSRF token.
 */
function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(url, options = {}) {
    const defaults = {
        headers: {
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
    };

    const res = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...options.headers } });
    const data = await res.json();

    if (!res.ok) {
        const msg = data.errors
            ? Object.values(data.errors).flat().join(', ')
            : data.message || 'Terjadi kesalahan.';
        throw new Error(msg);
    }

    return data;
}

export async function apiGet(url) {
    return apiFetch(url);
}

export async function apiPost(url, body) {
    return apiFetch(url, { method: 'POST', body: JSON.stringify(body) });
}

export async function apiPut(url, body) {
    return apiFetch(url, { method: 'PUT', body: JSON.stringify(body) });
}

export async function apiDelete(url) {
    return apiFetch(url, { method: 'DELETE' });
}

export async function apiPatch(url, body = {}) {
    return apiFetch(url, { method: 'PATCH', body: JSON.stringify(body) });
}
