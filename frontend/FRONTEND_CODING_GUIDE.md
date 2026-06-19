# Frontend Coding Guide

## Overview

This document describes the frontend architecture of the Sonyabus booking application, implemented in React with Vite.

The frontend is responsible for:
- rendering the booking portal
- selecting routes, dates, seats, and promotions
- authenticating users and showing user ticket history
- processing ZiniPay payment redirects and verification
- calling backend API endpoints exposed by the Laravel backend
- managing local UI state, cache, and form validation

---

## Project Structure

Key frontend files and folders:
- `frontend/src/App.jsx` — main application container and global state management
- `frontend/src/bookingUtils.js` — reusable booking helpers, seat mapping, pricing, and seat classes
- `frontend/src/zinipay.js` — ZiniPay payment redirect handling and verification helpers
- `frontend/src/scheduleCache.js` — client-side search cache for schedule queries
- `frontend/src/components` — presentational and form components used throughout the app

Important components:
- `BookingPortal.jsx` — main search and booking workflow
- `ScheduleList.jsx` — schedule results, selected coach details, and booking form container
- `SeatMap.jsx` — visual seat grid rendering and seat selection logic
- `AuthModal.jsx` — login/register/forgot/reset password UI
- `MyTickets.jsx` — authenticated ticket history and cancellation actions
- `UserProfile.jsx` — authenticated user profile and password update
- `OffersList.jsx` — promotion display and coupon copy actions
- `VerificationStatus.jsx` — payment verification result handling
- `BookingSuccess.jsx` — booking confirmation receipt screen

---

## Data Relationships and Payload Shapes

Frontend data objects mirror backend models and API contract.

### Station
- `id` — backend station ID
- `name` — station name
- `district` — additional location description

### Route / Schedule
The search API returns schedule objects with nested route and bus data.

Example shape:
- `id`
- `departure_time`
- `arrival_time`
- `fare`
- `bus`:
  - `id`
  - `operator_name`
  - `coach_number`
  - `coach_type`
  - `total_seats`
  - `seat_layout`
  - `seat_layout_grid`
- `route`:
  - `id`
  - `distance`
  - `duration`
  - `from`
  - `to`
- `seat_map` — object mapping seat labels to statuses
- `booked_seats` — array of occupied seat labels
- `boarding_points` / `dropping_points`
- `available_seats_count`
- `pricing` — breakdown used on the frontend

### Booking
Successful booking responses contain details such as:
- `id`
- `pnr`
- `passenger_name`
- `passenger_phone`
- `passenger_email`
- `seat_numbers`
- `total_fare`
- `payment_method`
- `status`
- `schedule` nested object

---

## Global App State (`App.jsx`)

`App.jsx` owns most cross-cutting state:
- `activeTab` — current selected view: `home`, `cancel`, `offers`, `profile`
- `toast` — user notification banner state
- `bookingSuccess` — confirmed booking payload shown on receipt screen
- `verificationStatus` — ZiniPay payment verification state while redirecting
- `paymentFailed` — payment failure state and reason
- `authUser` / `authToken` — authentication context persisted in localStorage
- `showAuthModal` / `authMode` — authentication modal visibility and mode
- `siteSettings` — admin-provided site metadata and maintenance config

App-level helpers:
- `showToast()` — central notification rendering
- `authHeaders()` — builds request headers including bearer token
- `persistAuth()` / `clearAuth()` — localStorage persistence and teardown
- `openAuthModal()` / `closeAuthModal()` — auth flow control
- `handleAuthSubmit()` — handles login, register, forgot password, and reset password requests
- `handleLogout()` — logs out user and clears local auth state

Lifecycle and side effects:
- fetch site settings once on mount
- load persisted auth state from localStorage
- call `handleZiniPayRedirect()` on mount to parse query string payment results
- refresh authenticated user data via `/auth/me` when token is present
- update document title, favicon, and SEO meta tags based on `siteSettings`

---

## API Integration

The frontend uses direct `fetch()` calls to backend API endpoints.

