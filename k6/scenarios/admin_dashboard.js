/**
 * k6 Performance Suite: Admin Dashboard Load Test
 * Simulates administrators logging in and performing back-office reporting and lookup queries.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/admin_dashboard.js
 */
import { fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { getFutureDateString } from '../utils/helpers.js';
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
    api.adminGetDashboard();
    sleepRandom(2, 3);

    // 4. Fetch Logs and Cancel Requests
    api.adminGetBookingLogs();
    api.adminGetCancelLogs();
    sleepRandom(2, 2);

    // 5. Search coach services as Admin (Dhaka to Chittagong routes)
    const searchDate = getFutureDateString(1);
    api.adminSearchCoachServices(1, 2, searchDate);

    sleepRandom(3, 6);
}
