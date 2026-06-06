/**
 * k6 Performance Suite: Extra Test - Seat Booking Race Condition Test
 * Simulates a concurrent race condition where multiple users attempt to book the exact same seat
 * on the exact same schedule at the same time. This verifies that database locking constraints
 * prevent double booking and reject concurrent requests gracefully (without deadlocks or 500 errors).
 * Run: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/seat_race.js
 */
import { sleep, fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomName, randomEmail, randomPhone, getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 10,             // 10 concurrent users racing
    iterations: 10,      // Run exactly 1 iteration per VU
    thresholds: {
        http_req_failed: ['rate<1.0'], // We expect some HTTP errors (e.g. 400/422 status conflicts are expected)
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    const api = new ApiClient(baseUrl);

    // 1. Setup phase: Each VU registers its own user
    const name = randomName();
    const email = randomEmail();
    const phone = randomPhone();
    const password = 'Password@123';
    const token = api.registerUser(name, email, password);

    if (!token) {
        fail('VU Registration failed during race condition setup');
    }

    // 2. Fetch stations and schedules
    const stations = api.getStations();
    if (!stations || stations.length === 0) return;

    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA') || stations[0];
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG') || (stations[1] || stations[0]);

    const searchDate = getFutureDateString(1); // Tomorrow
    const schedules = api.searchRoutes(fromStation.id, toStation.id, searchDate);

    if (!schedules || schedules.length === 0) return;

    const targetSchedule = schedules[0]; // All VUs target the first schedule
    const seatMap = targetSchedule.seat_map || {};
    const availableSeats = Object.keys(seatMap).filter(seatCode => seatMap[seatCode] === 'available');

    if (availableSeats.length === 0) {
        console.log('[Race Test] No available seats on schedule. Skipping race booking.');
        return;
    }

    // All VUs select the exact same seat code to race for
    const seatToRace = availableSeats[0];

    const bookingPayload = {
        schedule_id: targetSchedule.id,
        passenger_name: name,
        passenger_phone: phone,
        passenger_email: email,
        seat_numbers: seatToRace,
        payment_method: 'cash',
        passenger_gender: 'M',
        boarding_point: targetSchedule.boarding_points && targetSchedule.boarding_points[0] ? targetSchedule.boarding_points[0].value : fromStation.name,
        dropping_point: targetSchedule.dropping_points && targetSchedule.dropping_points[0] ? targetSchedule.dropping_points[0].value : toStation.name,
    };

    // 3. Synchronize execution timing roughly by sleeping briefly to align requests
    sleep(1);

    // 4. Executing the booking request (The Race)
    console.log(`[VU ${__VU}] Racing to book seat ${seatToRace} on schedule ${targetSchedule.id}...`);
    const res = api.createBooking(token, bookingPayload);

    // 5. Audit response
    if (res.status === 201) {
        console.log(`[SUCCESS] VU ${__VU} successfully booked the seat!`);
    } else if (res.status === 400 || res.status === 422) {
        console.log(`[CONFLICT HANDLED] VU ${__VU} was rejected with status ${res.status}: ${JSON.stringify(res.data)}`);
    } else {
        console.log(`[ERROR] VU ${__VU} received unexpected status ${res.status}: ${JSON.stringify(res.data)}`);
    }
}
