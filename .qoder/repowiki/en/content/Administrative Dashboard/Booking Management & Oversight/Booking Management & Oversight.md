# Booking Management & Oversight

<cite>
**Referenced Files in This Document**
- [BookingController.php](file://backend/app/Http/Controllers/Admin/BookingController.php)
- [AjaxController.php](file://backend/app/Http/Controllers/Admin/AjaxController.php)
- [AdminBookingService.php](file://backend/app/Services/AdminBookingService.php)
- [BookingService.php](file://backend/app/Services/BookingService.php)
- [Booking.php](file://backend/app/Models/Booking.php)
- [bookings.blade.php](file://backend/resources/views/admin/partials/bookings.blade.php)
- [bookings.js](file://backend/resources/js/admin/bookings.js)
- [web.php](file://backend/routes/web.php)
- [2026_06_01_000050_create_bookings_table.php](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php)
- [ReportController.php](file://backend/app/Http/Controllers/Admin/ReportController.php)
- [ExcelExportService.php](file://backend/app/Services/ExcelExportService.php)
- [PaymentController.php](file://backend/app/Http/Controllers/PaymentController.php)
- [SECURITY_IMPLEMENTATION_GUIDE.md](file://SECURITY_IMPLEMENTATION_GUIDE.md)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)

## Introduction
This document explains the booking oversight and management system for administrators. It covers how admins view, filter, create, update, cancel, approve cancellations, and export bookings. It also documents the admin booking service that enforces business rules around cancellation workflows and refund-related status transitions. The system supports filtering by date range, route, passenger, and booking status, and provides live polling, inline editing, and reporting/export capabilities.

## Project Structure
The booking oversight feature spans controllers, services, models, Blade views, JavaScript, routes, migrations, and reports:

- Admin UI: Blade partial and JavaScript provide a live table with filters, pagination, inline edit, cancel request, and delete actions.
- Admin API endpoints: Ajax controller exposes JSON endpoints for logs and cancel requests.
- Business logic: Admin booking service encapsulates cancellation workflows and log formatting; shared booking service handles creation and customer-facing cancellation logic.
- Data model: Booking model defines fields, relationships, and PNR generation.
- Database schema: Bookings table migration includes indexes for performance.
- Reporting and export: Report controller and Excel export service support bulk export of booking data.
- Payment integration: Payment controller processes ZiniPay webhooks to finalize payments.

```mermaid
graph TB
subgraph "Admin UI"
V["bookings.blade.php"]
J["bookings.js"]
end
subgraph "Admin HTTP Layer"
AC["AjaxController.php"]
ABC["Admin\\BookingController.php"]
end
subgraph "Services"
ABS["AdminBookingService.php"]
BS["BookingService.php"]
EES["ExcelExportService.php"]
end
subgraph "Domain"
BM["Booking.php"]
M["Bookings Table Migration"]
end
subgraph "Reporting"
RC["ReportController.php"]
end
subgraph "Payments"
PC["PaymentController.php"]
end
V --> J
J --> AC
ABC --> BS
AC --> ABS
ABS --> BM
RC --> EES
PC --> BM
BM --> M
```

**Diagram sources**
- [bookings.blade.php:1-161](file://backend/resources/views/admin/partials/bookings.blade.php#L1-L161)
- [bookings.js:1-346](file://backend/resources/js/admin/bookings.js#L1-L346)
- [AjaxController.php:1-146](file://backend/app/Http/Controllers/Admin/AjaxController.php#L1-L146)
- [AdminBookingService.php:1-254](file://backend/app/Services/AdminBookingService.php#L1-L254)
- [BookingService.php:1-335](file://backend/app/Services/BookingService.php#L1-L335)
- [Booking.php:1-49](file://backend/app/Models/Booking.php#L1-L49)
- [2026_06_01_000050_create_bookings_table.php:1-53](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L1-L53)
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:1-67](file://backend/app/Services/ExcelExportService.php#L1-L67)
- [PaymentController.php:84-119](file://backend/app/Http/Controllers/PaymentController.php#L84-L119)

**Section sources**
- [bookings.blade.php:1-161](file://backend/resources/views/admin/partials/bookings.blade.php#L1-L161)
- [bookings.js:1-346](file://backend/resources/js/admin/bookings.js#L1-L346)
- [AjaxController.php:1-146](file://backend/app/Http/Controllers/Admin/AjaxController.php#L1-L146)
- [AdminBookingService.php:1-254](file://backend/app/Services/AdminBookingService.php#L1-L254)
- [BookingService.php:1-335](file://backend/app/Services/BookingService.php#L1-L335)
- [Booking.php:1-49](file://backend/app/Models/Booking.php#L1-L49)
- [2026_06_01_000050_create_bookings_table.php:1-53](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L1-L53)
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:1-67](file://backend/app/Services/ExcelExportService.php#L1-L67)
- [PaymentController.php:84-119](file://backend/app/Http/Controllers/PaymentController.php#L84-L119)

## Core Components
- Admin Booking Controller: Handles creating, updating, deleting, cancelling, approving cancellation requests, and initiating ZiniPay payment from the admin panel.
- Admin Ajax Controller: Exposes JSON endpoints for live booking logs and cancel request submission.
- Admin Booking Service: Implements cancellation workflow helpers, log retrieval with date-range filtering, and response formatting.
- Shared Booking Service: Provides booking creation (admin and customer), seat validation, pricing, SMS notifications, and customer cancellation logic.
- Booking Model: Defines fillable fields, casts, relationships, and PNR attribute.
- Admin UI: Blade partial renders the live table, filters, and form; JavaScript polls the API, manages pagination, and triggers actions.
- Routes: Define admin endpoints for logs and cancel requests.
- Reports and Export: Report controller and Excel export service enable bulk export of booking data.
- Payment Controller: Processes ZiniPay webhook to finalize paid bookings.

Key responsibilities:
- View and manage all bookings across schedules and routes.
- Filter by date range, route, passenger, and status.
- Create and update bookings with validation and business rules.
- Submit cancellation requests and approve them through admin workflow.
- Initiate ZiniPay payment for pending bookings.
- Export booking data to Excel.

**Section sources**
- [BookingController.php:20-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L20-L135)
- [AjaxController.php:36-94](file://backend/app/Http/Controllers/Admin/AjaxController.php#L36-L94)
- [AdminBookingService.php:58-252](file://backend/app/Services/AdminBookingService.php#L58-L252)
- [BookingService.php:28-193](file://backend/app/Services/BookingService.php#L28-L193)
- [Booking.php:12-47](file://backend/app/Models/Booking.php#L12-L47)
- [bookings.blade.php:12-159](file://backend/resources/views/admin/partials/bookings.blade.php#L12-L159)
- [bookings.js:236-346](file://backend/resources/js/admin/bookings.js#L236-L346)
- [web.php:104-105](file://backend/routes/web.php#L104-L105)
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:9-18](file://backend/app/Services/ExcelExportService.php#L9-L18)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

## Architecture Overview
The admin booking oversight flow uses a layered architecture:

- Presentation layer: Blade partial and JavaScript render the interface and handle user interactions.
- HTTP layer: Controllers validate input and delegate to services.
- Service layer: Admin booking service and shared booking service enforce business rules and orchestrate domain operations.
- Domain layer: Booking model represents the entity and relationships.
- Infrastructure: Database migration defines schema and indexes; report controller and Excel export service provide reporting; payment controller integrates with ZiniPay.

```mermaid
sequenceDiagram
participant AdminUI as "Admin UI<br/>bookings.blade.php + bookings.js"
participant AjaxC as "AjaxController.php"
participant AdminSvc as "AdminBookingService.php"
participant DB as "Database<br/>bookings table"
participant PayC as "PaymentController.php"
AdminUI->>AjaxC : GET /admin/bookings/logs?period=...
AjaxC->>AdminSvc : getBookingLogs(request)
AdminSvc->>DB : Query recent bookings with joins
DB-->>AdminSvc : Bookings collection
AdminSvc-->>AjaxC : {bookings, updated_at}
AjaxC-->>AdminUI : JSON response
AdminUI->>AjaxC : POST /admin/bookings/{id}/request-cancel
AjaxC->>AdminSvc : requestCancellation(id)
AdminSvc->>DB : Update status to CANCEL_REQUESTED
DB-->>AdminSvc : Success
AdminSvc-->>AjaxC : {success, message}
AjaxC-->>AdminUI : JSON response
AdminUI->>AdminUI : Approve cancellation via admin flow
AdminUI->>AjaxC : Approve cancel request endpoint
AjaxC->>AdminSvc : approveCancelRequest(id)
AdminSvc->>DB : Update status to CANCELLED
DB-->>AdminSvc : Success
AdminSvc-->>AjaxC : {success, message}
AjaxC-->>AdminUI : JSON response
Note over PayC,DB : Webhook finalizes payment to SOLD when completed
```

**Diagram sources**
- [bookings.blade.php:152-159](file://backend/resources/views/admin/partials/bookings.blade.php#L152-L159)
- [bookings.js:168-204](file://backend/resources/js/admin/bookings.js#L168-L204)
- [AjaxController.php:36-94](file://backend/app/Http/Controllers/Admin/AjaxController.php#L36-L94)
- [AdminBookingService.php:112-223](file://backend/app/Services/AdminBookingService.php#L112-L223)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

## Detailed Component Analysis

### Admin Booking Controller
Responsibilities:
- Create bookings via admin form with validation and optional payment URL redirection.
- Update existing bookings with field-level validation.
- Delete bookings.
- Cancel bookings by setting status to CANCEL_REQUESTED.
- Approve cancellation requests using AdminBookingService.
- Initiate ZiniPay payment for pending bookings.

Validation and behavior highlights:
- Creation validates schedule, passenger details, seats, payment method, fare, gender, boarding/dropping points, and optional status.
- Update validates passenger details, seats, fare, payment method, and status.
- Cancel ensures not already cancelled or requested before transitioning to CANCEL_REQUESTED.
- ApproveCancelRequest delegates to AdminBookingService for state enforcement.
- payAdmin checks PENDING status and ZiniPay method before creating invoice and redirecting.

```mermaid
flowchart TD
Start([Admin Request]) --> Validate["Validate Input"]
Validate --> CreateOrUpdate{"Action?"}
CreateOrUpdate --> |Create| CreateBooking["Create Booking via BookingService"]
CreateOrUpdate --> |Update| UpdateBooking["Update Fields"]
CreateOrUpdate --> |Delete| DeleteBooking["Delete Booking"]
CreateOrUpdate --> |Cancel| SetCancelRequested["Set Status = CANCEL_REQUESTED"]
CreateOrUpdate --> |Approve| ApproveCancel["Approve via AdminBookingService"]
CreateOrUpdate --> |Pay| InitZinipay["Initiate ZiniPay Invoice"]
InitZinipay --> Redirect["Redirect to Payment URL"]
SetCancelRequested --> End([Response])
ApproveCancel --> End
CreateBooking --> End
UpdateBooking --> End
DeleteBooking --> End
```

**Diagram sources**
- [BookingController.php:20-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L20-L135)

**Section sources**
- [BookingController.php:20-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L20-L135)

### Admin Ajax Controller
Responsibilities:
- Provide JSON endpoints for booking logs and cancel requests.
- Expose seat hold/release endpoints for admin seat reservation during manual booking.

Key endpoints:
- GET /admin/bookings/logs: Returns recent bookings filtered by period/custom dates.
- POST /admin/bookings/{id}/request-cancel: Submits cancellation request.
- Seat hold/release: Uses SeatMapService to reserve seats while admin fills forms.

**Section sources**
- [AjaxController.php:36-94](file://backend/app/Http/Controllers/Admin/AjaxController.php#L36-L94)
- [AjaxController.php:100-144](file://backend/app/Http/Controllers/Admin/AjaxController.php#L100-L144)

### Admin Booking Service
Responsibilities:
- Resolve date ranges for logs (today, yesterday, this week/month/year, custom).
- Retrieve recent bookings and cancel requests with related schedule/bus/route data.
- Enforce cancellation workflow: request cancellation, direct cancellation, and approval.
- Format log entries for UI consumption.

Business rules:
- Cancellation request cannot be submitted if already cancelled or requested.
- Approval requires status CANCEL_REQUESTED; otherwise returns error.
- Direct cancellation sets status to CANCELLED.

```mermaid
classDiagram
class AdminBookingService {
-resolveLogDateRange(request) array|null
-resolveCustomRange(request) array|null
+getBookingLogs(request) array
+getCancelRequestsLogs(request) array
+requestCancellation(id) array
+cancelBooking(id) array
+approveCancelRequest(id) array
#formatLogEntry(booking) array
}
class Booking {
+id
+pnr
+passenger_name
+passenger_phone
+passenger_email
+seat_numbers
+total_fare
+status
+payment_method
+created_at
+updated_at
+schedule()
+user()
}
AdminBookingService --> Booking : "reads/writes"
```

**Diagram sources**
- [AdminBookingService.php:17-56](file://backend/app/Services/AdminBookingService.php#L17-L56)
- [AdminBookingService.php:58-252](file://backend/app/Services/AdminBookingService.php#L58-L252)
- [Booking.php:12-47](file://backend/app/Models/Booking.php#L12-L47)

**Section sources**
- [AdminBookingService.php:17-56](file://backend/app/Services/AdminBookingService.php#L17-L56)
- [AdminBookingService.php:58-252](file://backend/app/Services/AdminBookingService.php#L58-L252)

### Shared Booking Service
Responsibilities:
- Create bookings for customers with seat locking and availability checks.
- Create bookings for admins without seat locking but with pricing and SMS handling.
- Handle customer-initiated cancellation: immediate cancellation for PENDING, or request cancellation for other statuses.
- Format booking data for API responses.
- Integrate with ZiniPay for payment initiation.

Key behaviors:
- Customer creation validates seat selection limits and availability, calculates total fare, applies promotions, and sends SMS on confirmed status.
- Admin creation allows explicit status overrides and auto-sets SOLD for cash unless explicitly reserved or cancelled.
- Customer cancellation enforces authorization and prevents duplicate requests.

**Section sources**
- [BookingService.php:28-131](file://backend/app/Services/BookingService.php#L28-L131)
- [BookingService.php:138-193](file://backend/app/Services/BookingService.php#L138-L193)
- [BookingService.php:200-240](file://backend/app/Services/BookingService.php#L200-L240)
- [BookingService.php:242-333](file://backend/app/Services/BookingService.php#L242-L333)

### Admin UI: Blade Partial and JavaScript
Blade partial:
- Renders a live table with columns for PNR, passenger info, journey details, coach registration, seats, fare, status, and operations.
- Provides date filter controls (period selector and custom date inputs).
- Includes an inline form for creating/editing bookings with fields for schedule, passenger details, seats, fare, payment method, and status.
- Sets up JavaScript configuration constants for API URLs and route templates.

JavaScript:
- Polls the logs API every 5 seconds when the tab is active.
- Renders rows, binds edit/cancel buttons, and manages client-side pagination.
- Builds query parameters from date filters and fetches data accordingly.
- Supports inline editing by populating the form with existing booking data.
- Auto-fetches if the bookings tab is currently displayed.

```mermaid
flowchart TD
Load(["Page Load"]) --> CheckTab{"Bookings Tab Active?"}
CheckTab --> |Yes| StartPoll["Start Polling Every 5s"]
CheckTab --> |No| Idle["Idle"]
StartPoll --> Fetch["Fetch Logs API"]
Fetch --> Render["Render Rows + Pagination"]
Render --> BindActions["Bind Edit/Cancel/Delete Actions"]
BindActions --> Wait["Wait for User Interaction"]
Wait --> ApplyFilter["Apply Date Filter"]
ApplyFilter --> Fetch
Wait --> Edit["Edit Booking"]
Edit --> PopulateForm["Populate Form Fields"]
Wait --> Cancel["Submit Cancel Request"]
Cancel --> Refresh["Refresh Table"]
```

**Diagram sources**
- [bookings.blade.php:12-159](file://backend/resources/views/admin/partials/bookings.blade.php#L12-L159)
- [bookings.js:23-346](file://backend/resources/js/admin/bookings.js#L23-L346)

**Section sources**
- [bookings.blade.php:12-159](file://backend/resources/views/admin/partials/bookings.blade.php#L12-L159)
- [bookings.js:23-346](file://backend/resources/js/admin/bookings.js#L23-L346)

### Routes
Admin endpoints relevant to booking oversight:
- POST /admin/bookings/{id}/request-cancel: Submits cancellation request via AjaxController.
- GET /admin/bookings/logs: Retrieves booking logs via AjaxController.

These routes are consumed by the admin UI JavaScript and Blade partial.

**Section sources**
- [web.php:104-105](file://backend/routes/web.php#L104-L105)

### Data Model and Schema
Booking model:
- Fillable fields include user, schedule, passenger details, boarding/dropping points, seat class, seat numbers, fare, payment method, invoice ID, and status.
- Casts total_fare to decimal.
- Relationships: belongsTo Schedule and User.
- PNR attribute generates a formatted ticket code.

Schema migration:
- Defines columns and indexes for efficient querying by user, schedule, status, email, phone, and composite keys.

```mermaid
erDiagram
BOOKING {
int id PK
int user_id FK
int schedule_id FK
string passenger_name
string passenger_phone
string passenger_email
enum passenger_gender
string boarding_point
string dropping_point
string seat_class
string seat_numbers
decimal total_fare
string payment_method
string payment_invoice_id
enum status
timestamp created_at
timestamp updated_at
}
SCHEDULE ||--o{ BOOKING : "has many"
USER ||--o{ BOOKING : "has many"
```

**Diagram sources**
- [Booking.php:12-47](file://backend/app/Models/Booking.php#L12-L47)
- [2026_06_01_000050_create_bookings_table.php:14-42](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L14-L42)

**Section sources**
- [Booking.php:12-47](file://backend/app/Models/Booking.php#L12-L47)
- [2026_06_01_000050_create_bookings_table.php:14-42](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L14-L42)

### Search and Filter Functionality
- Date range filtering: Period selector (all, today, yesterday, this week, this month, this year) and custom from/to dates.
- Route filtering: Available via report queries and preview endpoints; the admin logs endpoint focuses on recency and date range.
- Passenger filtering: Not directly exposed in the live logs endpoint; available through report queries.
- Status filtering: Not directly exposed in the live logs endpoint; available through report queries and cancel requests logs.

Implementation notes:
- AdminBookingService resolves date ranges and applies whereBetween clauses for created_at or updated_at depending on the endpoint.
- JavaScript builds query parameters and updates the live table accordingly.

**Section sources**
- [AdminBookingService.php:17-56](file://backend/app/Services/AdminBookingService.php#L17-L56)
- [AdminBookingService.php:58-105](file://backend/app/Services/AdminBookingService.php#L58-L105)
- [bookings.js:236-310](file://backend/resources/js/admin/bookings.js#L236-L310)

### Cancellation Workflows and Refund Processing
Workflows:
- Customer cancellation: Immediate cancellation for PENDING; otherwise submits a cancellation request requiring admin approval.
- Admin cancellation request: Sets status to CANCEL_REQUESTED; visible in cancel requests queue.
- Admin approval: Transitions CANCEL_REQUESTED to CANCELLED.

Refund processing:
- The system indicates refunds will be processed after approval to the original payment method.
- Payment controller finalizes payment status upon ZiniPay webhook completion.

```mermaid
stateDiagram-v2
[*] --> PENDING
PENDING --> CANCELLED : "Customer cancels immediately"
PENDING --> CANCEL_REQUESTED : "Customer requests cancellation"
CANCEL_REQUESTED --> CANCELLED : "Admin approves"
CANCEL_REQUESTED --> CANCEL_REQUESTED : "Duplicate request blocked"
CANCELLED --> [*]
```

**Diagram sources**
- [BookingService.php:200-240](file://backend/app/Services/BookingService.php#L200-L240)
- [AdminBookingService.php:112-223](file://backend/app/Services/AdminBookingService.php#L112-L223)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

**Section sources**
- [BookingService.php:200-240](file://backend/app/Services/BookingService.php#L200-L240)
- [AdminBookingService.php:112-223](file://backend/app/Services/AdminBookingService.php#L112-L223)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

### Booking Modification Capabilities
- Inline editing: Admin can edit passenger name, phone, email, seat numbers, total fare, status, and payment method via the form populated by JavaScript.
- Validation: Server-side validation ensures required fields and valid values.
- Status changes: Allowed within defined enum values; cash payment auto-sets SOLD unless explicitly reserved or cancelled.

**Section sources**
- [bookings.js:206-232](file://backend/resources/js/admin/bookings.js#L206-L232)
- [BookingController.php:54-79](file://backend/app/Http/Controllers/Admin/BookingController.php#L54-L79)
- [BookingService.php:138-193](file://backend/app/Services/BookingService.php#L138-L193)

### Bulk Operations and Export Capabilities
- Export to Excel: Report controller provides booking export endpoints that build rows and summaries and stream downloadable files via ExcelExportService.
- Bulk operations: While the live logs do not expose bulk actions, the report system enables exporting large datasets for offline analysis and subsequent bulk processing.

**Section sources**
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:9-18](file://backend/app/Services/ExcelExportService.php#L9-L18)

### Audit Logging
- Audit logging infrastructure is defined in the security implementation guide, including a migration for audit_logs with fields for action, model type/id, changes, IP address, and user agent.
- This provides a foundation for tracking booking changes and administrative actions.

**Section sources**
- [SECURITY_IMPLEMENTATION_GUIDE.md:566-603](file://SECURITY_IMPLEMENTATION_GUIDE.md#L566-L603)

## Dependency Analysis
Component dependencies:
- Admin UI depends on AjaxController endpoints and consumes JSON payloads.
- AjaxController depends on AdminBookingService for logs and cancellation requests.
- AdminBookingService depends on Booking model and Carbon for date handling.
- BookingController depends on BookingService and ZinipayService for creation and payment flows.
- ReportController depends on data/filter services and ExcelExportService for exports.
- PaymentController depends on ZinipayService and Booking model for webhook processing.

```mermaid
graph LR
UI["Admin UI"] --> AC["AjaxController"]
AC --> ABS["AdminBookingService"]
ABS --> BM["Booking Model"]
ABC["Admin BookingController"] --> BS["BookingService"]
BS --> ZP["ZinipayService"]
RC["ReportController"] --> EES["ExcelExportService"]
PC["PaymentController"] --> ZP
```

**Diagram sources**
- [AjaxController.php:36-94](file://backend/app/Http/Controllers/Admin/AjaxController.php#L36-L94)
- [AdminBookingService.php:58-252](file://backend/app/Services/AdminBookingService.php#L58-L252)
- [BookingController.php:20-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L20-L135)
- [BookingService.php:28-193](file://backend/app/Services/BookingService.php#L28-L193)
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:9-18](file://backend/app/Services/ExcelExportService.php#L9-L18)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

**Section sources**
- [AjaxController.php:36-94](file://backend/app/Http/Controllers/Admin/AjaxController.php#L36-L94)
- [AdminBookingService.php:58-252](file://backend/app/Services/AdminBookingService.php#L58-L252)
- [BookingController.php:20-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L20-L135)
- [BookingService.php:28-193](file://backend/app/Services/BookingService.php#L28-L193)
- [ReportController.php:166-197](file://backend/app/Http/Controllers/Admin/ReportController.php#L166-L197)
- [ExcelExportService.php:9-18](file://backend/app/Services/ExcelExportService.php#L9-L18)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

## Performance Considerations
- Database indexing: The bookings table includes indexes on user_id, schedule_id, status, passenger_email, passenger_phone, and composite keys to optimize common queries.
- Live polling interval: The UI polls every 5 seconds; consider adjusting based on traffic and server load.
- Log limits: Recent bookings and cancel requests are limited to 100 records to reduce payload size.
- Export streaming: Excel export streams XML content to avoid loading entire datasets into memory.

Recommendations:
- Use report queries for heavy filtering instead of live logs.
- Monitor polling frequency and adjust based on environment capacity.
- Ensure proper caching strategies for frequently accessed schedule and bus data.

**Section sources**
- [2026_06_01_000050_create_bookings_table.php:32-42](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L32-L42)
- [bookings.js:278-290](file://backend/resources/js/admin/bookings.js#L278-L290)
- [AdminBookingService.php:71-79](file://backend/app/Services/AdminBookingService.php#L71-L79)
- [ExcelExportService.php:9-18](file://backend/app/Services/ExcelExportService.php#L9-L18)

## Troubleshooting Guide
Common issues and resolutions:
- Cancellation request already submitted: Ensure the booking is not already in CANCEL_REQUESTED or CANCELLED state before submitting.
- Invalid booking status or payment method for payment: Only PENDING bookings with ZiniPay method can initiate payment.
- Network errors during cancel request: Retry the operation and verify CSRF token and Accept headers.
- Live update failed: Check network connectivity and API availability; the UI displays a failure indicator.

Operational tips:
- Verify webhook delivery for ZiniPay to ensure payment status transitions to SOLD.
- Confirm SMS notifications are sent only for confirmed statuses (PAID, SOLD, BOOKED).
- Use report previews to validate filter combinations before exporting.

**Section sources**
- [AdminBookingService.php:112-151](file://backend/app/Services/AdminBookingService.php#L112-L151)
- [BookingController.php:120-135](file://backend/app/Http/Controllers/Admin/BookingController.php#L120-L135)
- [bookings.js:168-204](file://backend/resources/js/admin/bookings.js#L168-L204)
- [PaymentController.php:90-119](file://backend/app/Http/Controllers/PaymentController.php#L90-L119)

## Conclusion
The booking oversight and management system provides a robust admin interface for viewing, filtering, creating, updating, and managing bookings. It enforces clear cancellation workflows with approval steps and integrates payment processing via ZiniPay. The system supports live polling, inline editing, and reporting/export capabilities. With well-defined database indexes and structured services, it balances usability and performance while laying groundwork for audit logging and operational reliability.