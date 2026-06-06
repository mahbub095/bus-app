/**
 * k6 Performance Suite: Soak Testing Scenario
 * Simulates constant, moderate customer traffic over a long period to uncover memory/resource leaks.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/soak.js
 * (Note: Duration is set to 3 minutes for local verification, but should be extended to 2-4 hours for production soak testing).
 */
import { runBookingFlow } from './booking_flow.js';

export const options = {
    stages: [
        { duration: '30s', target: 40 },   // Warm up: ramp up to 40 VUs
        { duration: '2m', target: 40 },    // Soak: hold at 40 VUs for 2 minutes (extend this to 2h-4h in production)
        { duration: '30s', target: 0 },    // Ramp down to 0 VUs
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% fail rate
        http_req_duration: ['p(95)<2000'],   // Under 2.0s response times
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    runBookingFlow(baseUrl);
}
