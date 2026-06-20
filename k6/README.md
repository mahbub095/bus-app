# k6 Load Testing

Performance tests for the SonyaBus booking app. Each **scenario** file is a thin runner; the real logic lives in **flows**.

## Folder structure

```text
k6/
├── config/          # URLs, credentials, shared thresholds
│   └── index.js
├── lib/             # HTTP + API clients
│   ├── http.js          # JSON parsing, headers, CSRF helpers
│   ├── customer-api.js  # Public REST API  (/api/*)
│   ├── admin-api.js     # Admin dashboard  (/admin/*, session + CSRF)
│   └── client.js        # Combined ApiClient (used by flows)
├── helpers/         # Random data, dates, route/seat pickers
│   └── index.js
├── flows/           # Reusable user journeys (the important part)
│   ├── customer.js      # browse, search, book, cancel…
│   └── admin.js         # login, logs, reports, seat toggle…
└── scenarios/       # k6 entry points — options + one flow call
    ├── smoke.js
    ├── load.js
    ├── admin.js
    └── …
```

### How to read the code

| Layer | Purpose | Example |
|-------|---------|---------|
| **scenarios/** | How many users, how long, pass/fail rules | `load.js` → 100 VUs for 4 min |
| **flows/** | What each virtual user *does* step by step | `customerLoadJourney()` |
| **lib/** | How to call each API endpoint | `api.customer.search()` |
| **config/** | Shared constants | `baseUrl()`, `ADMIN.email` |
| **helpers/** | Test data utilities | `randomEmail()`, `pickRoute()` |

## Install k6

```powershell
winget install GrafanaLabs.k6
```

Restart your terminal after install.

## Run tests

Set the target server with `BASE_URL` (default: `http://sonyabus-app.test`).

```powershell
# Quick sanity check (1 user, full booking path)
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/smoke.js

# Customer load (ramps to 100 users)
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/load.js

# Admin back-office
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/admin.js

# Full admin dashboard (reports + cancellations)
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/admin_dashboard.js

# Customers + admins together
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/combined.js
```

### All scenarios

| File | What it tests |
|------|---------------|
| `smoke.js` | One full customer booking path |
| `load.js` | Realistic customer traffic (100 VUs) |
| `stress.js` | Ramp to 200 VUs |
| `soak.js` | Steady 40 VUs over time |
| `spike.js` | Sudden surge to 100 VUs |
| `booking_flow.js` | Register → book → cancel |
| `coach_search.js` | Route search only |
| `login_api.js` | Register + login throughput |
| `homepage_load.js` | Landing page + public APIs |
| `promo_validation.js` | Coupon check endpoint |
| `seat_layout.js` | Seat map polling |
| `seat_race.js` | Concurrent booking same seat |
| `admin.js` | Admin login, logs, search, toggle |
| `admin_dashboard.js` | Full admin workflow + reports |
| `combined.js` | Customer + admin in parallel |

## Customer journey (`flows/customer.js`)

```
browseHome → searchRoutes → (50% chance) registerAndMaybeBook
                                    ↓
                            tryBookSeat → (10% chance) cancel
```

## Admin journey (`flows/admin.js`)

```
adminLogin (CSRF + session)
    → dashboard
    → booking logs + cancel logs
    → coach search
    → (optional) toggle seat block
    → (dashboard flow) reports + approve cancel
```

Admin uses **session cookies** on `/admin/api/*`, not Sanctum tokens.

## Credentials

Default admin user (from database seeder):

- Email: `superadmin@sonyabus.com`
- Password: `password123`

Change in `config/index.js` if needed.

## Adding a new test

1. Add or reuse a **flow** function in `flows/customer.js` or `flows/admin.js`.
2. Create a small **scenario** file that sets `options` and calls that flow.
3. Run with `k6 run k6/scenarios/your_test.js`.

Example scenario:

```javascript
import { baseUrl, thresholds } from '../config/index.js';
import { searchOnlyJourney } from '../flows/customer.js';

export const options = { vus: 10, duration: '30s', thresholds: thresholds.normal };

export default function () {
    searchOnlyJourney(baseUrl());
}
```
