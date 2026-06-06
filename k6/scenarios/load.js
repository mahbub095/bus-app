/**
 * k6 Performance Suite: Realistic Load Test Scenario (Enhanced)
 * Simulates normal-to-peak application load by ramping up to 100 virtual users.
 * Uses a branching behavior model (not all users book a ticket) for realistic traffic.
 * Run: k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/load.js
 */
import { sleep } from 'k6';
import { ApiClient } from '../utils/api.js';
import { randomName, randomEmail, randomPhone, getFutureDateString, randomItem } from '../utils/helpers.js';

export const options = {
    stages: [
        { duration: '30s', target: 30 },  // Ramp up to 30 VUs
        { duration: '1m', target: 100 },  // Ramp up to 100 VUs (steady peak)
        { duration: '2m', target: 100 },  // Hold at 100 VUs for 2 minutes
        { duration: '30s', target: 0 },   // Ramp down to 0 VUs
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Fail rate must be under 5%
        http_req_duration: ['p(95)<1500'],   // 95% of requests must complete under 1.5s
    },
};

export default function () {
    const baseUrl = __ENV.BASE_URL || 'http://sonyabus-app.test';
    const api = new ApiClient(baseUrl);

    // 1. Visit landing page: Fetch stations & promotions (100% of users)
    const stations = api.getStations();
    api.getPromotions();
    
    // Check promo code check endpoint (40% of users check coupon)
    if (Math.random() > 0.60) {
        api.checkPromotion(Math.random() > 0.5 ? 'SONYANEW' : 'TRAVEL2026');
    }
    
    // Pacing: think time (2 to 5 seconds)
    sleep(2 + Math.random() * 3);

    if (!stations || stations.length === 0) return;

    // Pick stations: Dhaka to Chittagong or random stations
    let fromStation = stations.find(s => s.name.toUpperCase() === 'DHAKA');
    let toStation = stations.find(s => s.name.toUpperCase() === 'CHITTAGONG');
    if (!fromStation) fromStation = randomItem(stations);
    if (!toStation) toStation = randomItem(stations.filter(s => s.id !== fromStation.id)) || fromStation;

    // 2. Search for routes (100% of users)
    // Random date in the next 1-5 days to distribute DB load
    const dateOffset = Math.floor(Math.random() * 5) + 1;
    const searchDate = getFutureDateString(dateOffset);
    const schedules = api.searchRoutes(fromStation.id, toStation.id, searchDate);

    sleep(2 + Math.random() * 4);

    if (!schedules || schedules.length === 0) return;

    // --- BRANCH 1: User authentication (50% of users proceed to register/auth) ---
    if (Math.random() > 0.50) {
        const name = randomName();
        const email = randomEmail();
        const phone = randomPhone();
        const currentPassword = 'Password@123';
        let activePassword = currentPassword;

        const token = api.registerUser(name, email, activePassword);
        sleep(2 + Math.random() * 3);

        if (!token) return;

        // Fetch auth details & current bookings list
        api.getMe(token);
        api.getMyBookings(token);
        sleep(2 + Math.random() * 2);

        // Update password simulation (10% of users change their password)
        if (Math.random() > 0.90) {
            const newPassword = 'NewPassword@123';
            const passUpdated = api.updatePassword(token, activePassword, newPassword);
            if (passUpdated) {
                activePassword = newPassword;
            }
            sleep(2);
        }

        // --- BRANCH 2: Ticket Booking (25% of all users, which is half of authenticated users) ---
        let bookingId = null;
        if (Math.random() > 0.50) {
            // Select a random schedule from search results
            const targetSchedule = randomItem(schedules);
            if (targetSchedule) {
                const seatMap = targetSchedule.seat_map || {};
                const availableSeats = Object.keys(seatMap).filter(seatCode => seatMap[seatCode] === 'available');

                if (availableSeats.length > 0) {
                    // Pick 1 or 2 seats randomly
                    const numSeats = Math.random() > 0.7 ? 2 : 1;
                    const seatsToBook = availableSeats.slice(0, numSeats).join(',');

                    const bookingPayload = {
                        schedule_id: targetSchedule.id,
                        passenger_name: name,
                        passenger_phone: phone,
                        passenger_email: email,
                        seat_numbers: seatsToBook,
                        payment_method: 'cash',
                        passenger_gender: Math.random() > 0.5 ? 'M' : 'F',
                        boarding_point: targetSchedule.boarding_points && targetSchedule.boarding_points[0] ? targetSchedule.boarding_points[0].value : fromStation.name,
                        dropping_point: targetSchedule.dropping_points && targetSchedule.dropping_points[0] ? targetSchedule.dropping_points[0].value : toStation.name,
                    };

                    const bookingRes = api.createBooking(token, bookingPayload);
                    sleep(3 + Math.random() * 3);

                    if (bookingRes.success && bookingRes.data.booking) {
                        bookingId = bookingRes.data.booking.id;

                        // View updated bookings list
                        api.getMyBookings(token);
                        sleep(2);
                    }
                }
            }
        }

        // --- BRANCH 3: Ticket Cancellation (10% of booked users request cancellation) ---
        if (bookingId && Math.random() > 0.90) {
            api.cancelBooking(token, bookingId);
            sleep(1);
        }

        // Logout Cleanup (90% of authenticated users logout to delete tokens)
        if (Math.random() > 0.10) {
            api.logoutUser(token);
            sleep(1);
        }
    }
}
