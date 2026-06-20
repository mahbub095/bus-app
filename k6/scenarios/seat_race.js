/** Seat race — 10 users fight for the same seat (tests locking) */
import { baseUrl } from '../config/index.js';
import { seatRaceJourney } from '../flows/customer.js';

export const options = {
    vus: 10,
    iterations: 10,
    thresholds: {
        http_req_failed: ['rate<1.0'], // 422 conflicts are expected
    },
};

export default function () {
    seatRaceJourney(baseUrl());
}
