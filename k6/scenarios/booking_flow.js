/**
 * k6 Performance Suite: Booking Flow Test Scenario (Core)
 * Simulates a customer executing the complete booking lifecycle.
 * Run directly: k6 run --env BASE_URL="http://localhost:8000" k6/scenarios/booking_flow.js
 */
import { sleep } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomName, randomEmail, randomPhone, getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    vus: 20,
    duration: '1m',
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Under 5% fail rate
        http_req_duration: ['p(95)<2000'],   // Under 2.0s response times
    },
};

// Unified flow that can be shared across scenarios
export function runBookingFlow(baseUrl) {
    const api = new ApiClient(baseUrl);

    // 1. Visit landing page
    const stations = api.getStations();
    api.getPromotions();
    sleep(1 + Math.random());

    if (!stations || stations.length === 0) return;

    // Pick station pair
    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA');
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG');
    if (!fromStation) fromStation = randomItem(stations);
    if (!toStation) toStation = randomItem(stations.filter(s => s.id !== fromStation.id)) || fromStation;

    // 2. Search for routes
    const dateOffset = Math.floor(Math.random() * 5) + 1;
    const searchDate = getFutureDateString(dateOffset);
    const schedules = api.searchRoutes(fromStation.id, toStation.id, searchDate);
    sleep(1 + Math.random());

    if (!schedules || schedules.length === 0) return;

    // 3. Authenticate (Register new user 80% of the time, Login existing 20% of the time)
    const name = randomName();
    const email = randomEmail();
    const phone = randomPhone();
    const password = 'Password@123';
    let token = null;

    if (Math.random() > 0.20) {
        token = api.registerUser(name, email, password);
    } else {
        // Fallback or attempt to login (use seed accounts or mock register)
        token = api.registerUser(name, email, password); // reuse register to guarantee unique valid token under concurrent load
    }
    sleep(1 + Math.random());

    if (!token) return;

    // Fetch user details
    api.getMe(token);
    api.getMyBookings(token);
    sleep(1 + Math.random());

    // 4. Booking action (50% of authenticated users select seat and book)
    let bookingId = null;
    if (Math.random() > 0.50) {
        const targetSchedule = randomItem(schedules);
        if (targetSchedule) {
            const seatMap = targetSchedule.seat_map || {};
            const availableSeats = Object.keys(seatMap).filter(seatCode => seatMap[seatCode] === 'available');

            if (availableSeats.length > 0) {
                // Book a random seat
                const selectedSeat = randomItem(availableSeats);
                const bookingPayload = {
                    schedule_id: targetSchedule.id,
                    passenger_name: name,
                    passenger_phone: phone,
                    passenger_email: email,
                    seat_numbers: selectedSeat,
                    payment_method: 'cash',
                    passenger_gender: Math.random() > 0.5 ? 'M' : 'F',
                    boarding_point: targetSchedule.boarding_points && targetSchedule.boarding_points[0] ? targetSchedule.boarding_points[0].value : fromStation.name,
                    dropping_point: targetSchedule.dropping_points && targetSchedule.dropping_points[0] ? targetSchedule.dropping_points[0].value : toStation.name,
                };

                const bookingRes = api.createBooking(token, bookingPayload);
                sleep(2);

                if (bookingRes.success && bookingRes.data.booking) {
                    bookingId = bookingRes.data.booking.id;
                    api.getMyBookings(token);
                    sleep(1);
                }
            }
        }
    }

    // 5. Cancellation (10% of booked users request cancellation)
    if (bookingId && Math.random() > 0.90) {
        api.cancelBooking(token, bookingId);
        sleep(1);
    }

    // Logout
    if (Math.random() > 0.50) {
        api.logoutUser(token);
        sleep(1);
    }
}

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://localhost:8000';
    runBookingFlow(baseUrl);
}
