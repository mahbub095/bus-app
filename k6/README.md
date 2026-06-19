# Performance & Load Testing Suite with k6

This directory contains the k6 performance testing framework for the Sonya Bus Booking Application. The tests simulate realistic customer behaviors including route search, authentication/registration, seat booking, PNR validation, and ticket cancellation, as well as admin back-office operations.

## Directory Layout

```text
k6/
├── README.md            # This documentation
├── utils/
│   ├── api.js           # API client wrapper encapsulating both customer and admin endpoints
│   └── helpers.js       # Random generators for names, emails, phones, and dates
└── scenarios/
    ├── smoke.js         # Single VU verification test to check endpoint correctness
    ├── load.js          # Ramps to 100 VUs representing moderate customer traffic
    ├── stress.js        # Ramps to 2000 VUs to determine system limits & bottlenecks under load
    ├── admin.js         # Simulates concurrent admin operators searching services and blocking seats
    ├── admin_dashboard.js # Full admin dashboard flow including reports and cancel workflows
    └── combined.js      # Multi-scenario run executing both customer and admin traffic in parallel
```

---

## Getting Started

### 1. Install k6

k6 is a standalone CLI binary. On Windows, you can install it using `winget`:

```powershell
winget install GrafanaLabs.k6
```

*Note: Restart your terminal after installation to ensure the `k6` executable is in your shell's environment `PATH`.*

---

## Running the Scenarios

By default, the scripts point to `http://sonyabus-app.test`. You can override the target URL using the `BASE_URL` environment variable.

### 1. Smoke Test (Verification)
Runs 1 virtual user for a single iteration. Use this to ensure all APIs are working and no breaking code exists in the test script.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/smoke.js
```

### 2. Customer Load Test (Ramps up to 100 VUs)
Simulates realistic customer workload over ~4 minutes to establish a performance baseline. Ramps up, holds, and ramps down.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/load.js
```

### 3. Stress Test (Ramps up to 2000 VUs)
Pushes the system to its limits (scaling up to 2000 concurrent users) to test database locking, thread-pool queues, and potential memory leaks.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/stress.js
```

### 4. Admin Scenario (5 VUs)
Simulates administrative portal operations: session login, dashboard, `/admin/api/*` AJAX logs/search/toggle, and reports.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/admin.js
```

### 4b. Admin Dashboard Scenario (5 VUs)
Full back-office flow: logs, coach search with `seat_bookings`, seat toggle, reports, cancel approve/cancel API.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/admin_dashboard.js
```

### 5. Combined Load Test (Multi-Scenario)
Runs both the customer traffic ramp (up to 100 VUs) and 3 constant admin operators concurrently to model complete real-world activity.

```powershell
k6 run --env BASE_URL="http://sonyabus-app.test" k6/scenarios/combined.js
```

---

## Simulating User Journeys

To prevent database bloating and provide highly realistic load modeling, the scenarios distribute tasks using **probabilistic branching** and **think-time pacing**:

- **Customer Journey (`load.js` & `stress.js`)**:
  - **100% of users**: Query station list, check promotions, and search routes.
  - **50% of users**: Proceed to register a new account, retrieve their auth profile, and fetch their booking list.
  - **25% of users**: Filter search results, select 1-2 random available seats, and successfully book a ticket (via Cash).
  - **2.5% of users** (10% of bookings): Request cancellation of their ticket, invoking database status updates.
  - **2-5 seconds think-time**: Embedded between operations to simulate natural human delays.

- **Admin Journey (`admin.js` & `admin_dashboard.js`)**:
  - Authenticates via session cookies (`POST /admin/login`) and parses HTML CSRF tokens.
  - Views the Blade dashboard (`GET /admin`).
  - Queries session AJAX endpoints under `/admin/api/*` (booking logs, cancel-request logs, coach search, seat toggle).
  - Approve-cancel uses form POST to `/admin/bookings/{id}/approve-cancel` (redirect response).
  - Reports use `/admin/reports/*` preview and export routes.
  - Note: Sanctum `/api/admin/*` routes were removed; all admin dashboard load tests use session auth only.
