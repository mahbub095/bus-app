/**
 * k6 Performance Suite: Admin Dashboard Load Test
 * Simulates administrators logging in and performing back-office reporting and lookup queries.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/admin_dashboard.js
 */
import { fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { getFutureDateString, randomItem } from '../utils/helpers.js';
import { getBaseUrl, sleepRandom, ADMIN_CREDENTIALS } from '../utils/common.js';

export const options = {
    vus: 5,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% error rate
        http_req_duration: ['p(95)<1500'],   // Under 1.5s response times
    },
};

export default function () {
    const baseUrl = getBaseUrl();
    const api = new ApiClient(baseUrl);

    // 1. Fetch Login page to parse CSRF Token
    const csrfToken = api.adminGetLogin();
    if (!csrfToken) {
        fail('Unable to parse CSRF token from Admin login page');
    }
    sleepRandom(1, 2);

    // 2. Perform Session Login (admin credentials)
    const { email: adminEmail, password: adminPassword } = ADMIN_CREDENTIALS;
    const dashCsrf = api.adminLogin(adminEmail, adminPassword, csrfToken);

    if (!dashCsrf) {
        fail('Admin login failed or failed to retrieve authenticated CSRF token');
    }
    sleepRandom(2, 3);

    // 3. Load Admin Dashboard view
    const dashboardRes = api.adminGetDashboard();
    sleepRandom(2, 3);

    // 4. Fetch Logs and Cancel Requests
    const bookingLogs = api.adminGetBookingLogs();
    const cancelLogs = api.adminGetCancelLogs();
    sleepRandom(2, 2);

    // 5. Search coach services as Admin (Dhaka to Chittagong routes)
    const searchDate = getFutureDateString(1);
    const schedules = api.adminSearchCoachServices(1, 2, searchDate);
    sleepRandom(2, 3);

    if (schedules && schedules.length > 0) {
        const targetSchedule = randomItem(schedules);
        const seatMap = targetSchedule?.seat_map || {};
        const seats = Object.keys(seatMap);

        if (seats.length > 0 && Math.random() > 0.80) {
            const randomSeat = randomItem(seats);
            api.adminToggleBlockedSeat(targetSchedule.id, randomSeat, dashCsrf);
            sleepRandom(1, 2);
        }
    }

    // 6. Exercise admin reporting endpoints for dashboard coverage
    api.adminGetReportPreview('selling');
    api.adminGetReportPreview('cancel');
    api.adminGetReportExport('selling', 'excel');
    api.adminGetReportExport('selling', 'pdf');
    api.adminGetReportExport('cancel', 'excel');
    api.adminGetReportExport('cancel', 'pdf');
    sleepRandom(2, 3);

    // 7. If there are pending cancel requests, approve one to cover the cancellation workflow
    const pendingCancel = cancelLogs?.cancel_requests?.find((item) => item.status === 'CANCEL_REQUESTED');
    if (pendingCancel) {
        api.adminApproveCancelRequest(pendingCancel.id, dashCsrf);
        sleepRandom(1, 2);
    }

    // 8. If there are booking logs with not-yet-cancelled bookings, try a cancel request via API
    const cancellableBooking = bookingLogs?.bookings?.find((item) => item.status !== 'CANCELLED' && item.status !== 'CANCEL_REQUESTED');
    if (cancellableBooking && Math.random() > 0.90) {
        api.adminCancelBookingApi(cancellableBooking.id, dashCsrf);
        sleepRandom(1, 2);
    }

    sleepRandom(3, 6);
}
