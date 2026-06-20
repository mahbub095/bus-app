/** Admin dashboard — full back-office flow including reports and cancellations */
import { baseUrl, thresholds } from '../config/index.js';
import { adminDashboardJourney } from '../flows/admin.js';

export const options = {
    vus: 5,
    duration: '1m',
    thresholds: thresholds.normal,
};

export default function () {
    adminDashboardJourney(baseUrl());
}
