/** Booking flow — 20 users exercising register → book → cancel */
import { baseUrl, thresholds } from '../config/index.js';
import { bookingJourney } from '../flows/customer.js';

export const options = {
    vus: 20,
    duration: '1m',
    thresholds: thresholds.normal,
};

export default function () {
    bookingJourney(baseUrl());
}

// Re-export for stress/soak/spike scenarios
export { bookingJourney as runBookingFlow } from '../flows/customer.js';
