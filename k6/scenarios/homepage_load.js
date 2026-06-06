/**
 * k6 Performance Suite: Homepage Load Test
 * Simulates multiple concurrent customers landing on the website, loading the homepage and public data.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/homepage_load.js
 */
import { sleep } from 'k6';
import http from 'k6/http';
import { ApiClient } from '../utils/api.js';

export const options = {
    vus: 50,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Fail rate must be under 5%
        http_req_duration: ['p(95)<1500'],   // 95% of requests must complete under 1.5s
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    // 1. Visit landing page (HTML)
    http.get(baseUrl);
    sleep(1 + Math.random());

    // 2. Fetch public API resources
    api.getStations();
    api.getPromotions();

    // Natural user think time before exiting or looping
    sleep(2 + Math.random() * 3);
}
