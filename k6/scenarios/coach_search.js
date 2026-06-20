/** Coach search — high volume route lookups only */
import { baseUrl, thresholds } from '../config/index.js';
import { searchOnlyJourney } from '../flows/customer.js';

export const options = {
    vus: 50,
    duration: '1m',
    thresholds: thresholds.normal,
};

export default function () {
    searchOnlyJourney(baseUrl());
}
