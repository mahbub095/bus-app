/** Test data generators and domain helpers */
import { sleep } from 'k6';
import { DEFAULT_ROUTE } from '../config/index.js';

const FIRST_NAMES = ['John', 'Jane', 'Michael', 'Emily', 'Rahim', 'Karim', 'Fatima', 'Sumaiya'];
const LAST_NAMES = ['Smith', 'Khan', 'Chowdhury', 'Hasan', 'Ahmed', 'Rahman', 'Islam'];
const PHONE_PREFIXES = ['017', '018', '019', '015', '016'];

export function sleepRandom(min, max) {
    sleep(min + Math.random() * (max - min));
}

export function randomItem(list) {
    if (!list?.length) return null;
    return list[Math.floor(Math.random() * list.length)];
}

export function randomString(length = 8) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let out = '';
    for (let i = 0; i < length; i++) {
        out += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return out;
}

export function randomName() {
    return `${randomItem(FIRST_NAMES)} ${randomItem(LAST_NAMES)}`;
}

export function randomEmail() {
    return `loadtest_${Date.now()}_${randomString(5)}@sonyabus-test.com`;
}

export function randomPhone() {
    let digits = '';
    for (let i = 0; i < 8; i++) {
        digits += Math.floor(Math.random() * 10);
    }
    return `${randomItem(PHONE_PREFIXES)}${digits}`;
}

export function futureDate(daysAhead = 1) {
    const date = new Date();
    date.setDate(date.getDate() + daysAhead);
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

/** Pick from/to stations (defaults to Dhaka → Chittagong when seeded) */
export function pickRoute(stations, preferredFrom = DEFAULT_ROUTE.from, preferredTo = DEFAULT_ROUTE.to) {
    if (!stations?.length) return { from: null, to: null };

    const findByName = (name) => stations.find((s) => s.name.toUpperCase() === name);

    const from = findByName(preferredFrom) || stations[0];
    const to = findByName(preferredTo) || stations.find((s) => s.id !== from.id) || from;

    return { from, to };
}

export function availableSeats(seatMap = {}) {
    return Object.keys(seatMap).filter((code) => seatMap[code] === 'available');
}

export function toggleableSeats(seatMap = {}) {
    return Object.keys(seatMap).filter((code) => {
        const status = seatMap[code];
        return status === 'available' || status === 'blocked';
    });
}

/** Build a booking payload from schedule + passenger info */
export function bookingPayload({ schedule, passenger, fromStation, toStation, seats }) {
    const boarding = schedule.boarding_points?.[0]?.value || fromStation?.name || '';
    const dropping = schedule.dropping_points?.[0]?.value || toStation?.name || '';

    return {
        schedule_id: schedule.id,
        passenger_name: passenger.name,
        passenger_phone: passenger.phone,
        passenger_email: passenger.email,
        seat_numbers: Array.isArray(seats) ? seats.join(',') : seats,
        payment_method: passenger.paymentMethod || 'cash',
        passenger_gender: passenger.gender || 'M',
        boarding_point: boarding,
        dropping_point: dropping,
    };
}

export function newPassenger(overrides = {}) {
    return {
        name: randomName(),
        email: randomEmail(),
        phone: randomPhone(),
        gender: Math.random() > 0.5 ? 'M' : 'F',
        paymentMethod: 'cash',
        ...overrides,
    };
}
