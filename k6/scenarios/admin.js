/** Admin portal — login, logs, coach search, seat toggle */
import { baseUrl, thresholds } from '../config/index.js';
import { adminBasicJourney } from '../flows/admin.js';

export const options = {
    vus: 5,
    duration: '2m',
    thresholds: thresholds.normal,
};

export default function () {
    adminBasicJourney(baseUrl());
}
