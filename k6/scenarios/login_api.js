/** Auth API — register + login throughput */
import { baseUrl, thresholds } from '../config/index.js';
import { authJourney } from '../flows/customer.js';

export const options = {
    vus: 30,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<3000'], // bcrypt is slow
    },
};

export default function () {
    authJourney(baseUrl());
}
