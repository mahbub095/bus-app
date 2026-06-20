/**
 * Unified API client — combines customer REST and admin session APIs.
 * Scenarios should prefer flows/ for journeys; use this for simple one-off calls.
 */
import { CustomerApi } from './customer-api.js';
import { AdminApi } from './admin-api.js';

export class ApiClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.customer = new CustomerApi(this.baseUrl);
        this.admin = new AdminApi(this.baseUrl);
    }

    // Customer shortcuts (keeps existing scenario imports working)
    getStations() { return this.customer.stations(); }
    searchRoutes(from, to, date) { return this.customer.search(from, to, date); }
    getPromotions() { return this.customer.promotions(); }
    checkPromotion(code) { return this.customer.checkPromo(code); }
    registerUser(name, email, pw) { return this.customer.register(name, email, pw); }
    loginUser(email, pw) { return this.customer.login(email, pw); }
    getMe(token) { return this.customer.me(token); }
    getMyBookings(token) { return this.customer.myBookings(token); }
    updatePassword(token, cur, next) { return this.customer.updatePassword(token, cur, next); }
    logoutUser(token) { return this.customer.logout(token); }
    createBooking(token, payload) { return this.customer.createBooking(token, payload); }
    cancelBooking(token, id) { return this.customer.cancelBooking(token, id); }

    // Admin shortcuts
    adminGetLogin() { return this.admin.fetchLoginCsrf(); }
    adminLogin(email, pw, csrf) { return this.admin.login(email, pw, csrf); }
    adminGetDashboard() { return this.admin.viewDashboard(); }
    adminGetBookingLogs() { return this.admin.bookingLogs(); }
    adminGetCancelLogs() { return this.admin.cancelRequestLogs(); }
    adminSearchCoachServices(from, to, date) { return this.admin.searchCoaches(from, to, date); }
    adminToggleBlockedSeat(schedId, seat, csrf) { return this.admin.toggleSeatBlock(schedId, seat, csrf); }
    adminCancelBookingApi(id, csrf) { return this.admin.cancelBooking(id, csrf); }
    adminApproveCancelRequest(id, csrf) { return this.admin.approveCancel(id, csrf); }
    adminGetReportPreview(type) { return this.admin.reportPreview(type); }
    adminGetReportExport(type, fmt) { return this.admin.reportExport(type, fmt); }
}

export { CustomerApi, AdminApi };
