/** Seat layout — poll seat maps like the frontend auto-refresh */
import { baseUrl, thresholds } from '../config/index.js';
import { seatMapPollJourney } from '../flows/customer.js';

export const options = {
    vus: 40,
    duration: '1m',
    thresholds: thresholds.normal,
};

export default function () {
    seatMapPollJourney(baseUrl(), 3);
}