### Public endpoints
- `GET /api/site-settings` — loads site metadata, maintenance mode, and SEO values
- `GET /api/stations` — loads station list for search filters
- `GET /api/search` — finds available schedules for `from`, `to`, `date`, and `coach_type`
- `GET /api/promotions` — loads current coupon offers
- `GET /api/promotions/check?code=...` — validates promo code
- `GET /api/bookings/public/{id}` — loads booking receipt data after payment redirect

### Authenticated customer endpoints
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/password`
- `POST /api/bookings`
- `GET /api/bookings/mine`
- `POST /api/bookings/{id}/cancel`

### Payment-related endpoints
- `POST /api/payment/webhook` — called after ZiniPay redirects back for payment verification

---

## Booking Portal Logic (`BookingPortal.jsx`)

This component drives the search/book flow.

### Search flow
- Maintains `searchParams` for `from`, `to`, `date`, and `coachType`
- Uses `stations` from backend to populate select inputs
- Validates inputs before requesting schedules
- Caches search results in `scheduleCache` for 60 seconds to avoid duplicate backend calls
- Uses `isFromCache` and `lastFetched` to indicate freshness
- If cached query exists and not forced refresh, displays cached results immediately

### Selection flow
- `selectedSchedule` holds the currently expanded schedule
- `selectedSeats` stores seat labels chosen by the user
- `boardingPoint` and `droppingPoint` default from schedule points and update when schedule changes
- `promoInput` and `appliedPromo` store coupon information
- `passengerDetails` stores name, phone, email, payment method, and is prefilled when `authUser` is present

### Seat map refresh
- after selecting a schedule, a periodic refresh fetches fresh seat data every 5 seconds
- this keeps the seat map up-to-date with the latest backend booking state

### Promo code handling
- `handleApplyPromo()` validates the entered promo code via `/api/promotions/check`
- on success, stores promo data locally for booking submission

### Booking submission (`handleConfirmBooking`)
- requires authenticated users via `requireAuth()`
- validates inputs: seats selected, passenger name/phone/email, boarding/dropping selection
- sends POST request to `/api/bookings`
- handles unauthorized state by clearing auth and prompting login
- if backend returns `payment_url`, redirects browser to ZiniPay
- if booking completes immediately, stores `bookingSuccess` and resets local booking form
- refreshes schedule search results after a successful booking

---

## Seat Map and Seat Logic

### `seatMap` data
- provided by the backend as `schedule.seat_map`
- `bookingUtils.getSeatMap()` also builds a fallback map from schedule and bus layout if needed
- statuses include `available`, `blocked`, `booked_m`, `booked_f`, `sold_m`, `sold_f`

### Seat rendering (`SeatMap.jsx`)
- renders seat grid based on `schedule.bus.seat_layout_grid` if available
- falls back to a generated layout based on `seat_layout` values such as `2+2`, `1+2`, `sleeper`, `2+2_last5`
- seat cells are clickable when selectable
- selection toggles on click and prevents selecting more than 4 seats

### Seat utilities (`bookingUtils.js`)
- `SEAT_STATUS_LABELS` maps seat state to text labels
- `seatStatusClass()` returns CSS class names for seat styling
- `isSeatSelectable()` determines whether a seat can be selected
- `calcPricing()` mirrors backend pricing breakdown logic and supports gateway charges and discounts
- `formatBdt()` formats currency for display
- `getSeatMap()` reconstructs seat statuses if the backend response is missing seat_map

---

## Authentication Flow

Authentication is handled in `App.jsx` and driven through `AuthModal.jsx`.

### Auth modal modes
- `login` — existing user sign-in
- `register` — create a new account
- `forgot` — send password reset code
- `reset` — apply reset code and new password

### Auth state persistence
- `authUser` and `authToken` are stored in localStorage keys:
  - `sonyabus_auth_token`
  - `sonyabus_auth_user`
- on app mount, saved auth data is restored and `/api/auth/me` verifies the token

### Auth controls
- when booking, canceling, or updating profile, components call `requireAuth()` to prompt login if needed
- logout calls `/api/auth/logout` and clears stored auth state

---

## Payment Flow and ZiniPay Handling

### External ZiniPay helper (`zinipay.js`)
- `ziniPayCreatePayment()` — builds payload for ZiniPay payment creation
- `ziniPayVerifyPayment()` — verifies invoice status with ZiniPay API
- `handleZiniPayRedirect()` — inspects URL query params after redirect back to the React app

### Redirect query handling
- if `invoiceId` exists, verifies payment via ZiniPay and then posts to `/api/payment/webhook`
- if `payment=success` and `booking_id` exist, loads public booking receipt from `/api/bookings/public/{id}`
- if `payment=cancelled` or `payment=failed`, sets failure state for the UI

### Verification UI (`VerificationStatus.jsx`)
- shows loading state while backend and ZiniPay verification occur
- displays success or failure messaging
- on success, optionally fetches booking receipt and updates UI

---

## Authenticated User Pages

### `MyTickets.jsx`
- loads current user bookings from `/api/bookings/mine`
- allows cancellation requests via `/api/bookings/{id}/cancel`
- renders each booking with status, route, bus, PNR, and actions
- supports PDF export using `jsPDF` loaded dynamically at runtime

### `UserProfile.jsx`
- authenticated password update via `/api/auth/password`
- requires current password plus new password confirmation
- updates local auth user state on success

---

## Auxiliary UI Components

### `AuthModal.jsx`
- renders the modular auth form
- handles mode switching without leaving the page
- collects user credentials, reset code, and password confirmation

### `OffersList.jsx`
- fetches promotions and renders coupon cards
- copies codes to clipboard with user feedback

### `BookingSuccess.jsx`
- displays a receipt after a successful booking
- includes PNR details, passenger info, route, payment method, and fare summary
- supports print action through browser print API

### `VerificationStatus.jsx`
- renders payment verification results before the normal portal screen
- shows a success path that can load the booking receipt

### `ScheduleList.jsx`
- renders matched schedule cards
- toggles `SeatMap` expansion for the selected schedule
- includes refresh and cached result indicators

### `SearchForm.jsx`
- presents route search inputs and validation
- posts search requests when the user clicks search

---

## Client-side Cache

`frontend/src/scheduleCache.js` provides a lightweight cache for schedule searches.

Cache behavior:
- key is built from `from`, `to`, `date`, and `coachType`
- TTL is 60 seconds by default
- `getEntry()` returns both `data` and `timestamp`
- `set()` stores responses and timestamp
- `invalidate()` removes a specific cache entry
- `clear()` empties all cached entries

This cache reduces repeated requests for identical search parameters and lets the UI show whether results are cached or live.

---

## Styling and UX Patterns

- CSS classes are applied with semantic names such as `btn`, `search-card`, `bus-card`, `ticket-wrapper`, and `status-available`
- toast notifications are centrally managed by `App.jsx`
- seat selection UI uses a combination of status classes and click handlers for interactive seat maps
- error and success handling is kept local to each component where network responses are processed
- the app prefers progressive enhancement: if backend data fails, the UI still displays a sensible error

---

## Important Coding Notes

- Most async calls use `fetch()` directly rather than a shared HTTP client
- Authentication headers are built inside each component via `authHeaders()` helper functions
- Form validation is done in React before submission and relies on backend validation for security
- Payment handling mixes frontend redirect parsing with backend webhook updates
- Seat map layout is generated on the frontend if backend layout metadata is incomplete

---

## Suggested Improvements

- centralize `fetch()` request logic into a reusable API client wrapper
- unify auth token handling into a context provider
- extract common validation helpers for booking and user forms
- turn seat map rendering into a smaller presentational component with clearer layout abstraction
- support server-side error message normalization for easier UI consumption

---

## Files to Inspect for Full Logic

- `frontend/src/App.jsx`
- `frontend/src/components/BookingPortal.jsx`
- `frontend/src/components/ScheduleList.jsx`
- `frontend/src/components/SeatMap.jsx`
- `frontend/src/bookingUtils.js`
- `frontend/src/zinipay.js`
- `frontend/src/scheduleCache.js`
- `frontend/src/components/AuthModal.jsx`
- `frontend/src/components/MyTickets.jsx`
- `frontend/src/components/UserProfile.jsx`
- `frontend/src/components/OffersList.jsx`
- `frontend/src/components/VerificationStatus.jsx`
- `frontend/src/components/BookingSuccess.jsx`
