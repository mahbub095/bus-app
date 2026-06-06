/**
 * k6 Performance Suite: Coach Search Load Test
 * Simulates customers searching coach services on different dates with random station routes.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/coach_search.js
 */
import { sleep } from 'k6';
import { ApiClient } from '../utils/api.js';
import { getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 50,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% fail rate
        http_req_duration: ['p(95)<1500'],   // 95% of requests must complete under 1.5s
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    // Fetch stations to obtain valid IDs
    const stations = api.getStations();
    sleep(1);

    if (!stations || stations.length === 0) return;

    // Pick dynamic from and to stations
    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA');
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG');

    if (!fromStation) fromStation = randomItem(stations);
    if (!toStation) toStation = randomItem(stations.filter(s => s.id !== fromStation.id)) || fromStation;

    // Distribute date load over tomorrow to next 5 days
    const dateOffset = Math.floor(Math.random() * 5) + 1;
    const searchDate = getFutureDateString(dateOffset);

    // Search coach routes
    api.searchRoutes(fromStation.id, toStation.id, searchDate);

    sleep(2 + Math.random() * 3);
}
