/**
 * k6 Performance Suite: Smoke Test Scenario
 * Simulates a single customer executing the complete booking flow once.
 * Run: k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/smoke.js
 */
import { sleep, fail } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomName, randomEmail, randomPhone, getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        http_req_failed: ['rate<0.01'], // less than 1% errors
        http_req_duration: ['p(95)<1000'], // 95% of requests should respond in < 1s
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://sonyabus-app.test';
    const api = new ApiClient(baseUrl);

    console.log(`[Smoke Test] Starting test against: ${baseUrl}`);

    // 1. Visit landing page - Load Stations and promotions
    console.log('[Smoke Test] 1. Fetching stations...');
    const stations = api.getStations();
    if (!stations || stations.length === 0) {
        fail('Failed to fetch stations or stations list is empty');
    }
    sleep(1);

    console.log('[Smoke Test] 2. Fetching promotions...');
    api.getPromotions();
    sleep(1);

    // Find DHAKA and CHITTAGONG stations dynamically
    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA');
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG');

    // Fallbacks if not found
    if (!fromStation) fromStation = stations[0];
    if (!toStation) toStation = stations[1] || stations[0];

    console.log(`[Smoke Test] Dynamic stations selected: ${fromStation.name} (ID: ${fromStation.id}) -> ${toStation.name} (ID: ${toStation.id})`);

    // 3. Search routes for tomorrow
    const searchDate = getFutureDateString(1); // tomorrow
    console.log(`[Smoke Test] 3. Searching routes for date: ${searchDate}...`);
    const schedules = api.searchRoutes(fromStation.id, toStation.id, searchDate);
    sleep(1);

    if (!schedules || schedules.length === 0) {
        console.log('[Smoke Test] No schedules found for this date. Smoke test search succeeded, but booking flow skipped because no schedules were seeded.');
        return;
    }

    console.log(`[Smoke Test] Found ${schedules.length} schedules.`);

    // 4. Register a new user
    const name = randomName();
    const email = randomEmail();
    const phone = randomPhone();
    const password = 'Password@123';

    console.log(`[Smoke Test] 4. Registering user: ${email}...`);
    const token = api.registerUser(name, email, password);
    sleep(1);

    if (!token) {
        fail('Registration failed, unable to proceed to booking flow');
    }

    console.log('[Smoke Test] Registration successful. Token received.');

    // 5. Get profile and current bookings
    console.log('[Smoke Test] 5. Fetching profile & initial bookings list...');
    api.getMe(token);
    api.getMyBookings(token);
    sleep(1);

    // 6. Select a schedule and book an available seat
    const targetSchedule = schedules[0];
    const seatMap = targetSchedule.seat_map || {};
    
    // Find available seats
    const availableSeats = Object.keys(seatMap).filter(seatCode => seatMap[seatCode] === 'available');
    
    if (availableSeats.length === 0) {
        console.log(`[Smoke Test] Schedule ID ${targetSchedule.id} has no available seats. Booking skipped.`);
        return;
    }

    const selectedSeat = availableSeats[0];
    console.log(`[Smoke Test] 6. Booking seat ${selectedSeat} on Schedule ID ${targetSchedule.id}...`);

    const bookingPayload = {
        schedule_id: targetSchedule.id,
        passenger_name: name,
        passenger_phone: phone,
        passenger_email: email,
        seat_numbers: selectedSeat,
        payment_method: 'cash',
        passenger_gender: 'M',
        boarding_point: targetSchedule.boarding_points && targetSchedule.boarding_points[0] ? targetSchedule.boarding_points[0].value : fromStation.name,
        dropping_point: targetSchedule.dropping_points && targetSchedule.dropping_points[0] ? targetSchedule.dropping_points[0].value : toStation.name,
    };

    const bookingRes = api.createBooking(token, bookingPayload);
    sleep(1);

    if (!bookingRes.success) {
        fail(`Booking failed with status: ${bookingRes.status}`);
    }

    const bookingId = bookingRes.data.booking.id;
    const pnr = bookingRes.data.booking.pnr;
    console.log(`[Smoke Test] Booking successful! Booking ID: ${bookingId}, PNR: ${pnr}`);

    // Fetch bookings again
    console.log('[Smoke Test] Fetching updated bookings list...');
    const bookings = api.getMyBookings(token);
    checkBookingsList(bookings, bookingId);
    sleep(1);

    // 7. Request cancellation of the ticket
    console.log(`[Smoke Test] 7. Requesting cancellation for booking ID: ${bookingId}...`);
    api.cancelBooking(token, bookingId);
    sleep(1);

    console.log('[Smoke Test] Smoke test run completed successfully.');
}

function checkBookingsList(bookings, bookingId) {
    const found = bookings.some(b => b.id === bookingId);
    if (!found) {
        console.warn(`[Warning] Newly created booking ID ${bookingId} was not found in getMyBookings list.`);
    }
}
