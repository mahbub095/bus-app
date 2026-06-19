/**
 * k6 Load Testing Utilities: API Client Wrapper (Enhanced)
 */
import http from 'k6/http';
import { check } from 'k6';

export class ApiClient {
    constructor(baseUrl) {
        // Strip trailing slash if present
        this.baseUrl = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
    }

    // Common headers for REST JSON API
    getHeaders(token = null) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        return headers;
    }

    /*
     * ==========================================
     * Public / Customer API Operations
     * ==========================================
     */

    // GET /api/stations
    getStations() {
        const url = `${this.baseUrl}/api/stations`;
        const res = http.get(url, { headers: this.getHeaders() });
        
        check(res, {
            'GET /api/stations status is 200': (r) => r.status === 200,
            'GET /api/stations has data': (r) => {
                try {
                    return Array.isArray(JSON.parse(r.body));
                } catch (e) {
                    return false;
                }
            }
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return [];
        }
    }

    // GET /api/search?from=X&to=Y&date=YYYY-MM-DD
    searchRoutes(fromId, toId, dateStr) {
        const url = `${this.baseUrl}/api/search?from=${fromId}&to=${toId}&date=${dateStr}`;
        const res = http.get(url, { headers: this.getHeaders() });

        check(res, {
            'GET /api/search status is 200': (r) => r.status === 200,
            'GET /api/search returns array': (r) => {
                try {
                    return Array.isArray(JSON.parse(r.body));
                } catch (e) {
                    return false;
                }
            }
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return [];
        }
    }

    // GET /api/promotions
    getPromotions() {
        const url = `${this.baseUrl}/api/promotions`;
        const res = http.get(url, { headers: this.getHeaders() });

        check(res, {
            'GET /api/promotions status is 200': (r) => r.status === 200,
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return [];
        }
    }

    // GET /api/promotions/check?code=CODE
    checkPromotion(code) {
        const url = `${this.baseUrl}/api/promotions/check?code=${code}`;
        const res = http.get(url, { headers: this.getHeaders() });

        check(res, {
            'GET /api/promotions/check status is 200': (r) => r.status === 200,
            'GET /api/promotions/check has discount': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return typeof body.discount_amount === 'number';
                } catch (e) {
                    return false;
                }
            }
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return null;
        }
    }

    // POST /api/auth/register
    registerUser(name, email, password) {
        const url = `${this.baseUrl}/api/auth/register`;
        const payload = JSON.stringify({
            name: name,
            email: email,
            password: password,
            password_confirmation: password
        });

        const res = http.post(url, payload, { headers: this.getHeaders() });

        const isSuccess = check(res, {
            'POST /api/auth/register status is 201 or 200': (r) => r.status === 201 || r.status === 200,
            'POST /api/auth/register has token': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return !!body.token || !!(body.data && body.data.token);
                } catch (e) {
                    return false;
                }
            }
        });

        if (!isSuccess) {
            return null;
        }

        try {
            const body = JSON.parse(res.body);
            return body.token || (body.data && body.data.token);
        } catch (e) {
            return null;
        }
    }

    // POST /api/auth/login
    loginUser(email, password) {
        const url = `${this.baseUrl}/api/auth/login`;
        const payload = JSON.stringify({
            email: email,
            password: password
        });

        const res = http.post(url, payload, { headers: this.getHeaders() });

        const isSuccess = check(res, {
            'POST /api/auth/login status is 200': (r) => r.status === 200,
            'POST /api/auth/login has token': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return !!body.token || !!(body.data && body.data.token);
                } catch (e) {
                    return false;
                }
            }
        });

        if (!isSuccess) {
            return null;
        }

        try {
            const body = JSON.parse(res.body);
            return body.token || (body.data && body.data.token);
        } catch (e) {
            return null;
        }
    }

    // GET /api/auth/me
    getMe(token) {
        const url = `${this.baseUrl}/api/auth/me`;
        const res = http.get(url, { headers: this.getHeaders(token) });

        check(res, {
            'GET /api/auth/me status is 200': (r) => r.status === 200,
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return null;
        }
    }

    // POST /api/auth/password
    updatePassword(token, currentPassword, newPassword) {
        const url = `${this.baseUrl}/api/auth/password`;
        const payload = JSON.stringify({
            current_password: currentPassword,
            password: newPassword,
            password_confirmation: newPassword
        });

        const res = http.post(url, payload, { headers: this.getHeaders(token) });

        return check(res, {
            'POST /api/auth/password status is 200': (r) => r.status === 200,
        });
    }

    // POST /api/auth/logout
    logoutUser(token) {
        const url = `${this.baseUrl}/api/auth/logout`;
        const res = http.post(url, '{}', { headers: this.getHeaders(token) });

        return check(res, {
            'POST /api/auth/logout status is 200': (r) => r.status === 200,
        });
    }

    // GET /api/bookings/mine
    getMyBookings(token) {
        const url = `${this.baseUrl}/api/bookings/mine`;
        const res = http.get(url, { headers: this.getHeaders(token) });

        check(res, {
            'GET /api/bookings/mine status is 200': (r) => r.status === 200,
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return [];
        }
    }

    // POST /api/bookings
    createBooking(token, bookingPayload) {
        const url = `${this.baseUrl}/api/bookings`;
        const payload = JSON.stringify(bookingPayload);
        const res = http.post(url, payload, { headers: this.getHeaders(token) });

        const isSuccess = check(res, {
            'POST /api/bookings status is 201': (r) => r.status === 201,
            'POST /api/bookings has booking data': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return !!body.booking || !!body.data;
                } catch (e) {
                    return false;
                }
            }
        });

        try {
            return {
                success: isSuccess,
                status: res.status,
                data: JSON.parse(res.body)
            };
        } catch (e) {
            return {
                success: false,
                status: res.status,
                data: null
            };
        }
    }

    // POST /api/bookings/{id}/cancel
    cancelBooking(token, bookingId) {
        const url = `${this.baseUrl}/api/bookings/${bookingId}/cancel`;
        const res = http.post(url, '{}', { headers: this.getHeaders(token) });

        check(res, {
            'POST /api/bookings/{id}/cancel status is 200': (r) => r.status === 200,
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return null;
        }
    }

    /*
     * ==========================================
     * Session-based Admin Dashboard (/admin/api/*)
     * Admin AJAX uses Laravel session auth + CSRF (Sanctum /api/admin/* was removed).
     * ==========================================
     */

    adminGetJson(path, checks = {}) {
        const url = `${this.baseUrl}${path}`;
        const res = http.get(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        check(res, {
            [`GET ${path} status is 200`]: (r) => r.status === 200,
            ...checks,
        });

        try {
            return JSON.parse(res.body);
        } catch (e) {
            return null;
        }
    }

    adminPostJson(path, payload, csrfToken, checks = {}) {
        const res = this.adminPostForm(path, payload, csrfToken, {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        });

        check(res, checks);

        try {
            return {
                status: res.status,
                body: JSON.parse(res.body),
            };
        } catch (e) {
            return {
                status: res.status,
                body: null,
            };
        }
    }

    // GET /admin/login (fetches HTML page & CSRF token)
    adminGetLogin() {
        const url = `${this.baseUrl}/admin/login`;
        const res = http.get(url);
        
        const is200 = check(res, {
            'GET /admin/login status is 200': (r) => r.status === 200,
        });

        if (!is200) return null;

        // Parse HTML to extract CSRF token: <input type="hidden" name="_token" value="xxxx">
        // Using response.html() which returns a Selection object.
        const doc = res.html();
        const csrfToken = doc.find('input[name="_token"]').attr('value');
        
        check(csrfToken, {
            'Admin login page has CSRF token': (t) => !!t,
        });

        return csrfToken;
    }

    // POST /admin/login (authenticates session)
    adminLogin(email, password, csrfToken) {
        const url = `${this.baseUrl}/admin/login`;
        const payload = {
            _token: csrfToken,
            email: email,
            password: password
        };

        // Standard Laravel login submits urlencoded form fields
        const params = {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'text/html,application/xhtml+xml,application/xml',
            },
            redirects: 5 // follow redirect to dashboard
        };

        const res = http.post(url, payload, params);

        const isSuccess = check(res, {
            'POST /admin/login redirects/succeeds with 200': (r) => r.status === 200,
            'POST /admin/login lands on Dashboard': (r) => r.body.indexOf('Dashboard') !== -1 || r.body.indexOf('Admin') !== -1,
        });

        // Try to scrape dashboard CSRF token for subsequent POST AJAX requests
        if (isSuccess) {
            const doc = res.html();
            const dashCsrf = doc.find('meta[name="csrf-token"]').attr('content') || doc.find('input[name="_token"]').attr('value');
            return dashCsrf || csrfToken; // fallback
        }
        
        return null;
    }

    // GET /admin
    adminGetDashboard() {
        const url = `${this.baseUrl}/admin`;
        const res = http.get(url);

        check(res, {
            'GET /admin status is 200': (r) => r.status === 200,
            'GET /admin is dashboard': (r) => r.body.indexOf('Dashboard') !== -1,
        });

        return res;
    }

    adminPostForm(path, payload = {}, csrfToken, extraHeaders = {}) {
        const url = `${this.baseUrl}${path}`;
        const params = {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json, text/html, */*',
                'X-CSRF-TOKEN': csrfToken,
                ...extraHeaders,
            }
        };

        return http.post(url, payload, params);
    }

    // GET admin report endpoints
    adminGetReportPreview(reportType) {
        const url = `${this.baseUrl}/admin/reports/${reportType}/preview`;
        const res = http.get(url, { headers: { 'Accept': 'text/html,application/xhtml+xml,application/xml' } });

        check(res, {
            [`GET /admin/reports/${reportType}/preview status is 200`]: (r) => r.status === 200,
        });

        return res;
    }

    adminGetReportExport(reportType, format) {
        const url = `${this.baseUrl}/admin/reports/${reportType}/export/${format}`;
        const res = http.get(url, { headers: { 'Accept': '*/*' } });

        check(res, {
            [`GET /admin/reports/${reportType}/export/${format} status is 200`]: (r) => r.status === 200,
        });

        return res;
    }

    adminCancelBookingApi(bookingId, csrfToken) {
        const payload = {
            _token: csrfToken,
        };

        const result = this.adminPostJson(`/admin/api/bookings/${bookingId}/cancel`, payload, csrfToken, {
            [`POST /admin/api/bookings/${bookingId}/cancel status is 200 or 400`]: (r) => r.status === 200 || r.status === 400,
            [`POST /admin/api/bookings/${bookingId}/cancel response shape`]: (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return typeof body.message === 'string'
                        && (r.status !== 200 || (body.booking_id && body.status === 'CANCELLED'));
                } catch (e) {
                    return false;
                }
            },
        });

        return result.body;
    }

    adminApproveCancelRequest(bookingId, csrfToken) {
        const payload = {
            _token: csrfToken,
        };

        const res = this.adminPostForm(`/admin/bookings/${bookingId}/approve-cancel`, payload, csrfToken, {
            'Accept': 'text/html,application/json,*/*',
            'X-Requested-With': 'XMLHttpRequest',
        });

        check(res, {
            [`POST /admin/bookings/${bookingId}/approve-cancel status is 302`]: (r) => r.status === 302,
        });

        return { status: res.status };
    }

    // GET /admin/api/bookings/logs (AdminBookingService)
    adminGetBookingLogs() {
        return this.adminGetJson('/admin/api/bookings/logs', {
            'booking logs has bookings array': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return Array.isArray(body.bookings) && typeof body.updated_at === 'string';
                } catch (e) {
                    return false;
                }
            },
            'booking log entries have pnr and schedule': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    if (!body.bookings.length) return true;
                    const entry = body.bookings[0];
                    return typeof entry.pnr === 'string'
                        && typeof entry.passenger_name === 'string'
                        && entry.schedule
                        && entry.schedule.route;
                } catch (e) {
                    return false;
                }
            },
        });
    }

    // GET /admin/api/cancel-requests/logs (AdminBookingService)
    adminGetCancelLogs() {
        return this.adminGetJson('/admin/api/cancel-requests/logs', {
            'cancel logs has cancel_requests array': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return Array.isArray(body.cancel_requests) && typeof body.updated_at === 'string';
                } catch (e) {
                    return false;
                }
            },
        });
    }

    // GET /admin/api/coach-services/search (CoachServicesService, includes seat_bookings)
    adminSearchCoachServices(fromId, toId, dateStr, coachType = null) {
        let path = `/admin/api/coach-services/search?from=${fromId}&to=${toId}&date=${dateStr}`;
        if (coachType) {
            path += `&coach_type=${encodeURIComponent(coachType)}`;
        }

        return this.adminGetJson(path, {
            'coach search returns array': (r) => {
                try {
                    return Array.isArray(JSON.parse(r.body));
                } catch (e) {
                    return false;
                }
            },
            'coach search includes admin seat_bookings when schedules exist': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    if (!body.length) return true;
                    return Object.prototype.hasOwnProperty.call(body[0], 'seat_bookings');
                } catch (e) {
                    return false;
                }
            },
        });
    }

    // POST /admin/api/schedules/{id}/seats/toggle-block (CoachServicesService)
    adminToggleBlockedSeat(scheduleId, seatCode, csrfToken) {
        const payload = {
            _token: csrfToken,
            seat: seatCode,
        };

        const result = this.adminPostJson(
            `/admin/api/schedules/${scheduleId}/seats/toggle-block`,
            payload,
            csrfToken,
            {
                'POST toggle-block status is 200 or 422': (r) => r.status === 200 || r.status === 422,
                'POST toggle-block response shape on success': (r) => {
                    if (r.status !== 200) return true;
                    try {
                        const body = JSON.parse(r.body);
                        return typeof body.message === 'string'
                            && body.seat === seatCode.toUpperCase()
                            && typeof body.blocked === 'boolean'
                            && body.seat_map
                            && typeof body.available_seats_count === 'number';
                    } catch (e) {
                        return false;
                    }
                },
            }
        );

        return result.body;
    }
}
