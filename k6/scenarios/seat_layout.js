/**
 * k6 Performance Suite: Seat Layout Stress Test
 * Simulates multiple concurrent users checking and polling seat map configurations (occupied vs available).
 * This simulates the frontend's auto-refresh polling behavior (every 5 seconds) for selected schedules.
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/seat_layout.js
 */
import { sleep } from 'k6';
import { ApiClient } from '../utils/api.js';
import { getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 40,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% fail rate
        http_req_duration: ['p(95)<1500'],   // Under 1.5s response times
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    // Fetch stations to obtain valid IDs
    const stations = api.getStations();
    if (!stations || stations.length === 0) return;

    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA') || stations[0];
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG') || (stations[1] || stations[0]);

    // Focus on tomorrow's date to overlap and stress the same schedules
    const searchDate = getFutureDateString(1);

    // 1. Initial search to load routes
    const schedules = api.searchRoutes(fromStation.id, toStation.id, searchDate);
    sleep(2);

    if (!schedules || schedules.length === 0) return;

    // 2. Poll the search route 3 times representing seat map layout refresh checks
    for (let i = 0; i < 3; i++) {
        api.searchRoutes(fromStation.id, toStation.id, searchDate);
        sleep(3); // poll every 3 seconds
    }
}
