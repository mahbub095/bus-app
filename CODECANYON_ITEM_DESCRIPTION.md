# JourneyGo — Bus Ticket Booking System

> CodeCanyon item description. Paste into the **Description** tab of your item submission
> (CodeCanyon accepts plain text / HTML — convert the markdown headings to `<h3>` if needed).
>
> **Before publishing, replace:** `support@yourdomain.com`, the demo URLs, and the
> documentation link. Anything inside `[[double brackets]]` must be edited.

---

## KEY FEATURES

### User App (React 19 · responsive web)

- **Easy Bus Booking** — search by station, journey date and coach type, then book in seconds.
- **Real-Time Seat Availability** — live seat map with booked / available / selected states.
- **Gender-Aware Seat Map** — booked and sold seats are colour-coded by passenger gender (M/F) with a live legend.
- **Multiple Payment Options** — ZiniPay checkout (bKash and other local rails) with callback verification and instant status sync.
- **Instant PNR & Invoice** — printable ticket invoice generated the moment payment is confirmed.
- **Booking History** — “My Tickets” lists past and upcoming journeys with status tracking.
- **Cancel Requests** — request a cancellation from the app; the admin approves or rejects it.
- **Promo Codes & Offers** — validate coupons at checkout with live discount preview.
- **SMS & Email Alerts** — booking confirmations and password-reset codes through configurable gateways.
- **Dark / Light Theme** — theme switch that persists per visitor.
- **Fully Responsive** — one codebase for desktop, tablet and mobile browsers.
- **Branded by Admin** — logo, favicon, titles, SEO tags, footer and maintenance screen are database-driven.

### Admin Portal (Laravel 12 · Blade dashboard)

The Admin Portal is the backbone of your bus booking business — full control over operations,
customers and revenue from a single console.

- **Dashboard Overview** — revenue, confirmed bookings, pending, cancelled, cancel requests and
  average fare, plus booking-status, payment-method, daily-revenue, daily-booking, coach-type and
  top-route charts in real time.
- **Coach Services** — search live departures, inspect the seat map and block/hold seats for
  walk-in customers.
- **Ticket Management** — bookings log with PNR/contact search, on-behalf-of booking creation,
  status transitions and refund tracking.
- **Cancel Requests** — approve or reject customer cancellation requests with a full audit trail.
- **Route & Schedule Management** — stations, coaches, routes (with route points), schedules and
  fares, including seat-layout templates per coach.
- **Promotions & Marketing** — create promo codes, set discount type/value, limits and expiry.
- **Users & Roles** — super-admin / admin / customer roles with per-menu permissions;
  user creation is restricted to super admins.
- **Reports & Analytics** — 11 report types (selling, booking, cancel, cancellation, passenger,
  payment, refund, revenue, route sales, seat occupancy, agent sales) with **PDF and Excel export**.
- **Site Settings** — branding, favicon/logo upload, footer links, SEO and Open Graph metadata,
  maintenance mode.
- **Integrations & Gateways** — SMTP mail (with test send), multi-driver SMS gateway with
  templates (SMS.NET.BD, BulkSMSBD), ZiniPay credentials.
- **Session & Security Policy** — configurable admin session expiry, license gate, login throttling.
- **License Activation** — Envato purchase-code verification built into the install wizard.
- **One-Click Installer** — three-step web wizard (license → database → finish) with demo seeding.

---

## PACKAGE CONTENTS

```
journeygo-codecanyon-v1.1.0/
├── backend/          # Laravel 12 — API + Blade admin dashboard
├── frontend/         # React 19 + Vite — customer app
├── documentation/    # index.html — installation & user guide
├── k6/               # optional load-test suite
├── changelog.txt
├── license.txt
└── README.md
```

- **Documentation** — available online: [[View Doc]]
- **Server App** — admin panel + backend API source (Laravel 12)
- **Customer App** — React 19 booking front-end source (Vite)

There are **no Flutter / native mobile apps** in this package — the customer experience is a
responsive React web app, so there is nothing to publish to App Store or Play Store.

Feel free to connect with us if you have any questions via **support@yourdomain.com**

---

## SUPPORT

### For customers with a Regular License

Please note that we do not support setting up your server or your local development
environment. You can use the demo application to try every feature before purchasing.

Installation and customization services are available upon request — contact
**support@yourdomain.com**.

Customers purchasing this script should have prior technical knowledge of PHP / Laravel and
web-server setup.

### For customers with an Extended License

We provide support for setting up your hosting (shared cPanel / VPS), guiding you through
Composer, Node and database configuration, and we assist with payment-gateway issues,
notification problems, adding new languages, etc. Our support team cannot assist with issues
caused by your own customizations.

We aim for a fast reply, but the response time may be up to **5 business days**. Requests that
fall outside the supported scope will slow our reply time — please investigate first before
opening a ticket or posting a comment.

Before contacting our team, please refer to the documentation bundled in the package
(`documentation/index.html`).

Refunds are not possible once the item has been downloaded. For more information, please refer
to Envato’s refund policy: <https://themeforest.net/page/author_refund_policy>

---

## REQUIREMENTS

- **PHP 8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (or `imagick`)
- **Composer 2.x**
- **Node.js 20.19+ / 22 LTS** and npm (Vite 8 requirement)
- **MySQL 8.0+ / MariaDB 10.4+** (SQLite also works for evaluation)
- Web server: Apache (`.htaccess` included) or Nginx
- Optional account keys: **ZiniPay** (payments), **SMS.NET.BD / BulkSMSBD** (SMS),
  **Envato** personal token + purchase code (license activation)
- Shared cPanel hosting works; a small VPS (Ubuntu 22.04) is recommended for production

---

## DEMO

- Customer app: `[[https://demo.yourdomain.com]]`
- Admin portal: `[[https://api.yourdomain.com/admin/login]]`

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `superadmin@journeygo.com` | `password123` |
| Admin | `admin@journeygo.com` | `password123` |
| Customer | `user@journeygo.com` | `password123` |

> Demo credentials are seeded for evaluation only — change every password immediately after
> installation.

---

## CHANGELOG

See `changelog.txt` in the package.
