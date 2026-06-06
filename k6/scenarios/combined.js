/**
 * k6 Performance Suite: Combined Customer & Admin Scenario
 * Runs both customer traffic and admin operations in parallel.
 * This simulates a fully loaded system with active customer search/bookings and background administration.
 * Run: k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/combined.js
 */
import customerFlow from './load.js';
import adminFlow from './admin.js';

export const options = {
    scenarios: {
        customer_traffic: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 30 },  // Ramp up customers
                { duration: '1m', target: 100 },  // 100 steady customers
                { duration: '2m', target: 100 },
                { duration: '30s', target: 0 },   // Ramp down
            ],
            gracefulRampDown: '30s',
            exec: 'runCustomer', // Function to execute
        },
        admin_traffic: {
            executor: 'constant-vus',
            vus: 3,             // 3 active admins during the test
            duration: '4m',     // Runs for the full duration
            exec: 'runAdmin',    // Function to execute
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.05'],     // General failure rate must be under 5%
        http_req_duration: ['p(95)<2000'],   // 95% of requests must complete under 2.0s
    },
};

// Customer flow runner
export function runCustomer() {
    customerFlow();
}

// Admin flow runner
export function runAdmin() {
    runAdminFlowWithCatch();
}

// Wrap admin flow to handle failure gracefully in multi-scenario runs
function runAdminFlowWithCatch() {
    try {
        adminFlow();
    } catch (e) {
        console.error(`[Admin Flow Error]: ${e.message}`);
    }
}
