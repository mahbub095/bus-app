/**
 * k6 Performance Suite: Extra Test - Promotions/Coupon Validation Load Test
 * Simulates concurrent coupon validations for valid seeded codes to stress validation logic.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/promo_validation.js
 */
import { sleep } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomItem } from '../utils/helpers.js';

export const options = {
    vus: 30,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% HTTP errors
        http_req_duration: ['p(95)<1000'],   // Under 1.0s response times
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    // Seeded valid promo codes
    const promoCodes = [
        'SONYANEW',
        'TRAVEL2026'
    ];

    const targetPromo = randomItem(promoCodes);

    // Call check promotion endpoint
    api.checkPromotion(targetPromo);

    sleep(1 + Math.random() * 2);
}
