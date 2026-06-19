/**
 * k6 Load Testing Utilities: Helper functions
 */

// Helper to choose a random item from an array
export function randomItem(array) {
    if (!array || array.length === 0) return null;
    const index = Math.floor(Math.random() * array.length);
    return array[index];
}

// Helper to generate a random string
export function randomString(length = 8) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

// Helper to generate a random passenger name
export function randomName() {
    const firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Rahim', 'Karim', 'Abul', 'Fatima', 'Sumaiya', 'Tareq'];
    const lastNames = ['Smith', 'Doe', 'Johnson', 'Williams', 'Brown', 'Jones', 'Khan', 'Chowdhury', 'Hasan', 'Ahmed', 'Rahman', 'Islam'];
    return `${randomItem(firstNames)} ${randomItem(lastNames)}`;
}

// Helper to generate a random email address
export function randomEmail() {
    const ts = Date.now();
    const rand = randomString(5);
    return `loadtest_${ts}_${rand}@sonyabus-test.com`;
}

// Helper to generate a random phone number
export function randomPhone() {
    const prefixes = ['017', '018', '019', '015', '016', '013', '014'];
    const prefix = randomItem(prefixes);
    let rest = '';
    for (let i = 0; i < 8; i++) {
        rest += Math.floor(Math.random() * 10);
    }
    return `${prefix}${rest}`;
}

// Helper to format a date as YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Helper to get today's date in YYYY-MM-DD format
// Helper to get a date offset by 'days' in the future
export function getFutureDateString(days = 1) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return formatDate(date);
}

// Seats that can be blocked/unblocked by admin (not sold/booked/pending)
export function getToggleableSeats(seatMap = {}) {
    return Object.keys(seatMap).filter((seatCode) => {
        const status = seatMap[seatCode];
        return status === 'available' || status === 'blocked';
    });
}
