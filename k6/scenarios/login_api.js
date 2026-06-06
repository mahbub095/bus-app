/**
 * k6 Performance Suite: Login API Test
 * Simulates concurrent registration and login operations to test authentication API throughput.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/login_api.js
 */
import { sleep, fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomName, randomEmail, randomPhone } from '../utils/helpers.js';

export const options = {
    vus: 30,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% error rate
        http_req_duration: ['p(95)<3000'],   // 95% of requests must complete under 3.0s (auth uses heavy bcrypt hashing)
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    const name = randomName();
    const email = randomEmail();
    const phone = randomPhone();
    const password = 'Password@123';

    // 1. Simulates dynamic registration
    const token = api.registerUser(name, email, password);
    sleep(1 + Math.random());

    if (token) {
        // 2. Simulates logging back in
        const loginToken = api.loginUser(email, password);
        
        if (!loginToken) {
            fail(`Login failed for registered user: ${email}`);
        }
    }

    // Pacing think time
    sleep(2 + Math.random() * 3);
}
