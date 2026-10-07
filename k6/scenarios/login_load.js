/**
 * লোড টেস্ট — Login Phase
 *
 * উদ্দেশ্য: ধীরে ধীরে ২০,০০০ VU পর্যন্ত লোড বাড়িয়ে লগইন এন্ডপয়েন্টের
 *           রেসপন্স টাইম, এরর রেট ও থ্রুপুট পরিমাপ করা।
 *
 * চালানোর নিয়ম:
 *   k6 run k6/scenarios/login_load.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

// ── কনফিগ ─────────────────────────────────────────────────────────────────────
const BASE_URL = 'http://127.0.0.1:8000';
const EMAIL    = 'superadmin@sonyabus.com';
const PASSWORD = 'password123';

// ── কাস্টম মেট্রিক্স ─────────────────────────────────────────────────────────
const loginDuration = new Trend('login_duration', true);
const requestCount  = new Counter('total_requests');
const errorRate     = new Rate('error_rate');

// ── টেস্ট কনফিগারেশন ─────────────────────────────────────────────────────────
export const options = {
    scenarios: {
        load: {
            executor: 'ramping-vus',
            stages: [
                { duration: '2m', target: 200   },  // ধীরে রেম্প-আপ
                { duration: '5m', target: 20000 },  // পিক লোড
                { duration: '2m', target: 0     },  // রেম্প-ডাউন
            ],
            gracefulRampDown: '30s',
        },
    },

    thresholds: {
        'login_duration':    ['p(95)<5000'],
        'error_rate':        ['rate<0.05'],
        'http_req_duration': ['p(90)<4000', 'p(95)<5000'],
    },
};

// ── Main ──────────────────────────────────────────────────────────────────────
export default function () {

    // ধাপ ১: লগইন পেজ থেকে CSRF টোকেন নেওয়া
    const pageRes = http.get(`${BASE_URL}/admin/login`);
    requestCount.add(1);

    const csrfToken = pageRes.html().find('input[name="_token"]').attr('value');
    if (!csrfToken) return;

    sleep(1);

    // ধাপ ২: email ও password দিয়ে লগইন
    const loginRes = http.post(
        `${BASE_URL}/admin/login`,
        { _token: csrfToken, email: EMAIL, password: PASSWORD },
        {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'text/html',
            },
            redirects: 5,
        }
    );
    requestCount.add(1);
    loginDuration.add(loginRes.timings.duration);

    const ok = check(loginRes, {
        'Login → 200':         (r) => r.status === 200,
        'Login response < 5s': (r) => r.timings.duration < 5000,
        'No server error':     (r) => r.status < 500,
    });
    errorRate.add(!ok);

    sleep(1);
}
