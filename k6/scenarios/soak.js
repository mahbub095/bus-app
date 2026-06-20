/** Soak test — steady moderate load over time (extend duration for production) */
import { baseUrl, thresholds } from '../config/index.js';
import { bookingJourney } from '../flows/customer.js';

export const options = {
    stages: [
        { duration: '30s', target: 40 },
        { duration: '2m', target: 40 },
        { duration: '30s', target: 0 },
    ],
    thresholds: thresholds.normal,
};

export default function () {
    bookingJourney(baseUrl());
}
