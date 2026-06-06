/**
 * k6 Performance Suite: Stress Testing Scenario
 * Simulates heavy concurrent customer traffic ramping up in steps to locate system limits and bottlenecks.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/stress.js
 */
import { runBookingFlow } from './booking_flow.js';

export const options = {
    stages: [
        { duration: '30s', target: 50 },   // Step 1: ramp to 50 VUs
        { duration: '30s', target: 100 },  // Step 2: ramp to 100 VUs
        { duration: '1m', target: 200 },   // Step 3: ramp to 200 VUs (stress limit)
        { duration: '1m', target: 200 },   // Hold at 200 VUs
        { duration: '30s', target: 0 },    // Ramp down to 0 VUs
    ],
    thresholds: {
        // Accept up to 15% failure under extreme stress
        http_req_failed: ['rate<0.15'],
        // 95% of requests must complete under 3.0s under stress
        http_req_duration: ['p(95)<3000'],
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    runBookingFlow(baseUrl);
}
