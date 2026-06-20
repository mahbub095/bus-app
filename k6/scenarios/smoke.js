/** Smoke test — 1 user, 1 run, verifies the full booking path works */
import { baseUrl, thresholds } from '../config/index.js';
import { smokeJourney } from '../flows/customer.js';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: thresholds.strict,
};

export default function () {
    smokeJourney(baseUrl());
}
