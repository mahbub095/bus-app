---
kind: business_term
name: Business Glossary
category: business_term
scope:
    - '**'
---

### SonyaBus
- Definition：The product name of this bus ticket booking system, distributed as a CodeCanyon/Envato market item (v1.1.0).
- Aliases：sonyabus

### Seat Hold
- Definition：A temporary reservation that locks selected seats for a limited window (2 minutes) while a customer completes checkout, preventing other buyers from taking the same seats.
- Aliases：hold、seats.hold

### PNR
- Definition：Passenger Name Record identifier generated for each confirmed booking, formatted as `SE` followed by a zero-padded 5-digit booking id (e.g. `SE00001`). Used in SMS templates and ticket documents.
- Aliases：pnr

### Logo URL
- Definition：A `longText` column on the `buses` table storing the coach/bus logo as a base64 data URL, introduced by merging migrations `2026_10_09_000000_add_logo_url_to_buses_table` and `2026_10_09_000001_change_logo_url_to_text_in_buses` into the base `create_buses_table` migration.
- Aliases：logo_url

### Mine Bookings
- Definition：The paginated endpoint `GET /api/bookings/mine` returning the authenticated user's own bookings with `page`/`per_page` params (default 10, max 50) and a standard Laravel paginated payload shape (`data`, `current_page`, `last_page`, `total`).
- Aliases：my tickets、mine

### Maintenance Mode
- Definition：An admin-controlled full-page downtime state that blocks normal site access and shows a maintenance message instead of the application.
- Aliases：maintenance

### Promo Codes
- Definition：Flat-discount coupon mechanism with expiry dates and usage limits applied at booking time.
- Aliases：promotions、promo codes

### Role-Based Access Control
- Definition：Three-tier authorization model: Super Admin (full access), Admin (menu-scoped permissions), and Customer (booking-only access).
- Aliases：RBAC、roles
