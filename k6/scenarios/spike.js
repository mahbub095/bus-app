/**
 * k6 Performance Suite: Spike Testing Scenario
 * Simulates a sudden, massive surge in customer traffic to test system stability and recovery.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/spike.js
 */
import { sleep } from 'k6';
import { runBookingFlow } from './booking_flow.js';

export const options = {
    stages: [
        { duration: '10s', target: 100 },  // Extremely fast ramp-up to 100 VUs (spike)
        { duration: '20s', target: 100 },  // Hold peak load for 20 seconds
        { duration: '10s', target: 0 },    // Fast ramp-down to 0 VUs
    ],
    thresholds: {
        http_req_failed: ['rate<0.10'],     // Under 10% errors allowed under sudden spike
        http_req_duration: ['p(95)<3000'],   // Under 3.0s response times
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    runBookingFlow(baseUrl);
}
