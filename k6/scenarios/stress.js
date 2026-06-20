/** Stress test — ramps to 200 users to find breaking points */
import { baseUrl, thresholds } from '../config/index.js';
import { bookingJourney } from '../flows/customer.js';

export const options = {
    stages: [
        { duration: '30s', target: 50 },
        { duration: '30s', target: 100 },
        { duration: '1m', target: 200 },
        { duration: '1m', target: 200 },
        { duration: '30s', target: 0 },
    ],
    thresholds: thresholds.stress,
};

export default function () {
    bookingJourney(baseUrl());
}
