import { sleep } from 'k6';

export const DEFAULT_BASE_URL = 'http://sonyabus-app.test';

export const ADMIN_CREDENTIALS = {
    email: 'superadmin@sonyabus.com',
    password: 'password123',
};

export function getBaseUrl() {
    return __ENV.BASE_URL || DEFAULT_BASE_URL;
}

export function sleepRandom(minSeconds, maxSeconds) {
    sleep(minSeconds + Math.random() * (maxSeconds - minSeconds));
}
