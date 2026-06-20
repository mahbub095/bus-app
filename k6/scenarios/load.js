/** Load test — ramps to 100 users with realistic branching */
import { baseUrl, thresholds } from '../config/index.js';
import { customerLoadJourney } from '../flows/customer.js';

export const options = {
    stages: [
        { duration: '30s', target: 30 },
        { duration: '1m', target: 100 },
        { duration: '2m', target: 100 },
        { duration: '30s', target: 0 },
    ],
    thresholds: thresholds.normal,
};

export default function () {
    customerLoadJourney(baseUrl());
}
