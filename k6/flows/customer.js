/**
 * Reusable customer user journeys.
 * Each function = one complete path a virtual user can take.
 */
import { fail } from 'k6';
import { TEST_PASSWORD, PROMO_CODES } from '../config/index.js';
import { ApiClient } from '../lib/client.js';
import {
    availableSeats,
    bookingPayload,
    futureDate,
    newPassenger,
    pickRoute,
    randomItem,
    sleepRandom,
} from '../helpers/index.js';

/** Browse homepage data: stations, promotions, optional promo check */
export function browseHome(api) {
    const stations = api.getStations();
    api.getPromotions();

    if (Math.random() > 0.6) {
        api.checkPromotion(randomItem(PROMO_CODES));
    }

    sleepRandom(1, 3);
    return stations;
}

/** Search routes for a random date in the next 1–5 days */
export function searchRoutes(api, stations) {
    const { from, to } = pickRoute(stations);
    if (!from || !to) return { from, to, schedules: [] };

    const daysAhead = Math.floor(Math.random() * 5) + 1;
    const schedules = api.searchRoutes(from.id, to.id, futureDate(daysAhead));
    sleepRandom(2, 5);

    return { from, to, schedules };
}

/** Register, view profile, optionally book and cancel */
export function registerAndMaybeBook(api, stations, schedules) {
    const passenger = newPassenger();
    const token = api.registerUser(passenger.name, passenger.email, TEST_PASSWORD);
    if (!token) return null;

    sleepRandom(1, 3);
    api.getMe(token);
    api.getMyBookings(token);
    sleepRandom(1, 3);

    let bookingId = null;

    if (Math.random() > 0.5 && schedules?.length) {
        bookingId = tryBookSeat(api, token, passenger, stations, schedules);
    }

    if (bookingId && Math.random() > 0.9) {
        api.cancelBooking(token, bookingId);
        sleepRandom(1, 2);
    }

    if (Math.random() > 0.1) {
        api.logoutUser(token);
    }

    return token;
}

/** Attempt to book 1–2 available seats on a random schedule */
export function tryBookSeat(api, token, passenger, stations, schedules) {
    const schedule = randomItem(schedules);
    if (!schedule) return null;

    const seats = availableSeats(schedule.seat_map);
    if (!seats.length) return null;

    const count = Math.random() > 0.7 ? 2 : 1;
    const { from, to } = pickRoute(stations);
    const payload = bookingPayload({
        schedule,
        passenger,
        fromStation: from,
        toStation: to,
        seats: seats.slice(0, count),
    });

    const result = api.createBooking(token, payload);
    sleepRandom(2, 4);

    if (result.success) {
        api.getMyBookings(token);
        return result.data?.booking?.id ?? null;
    }
    return null;
}

/** Full customer load-test journey (used by load.js) */
export function customerLoadJourney(baseUrl) {
    const api = new ApiClient(baseUrl);

    const stations = browseHome(api);
    if (!stations?.length) return;

    const { schedules } = searchRoutes(api, stations);
    if (!schedules?.length) return;

    if (Math.random() > 0.5) {
        registerAndMaybeBook(api, stations, schedules);
    }
}

/** Core booking lifecycle (used by stress/soak/spike/booking_flow.js) */
export function bookingJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const stations = browseHome(api);
    if (!stations?.length) return;

    const { schedules } = searchRoutes(api, stations);
    if (!schedules?.length) return;

    registerAndMaybeBook(api, stations, schedules);
}

/** Single-run smoke test — must pass or fail loudly */
export function smokeJourney(baseUrl) {
    const api = new ApiClient(baseUrl);

    const stations = api.getStations();
    if (!stations?.length) fail('No stations returned');

    api.getPromotions();

    const { from, to } = pickRoute(stations);
    const schedules = api.searchRoutes(from.id, to.id, futureDate(1));
    if (!schedules?.length) {
        console.log('[smoke] No schedules — search OK, booking skipped');
        return;
    }

    const passenger = newPassenger();
    const token = api.registerUser(passenger.name, passenger.email, TEST_PASSWORD);
    if (!token) fail('Registration failed');

    api.getMe(token);
    api.getMyBookings(token);

    const schedule = schedules[0];
    const seats = availableSeats(schedule.seat_map);
    if (!seats.length) {
        console.log('[smoke] No available seats — booking skipped');
        return;
    }

    const result = api.createBooking(
        token,
        bookingPayload({ schedule, passenger, fromStation: from, toStation: to, seats: seats[0] })
    );
    if (!result.success) fail(`Booking failed: ${result.status}`);

    const bookingId = result.data.booking.id;
    api.getMyBookings(token);
    api.cancelBooking(token, bookingId);
}

/** Auth throughput test (register + login) */
export function authJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const passenger = newPassenger();

    const token = api.registerUser(passenger.name, passenger.email, TEST_PASSWORD);
    sleepRandom(1, 2);

    if (token) {
        const loginToken = api.loginUser(passenger.email, TEST_PASSWORD);
        if (!loginToken) fail(`Login failed for ${passenger.email}`);
    }

    sleepRandom(2, 4);
}

/** Search-only journey (coach_search.js) */
export function searchOnlyJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const stations = api.getStations();
    if (!stations?.length) return;

    const { from, to } = pickRoute(stations);
    const daysAhead = Math.floor(Math.random() * 5) + 1;
    api.searchRoutes(from.id, to.id, futureDate(daysAhead));
    sleepRandom(2, 4);
}

/** Poll seat maps repeatedly (seat_layout.js) */
export function seatMapPollJourney(baseUrl, polls = 3) {
    const api = new ApiClient(baseUrl);
    const stations = api.getStations();
    if (!stations?.length) return;

    const { from, to } = pickRoute(stations);
    const date = futureDate(1);

    const schedules = api.searchRoutes(from.id, to.id, date);
    if (!schedules?.length) return;

    for (let i = 0; i < polls; i++) {
        api.searchRoutes(from.id, to.id, date);
        sleepRandom(2, 4);
    }
}

/** Race condition: many VUs book the same seat */
export function seatRaceJourney(baseUrl) {
    const api = new ApiClient(baseUrl);
    const passenger = newPassenger();
    const token = api.registerUser(passenger.name, passenger.email, TEST_PASSWORD);
    if (!token) fail('Registration failed during race setup');

    const stations = api.getStations();
    if (!stations?.length) return;

    const { from, to } = pickRoute(stations);
    const schedules = api.searchRoutes(from.id, to.id, futureDate(1));
    if (!schedules?.length) return;

    const schedule = schedules[0];
    const seats = availableSeats(schedule.seat_map);
    if (!seats.length) return;

    sleepRandom(0.5, 1.5);

    const result = api.createBooking(
        token,
        bookingPayload({ schedule, passenger, fromStation: from, toStation: to, seats: seats[0] })
    );

    if (result.status === 201) {
        console.log(`[race] VU ${__VU} won seat ${seats[0]}`);
    } else if (result.status === 400 || result.status === 422) {
        console.log(`[race] VU ${__VU} rejected (${result.status}) — expected conflict`);
    }
}
