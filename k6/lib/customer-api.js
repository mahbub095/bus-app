/** Customer-facing REST API (/api/*) */
import http from 'k6/http';
import { check } from 'k6';
import { jsonHeaders, parseJson } from './http.js';

export class CustomerApi {
    constructor(baseUrl) {
        this.base = baseUrl;
    }

    url(path) {
        return `${this.base}/api${path}`;
    }

    get(path, token = null, checks = {}) {
        const res = http.get(this.url(path), { headers: jsonHeaders(token) });
        check(res, { [`GET ${path} → 200`]: (r) => r.status === 200, ...checks });
        return res;
    }

    post(path, body, token = null, expectedStatus = 200) {
        const res = http.post(this.url(path), JSON.stringify(body), { headers: jsonHeaders(token) });
        check(res, { [`POST ${path} → ${expectedStatus}`]: (r) => r.status === expectedStatus });
        return res;
    }

    // --- Endpoints ---

    stations() {
        const res = this.get('/stations', null, {
            'stations is array': (r) => Array.isArray(parseJson(r.body, [])),
        });
        return parseJson(res.body, []);
    }

    search(fromId, toId, date) {
        const res = this.get(`/search?from=${fromId}&to=${toId}&date=${date}`, null, {
            'search returns array': (r) => Array.isArray(parseJson(r.body, [])),
        });
        return parseJson(res.body, []);
    }

    promotions() {
        const res = this.get('/promotions');
        return parseJson(res.body, []);
    }

    checkPromo(code) {
        const res = this.get(`/promotions/check?code=${code}`, null, {
            'promo has discount': (r) => typeof parseJson(r.body)?.discount_amount === 'number',
        });
        return parseJson(res.body);
    }

    register(name, email, password) {
        const res = http.post(
            this.url('/auth/register'),
            JSON.stringify({ name, email, password, password_confirmation: password }),
            { headers: jsonHeaders() }
        );
        const ok = check(res, {
            'register → 200/201': (r) => r.status === 200 || r.status === 201,
            'register returns token': (r) => !!parseJson(r.body)?.token,
        });
        return ok ? parseJson(res.body)?.token : null;
    }

    login(email, password) {
        const res = http.post(
            this.url('/auth/login'),
            JSON.stringify({ email, password }),
            { headers: jsonHeaders() }
        );
        const ok = check(res, {
            'login → 200': (r) => r.status === 200,
            'login returns token': (r) => !!parseJson(r.body)?.token,
        });
        return ok ? parseJson(res.body)?.token : null;
    }

    me(token) {
        return parseJson(this.get('/auth/me', token).body);
    }

    myBookings(token) {
        return parseJson(this.get('/bookings/mine', token).body, []);
    }

    updatePassword(token, current, next) {
        const res = this.post('/auth/password', {
            current_password: current,
            password: next,
            password_confirmation: next,
        }, token);
        return res.status === 200;
    }

    logout(token) {
        return this.post('/auth/logout', {}, token).status === 200;
    }

    createBooking(token, payload) {
        const res = http.post(this.url('/bookings'), JSON.stringify(payload), {
            headers: jsonHeaders(token),
        });
        const ok = check(res, {
            'booking → 201': (r) => r.status === 201,
            'booking has data': (r) => !!parseJson(r.body)?.booking,
        });
        return { success: ok, status: res.status, data: parseJson(res.body) };
    }

    cancelBooking(token, bookingId) {
        const res = http.post(this.url(`/bookings/${bookingId}/cancel`), '{}', {
            headers: jsonHeaders(token),
        });
        check(res, { 'cancel → 200': (r) => r.status === 200 });
        return parseJson(res.body);
    }
}
