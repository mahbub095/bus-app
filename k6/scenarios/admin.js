/**
 * k6 Performance Suite: Admin Portal Scenario
 * Simulates administrators logging in and performing back-office tasks (logs, search, seat blocking).
 * Run: k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/admin.js
 */
import { sleep, fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 5,
    duration: '2m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% fail rate
        http_req_duration: ['p(95)<1500'],   // Under 1.5s response times
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://sonyabus-app.test';
    const api = new ApiClient(baseUrl);

    // 1. Fetch Login page to get initial CSRF Token
    const csrfToken = api.adminGetLogin();
    if (!csrfToken) {
        fail('Unable to parse CSRF token from Admin login page');
    }
    sleep(1 + Math.random());

    // 2. Perform Session Login (with admin@sonyabus.com / password123)
    const adminEmail = 'admin@sonyabus.com';
    const adminPassword = 'password123';
    const dashCsrf = api.adminLogin(adminEmail, adminPassword, csrfToken);

    if (!dashCsrf) {
        fail('Admin login failed or failed to retrieve authenticated CSRF token');
    }
    sleep(2 + Math.random() * 2);

    // 3. View Dashboard
    api.adminGetDashboard();
    sleep(2 + Math.random() * 2);

    // 4. Fetch Logs
    api.adminGetBookingLogs();
    api.adminGetCancelLogs();
    sleep(3 + Math.random() * 3);

    // 5. Search coach services as Admin
    // First, fetch stations using public API to know valid IDs
    const stations = api.getStations();
    if (!stations || stations.length === 0) return;

    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA') || stations[0];
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG') || (stations[1] || stations[0]);

    const searchDate = getFutureDateString(1); // tomorrow
    const schedules = api.adminSearchCoachServices(fromStation.id, toStation.id, searchDate);
    sleep(2 + Math.random() * 2);

    if (!schedules || schedules.length === 0) return;

    // 6. Administrative action: Toggle block a seat (20% chance)
    if (Math.random() > 0.80) {
        const targetSchedule = randomItem(schedules);
        if (targetSchedule) {
            // Find a seat code from its seat map (e.g. A1, B3)
            const seatMap = targetSchedule.seat_map || {};
            const seats = Object.keys(seatMap);
            if (seats.length > 0) {
                const randomSeat = randomItem(seats);
                console.log(`[Admin Scenario] Toggling blocked status of seat ${randomSeat} on schedule ${targetSchedule.id}`);
                api.adminToggleBlockedSeat(targetSchedule.id, randomSeat, dashCsrf);
                sleep(2);
            }
        }
    }
}
