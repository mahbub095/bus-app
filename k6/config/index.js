/** Shared k6 test configuration */

export const DEFAULT_BASE_URL = 'http://sonyabus-app.test';

export const ADMIN = {
    email: 'superadmin@sonyabus.com',
    password: 'password123',
};

export const TEST_PASSWORD = 'Password@123';

export const PROMO_CODES = ['SONYANEW', 'TRAVEL2026'];

export const DEFAULT_ROUTE = {
    from: 'DHAKA',
    to: 'CHITTAGONG',
};

/** Resolve target URL from k6 environment */
export function baseUrl() {
    return (__ENV.BASE_URL || DEFAULT_BASE_URL).replace(/\/$/, '');
}

/** Reusable pass/fail thresholds */
export const thresholds = {
    strict: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<1000'],
    },
    normal: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<1500'],
    },
    relaxed: {
        http_req_failed: ['rate<0.10'],
        http_req_duration: ['p(95)<3000'],
    },
    stress: {
        http_req_failed: ['rate<0.15'],
        http_req_duration: ['p(95)<3000'],
    },
};
