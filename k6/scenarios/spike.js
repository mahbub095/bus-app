/** Spike test — sudden surge to 100 users */
import { baseUrl, thresholds } from '../config/index.js';
import { bookingJourney } from '../flows/customer.js';

export const options = {
    stages: [
        { duration: '10s', target: 100 },
        { duration: '20s', target: 100 },
        { duration: '10s', target: 0 },
    ],
    thresholds: thresholds.relaxed,
};

export default function () {
    bookingJourney(baseUrl());
}
