/** Combined — customer load + admin traffic in parallel */
import { baseUrl, thresholds } from '../config/index.js';
import { customerLoadJourney } from '../flows/customer.js';
import { adminBasicJourney } from '../flows/admin.js';

export const options = {
    scenarios: {
        customers: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 30 },
                { duration: '1m', target: 100 },
                { duration: '2m', target: 100 },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
            exec: 'runCustomers',
        },
        admins: {
            executor: 'constant-vus',
            vus: 3,
            duration: '4m',
            exec: 'runAdmins',
        },
    },
    thresholds: thresholds.normal,
};

export function runCustomers() {
    customerLoadJourney(baseUrl());
}

export function runAdmins() {
    try {
        adminBasicJourney(baseUrl());
    } catch (e) {
        console.error(`[admin] ${e.message}`);
    }
}
