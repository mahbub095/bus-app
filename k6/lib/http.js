/** Low-level HTTP helpers used by API clients */
import { check } from 'k6';

export function parseJson(body, fallback = null) {
    try {
        return JSON.parse(body);
    } catch {
        return fallback;
    }
}

export function jsonHeaders(token = null) {
    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    return headers;
}

export function ajaxHeaders() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export function formHeaders(csrfToken, extra = {}) {
    return {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json, text/html, */*',
        'X-CSRF-TOKEN': csrfToken,
        ...extra,
    };
}

/** Run k6 checks and return parsed JSON body */
export function checkJson(res, label, checks = {}, fallback = null) {
    check(res, {
        [`${label} status ok`]: (r) => r.status >= 200 && r.status < 300,
        ...checks,
    });
    return parseJson(res.body, fallback);
}

/** Extract CSRF token from Laravel login HTML */
export function csrfFromHtml(res) {
    const doc = res.html();
    return (
        doc.find('meta[name="csrf-token"]').attr('content') ||
        doc.find('input[name="_token"]').attr('value') ||
        null
    );
}
