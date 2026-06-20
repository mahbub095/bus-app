/** Admin Blade dashboard API (session + CSRF, /admin/api/*) */
import http from 'k6/http';
import { check } from 'k6';
import { ajaxHeaders, csrfFromHtml, formHeaders, parseJson } from './http.js';

export class AdminApi {
    constructor(baseUrl) {
        this.base = baseUrl;
    }

    // --- Authentication ---

    /** Load login page and return CSRF token */
    fetchLoginCsrf() {
        const res = http.get(`${this.base}/admin/login`);
        if (!check(res, { 'admin login page → 200': (r) => r.status === 200 })) {
            return null;
        }
        const token = csrfFromHtml(res);
        check(token, { 'login page has CSRF': (t) => !!t });
        return token;
    }

    /** Submit login form; returns dashboard CSRF token on success */
    login(email, password, csrfToken) {
        const res = http.post(
            `${this.base}/admin/login`,
            { _token: csrfToken, email, password },
            {
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'text/html' },
                redirects: 5,
            }
        );
        const ok = check(res, {
            'admin login → 200': (r) => r.status === 200,
            'lands on dashboard': (r) => r.body.includes('Dashboard') || r.body.includes('Admin'),
        });
        return ok ? csrfFromHtml(res) || csrfToken : null;
    }

    viewDashboard() {
        const res = http.get(`${this.base}/admin`);
        check(res, {
            'dashboard → 200': (r) => r.status === 200,
            'dashboard content': (r) => r.body.includes('Dashboard'),
        });
        return res;
    }

    // --- AJAX helpers ---

    getJson(path, extraChecks = {}) {
        const res = http.get(`${this.base}${path}`, { headers: ajaxHeaders() });
        check(res, { [`GET ${path} → 200`]: (r) => r.status === 200, ...extraChecks });
        return parseJson(res.body);
    }

    postForm(path, fields, csrfToken, extraChecks = {}) {
        const res = http.post(`${this.base}${path}`, { _token: csrfToken, ...fields }, {
            headers: formHeaders(csrfToken, ajaxHeaders()),
        });
        check(res, extraChecks);
        return { status: res.status, body: parseJson(res.body) };
    }

    // --- Dashboard endpoints ---

    bookingLogs() {
        return this.getJson('/admin/api/bookings/logs', {
            'has bookings array': (r) => Array.isArray(parseJson(r.body)?.bookings),
        });
    }

    cancelRequestLogs() {
        return this.getJson('/admin/api/cancel-requests/logs', {
            'has cancel_requests': (r) => Array.isArray(parseJson(r.body)?.cancel_requests),
        });
    }

    searchCoaches(fromId, toId, date) {
        return this.getJson(
            `/admin/api/coach-services/search?from=${fromId}&to=${toId}&date=${date}`,
            { 'returns array': (r) => Array.isArray(parseJson(r.body)) }
        );
    }

    toggleSeatBlock(scheduleId, seat, csrfToken) {
        return this.postForm(
            `/admin/api/schedules/${scheduleId}/seats/toggle-block`,
            { seat },
            csrfToken,
            { 'toggle → 200 or 422': (r) => r.status === 200 || r.status === 422 }
        ).body;
    }

    cancelBooking(bookingId, csrfToken) {
        return this.postForm(
            `/admin/api/bookings/${bookingId}/cancel`,
            {},
            csrfToken,
            { 'cancel → 200 or 400': (r) => r.status === 200 || r.status === 400 }
        ).body;
    }

    approveCancel(bookingId, csrfToken) {
        const res = http.post(
            `${this.base}/admin/bookings/${bookingId}/approve-cancel`,
            { _token: csrfToken },
            { headers: formHeaders(csrfToken) }
        );
        check(res, { 'approve cancel → 302': (r) => r.status === 302 });
        return res;
    }

    // --- Reports ---

    reportPreview(type) {
        const res = http.get(`${this.base}/admin/reports/${type}/preview`, {
            headers: { Accept: 'text/html' },
        });
        check(res, { [`report ${type} preview → 200`]: (r) => r.status === 200 });
        return res;
    }

    reportExport(type, format) {
        const res = http.get(`${this.base}/admin/reports/${type}/export/${format}`, {
            headers: { Accept: '*/*' },
        });
        check(res, { [`report ${type} ${format} → 200`]: (r) => r.status === 200 });
        return res;
    }
}
