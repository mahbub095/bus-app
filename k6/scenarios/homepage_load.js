/** Homepage — landing page + public API data */
import http from 'k6/http';
import { sleep } from 'k6';
import { baseUrl, thresholds } from '../config/index.js';
import { ApiClient } from '../lib/client.js';

export const options = {
    vus: 50,
    duration: '1m',
    thresholds: thresholds.normal,
};

export default function () {
    const url = baseUrl();
    const api = new ApiClient(url);

    http.get(url);
    sleep(1 + Math.random());

    api.getStations();
    api.getPromotions();

    sleep(2 + Math.random() * 3);
}
