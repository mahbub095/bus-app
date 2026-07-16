/**
 * utils.js
 *
 * Shared utilities for the admin JS modules.
 */

/**
 * Safely escape a value for HTML output.
 */
export function escapeHtml(str) {
    const el = document.createElement('div');
    el.textContent = str ?? '';
    return el.innerHTML;
}

/**
 * Escape a string for use in an HTML attribute value.
 */
export function escapeAttr(str) {
    return String(str ?? '')
        .replace(/&/g,  '&amp;')
        .replace(/"/g,  '&quot;')
        .replace(/</g,  '&lt;');
}

/**
 * Format an ISO datetime string into a human-readable short form.
 */
export function formatDateTime(iso) {
    if (!iso) return 'N/A';
    return new Date(iso).toLocaleString([], {
        weekday: 'short',
        month:   'short',
        day:     '2-digit',
        year:    'numeric',
        hour:    '2-digit',
        minute:  '2-digit',
    });
}

/**
 * Format an ISO datetime string into a human-readable time string.
 */
export function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format a number/amount into BDT currency format.
 */
export function formatBdt(amount) {
    return '৳ ' + Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Substitute a placeholder in a route template with a real ID.
 */
export function buildUrl(template, id) {
    return template.replace('__ID__', encodeURIComponent(id));
}

/**
 * Map a booking status string to its badge CSS class.
 */
export function statusBadgeClass(status) {
    if (['PAID', 'SOLD', 'BOOKED'].includes(status)) return 'paid';
    if (status === 'PENDING' || status === 'CANCEL_REQUESTED') return 'pending';
    return 'cancelled';
}
