/**
 * Reusable admin user journeys (Blade dashboard + session AJAX).
 */
import { fail } from 'k6';
import { ADMIN } from '../config/index.js';
import { ApiClient } from '../lib/client.js';
import {
    futureDate,
    pickRoute,
    randomItem,
    sleepRandom,
    toggleableSeats,
} from '../helpers/index.js';

/** Login and return session CSRF token (fails test if login breaks) */
export function adminLogin(api) {
    const loginCsrf = api.adminGetLogin();
    if (!loginCsrf) fail('Could not read CSRF from admin login page');

    sleepRandom(1, 2);

    const sessionCsrf = api.adminLogin(ADMIN.email, ADMIN.password, loginCsrf);
    if (!sessionCsrf) fail('Admin login failed');

    return sessionCsrf;
}

/** Basic admin workflow: dashboard, logs, coach search, optional seat toggle */
export function adminBasicJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const csrf = adminLogin(api);

    sleepRandom(2, 3);
    api.adminGetDashboard();
    sleepRandom(2, 3);

    api.adminGetBookingLogs();
    api.adminGetCancelLogs();
    sleepRandom(2, 4);

    const stations = api.getStations();
    if (!stations?.length) return;

    const { from, to } = pickRoute(stations);
    const schedules = api.adminSearchCoachServices(from.id, to.id, futureDate(1));
    sleepRandom(2, 3);

    if (schedules?.length && Math.random() > 0.8) {
        maybeToggleSeat(api, schedules, csrf);
    }
}

/** Full dashboard workflow: reports, cancel approve, booking cancel */
export function adminDashboardJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const csrf = adminLogin(api);

    sleepRandom(2, 3);
    api.adminGetDashboard();

    const bookingLogs = api.adminGetBookingLogs();
    const cancelLogs = api.adminGetCancelLogs();
    sleepRandom(2, 3);

    const schedules = api.adminSearchCoachServices(1, 2, futureDate(1));
    sleepRandom(2, 3);

    if (schedules?.length) {
        maybeToggleSeat(api, schedules, csrf);
    }

    // Reports
    for (const type of ['selling', 'cancel']) {
        api.adminGetReportPreview(type);
        api.adminGetReportExport(type, 'excel');
        api.adminGetReportExport(type, 'pdf');
    }
    sleepRandom(2, 3);

    // Approve a pending cancel request if one exists
    const pending = cancelLogs?.cancel_requests?.find((b) => b.status === 'CANCEL_REQUESTED');
    if (pending) {
        api.adminApproveCancelRequest(pending.id, csrf);
        sleepRandom(1, 2);
    }

    // Occasionally cancel an active booking via AJAX
    const active = bookingLogs?.bookings?.find(
        (b) => b.status !== 'CANCELLED' && b.status !== 'CANCEL_REQUESTED'
    );
    if (active && Math.random() > 0.9) {
        api.adminCancelBookingApi(active.id, csrf);
    }

    sleepRandom(2, 4);
}

function maybeToggleSeat(api, schedules, csrf) {
    const schedule = randomItem(schedules);
    const seats = toggleableSeats(schedule?.seat_map);
    if (!seats.length) return;

    const seat = randomItem(seats);
    api.adminToggleBlockedSeat(schedule.id, seat, csrf);
    sleepRandom(1, 2);
}
