# Schedule & Timetable Management

<cite>
**Referenced Files in This Document**
- [Schedule.php](file://backend/app/Models/Schedule.php)
- [Bus.php](file://backend/app/Models/Bus.php)
- [Route.php](file://backend/app/Models/Route.php)
- [2026_06_01_000040_create_schedules_table.php](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php)
- [ScheduleController.php](file://backend/app/Http/Controllers/Admin/ScheduleController.php)
- [SeatMapService.php](file://backend/app/Services/SeatMapService.php)
- [ScheduleSeeder.php](file://backend/database/seeders/ScheduleSeeder.php)
- [BookingPortal.jsx](file://frontend/src/components/BookingPortal.jsx)
- [scheduleCache.js](file://frontend/src/scheduleCache.js)
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
This document explains the schedule and timetable management system for the bus application. It covers how schedules are created, how time slots and fares are managed, and how the scheduling engine prevents conflicts and optimizes resource utilization. It also documents the relationships between schedules, buses, routes, and pricing; recurring schedule generation via seeders; seat blocking; real-time seat holds; and client-side caching for fast schedule lookups.

## Project Structure
The scheduling domain spans backend models, a migration defining the database schema, an admin controller for CRUD operations, a service that builds seat maps and enforces availability, a seeder that generates recurring schedules, and frontend components that cache search results and manage seat reservations.

```mermaid
graph TB
subgraph "Backend"
M_Schedule["Model: Schedule"]
M_Bus["Model: Bus"]
M_Route["Model: Route"]
C_Admin["Admin Controller: ScheduleController"]
S_SeatMap["Service: SeatMapService"]
DB_Schema["Migration: schedules table"]
end
subgraph "Frontend"
UI_Booking["Component: BookingPortal"]
UI_Cache["Client Cache: scheduleCache"]
end
UI_Booking --> UI_Cache
UI_Booking --> C_Admin
C_Admin --> M_Schedule
M_Schedule --> M_Bus
M_Schedule --> M_Route
S_SeatMap --> M_Schedule
DB_Schema --> M_Schedule
```

**Diagram sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

**Section sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

## Core Components
- Schedule model: Represents a run with departure/arrival times, fare, and blocked seats. It links to a Bus and a Route and has many Bookings.
- Bus model: Describes vehicle attributes and capacity; exposes schedules relationship.
- Route model: Describes origin/destination stations and optional boarding/dropping points; exposes schedules relationship.
- Admin ScheduleController: Validates and persists schedule creation, updates, and deletions.
- SeatMapService: Builds seat availability maps, manages blocked seats, and implements seat hold/release logic using cache.
- Migration: Defines the schedules table schema and indexes.
- ScheduleSeeder: Generates recurring schedules across multiple days and routes.
- Frontend BookingPortal: Manages user seat selection, optimistic UI, and seat hold/release flows.
- Client scheduleCache: In-memory cache for search results with TTL-based expiration.

**Section sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)
- [ScheduleSeeder.php:13-63](file://backend/database/seeders/ScheduleSeeder.php#L13-L63)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

## Architecture Overview
The scheduling architecture combines data persistence (models and migration), administrative controls (controller), operational logic (service), and client-side performance optimizations (cache). The booking flow integrates with seat availability and temporary holds to prevent double-booking.

```mermaid
sequenceDiagram
participant Admin as "Admin UI"
participant Ctrl as "ScheduleController"
participant Model as "Schedule Model"
participant DB as "Database"
participant Service as "SeatMapService"
participant FE as "BookingPortal"
participant Cache as "Client scheduleCache"
Admin->>Ctrl : Create/Update/Delete Schedule
Ctrl->>Model : Persist changes
Model->>DB : Write schedule row
FE->>Cache : Get cached schedules
alt Cache miss
FE->>Ctrl : Fetch schedules (search)
Ctrl-->>FE : Return schedules
FE->>Cache : Store result with TTL
end
FE->>Service : Hold/Release seat
Service->>DB : Validate availability
Service-->>FE : Success or error
```

**Diagram sources**
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [SeatMapService.php:502-581](file://backend/app/Services/SeatMapService.php#L502-L581)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

## Detailed Component Analysis

### Data Model and Schema
- Schedule fields include bus_id, route_id, departure_time, arrival_time, fare, and blocked_seats. Times are cast to datetime; fare is decimal.
- The schedules table includes foreign keys to buses and routes, plus indexes on bus_id, route_id, departure_time, and composite indexes for common queries.

```mermaid
erDiagram
BUS {
int id PK
string operator_name
string coach_number
string coach_type
int total_seats
json seat_layout_grid
}
ROUTE {
int id PK
int departure_station_id FK
int arrival_station_id FK
decimal distance
int duration
json boarding_points
json dropping_points
}
SCHEDULE {
int id PK
int bus_id FK
int route_id FK
datetime departure_time
datetime arrival_time
decimal fare
text blocked_seats
}
BUS ||--o{ SCHEDULE : "has many"
ROUTE ||--o{ SCHEDULE : "has many"
```

**Diagram sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)

**Section sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)

### Schedule Creation and Time Slot Management
- Admin controller validates inputs: bus and route must exist; departure must be today or later; arrival must be after departure; fare must be non-negative.
- Creation stores only required fields; update mirrors validation and persists changes; delete removes the schedule.

```mermaid
flowchart TD
Start(["Admin creates/updates schedule"]) --> Validate["Validate bus_id, route_id,<br/>departure_time, arrival_time, fare"]
Validate --> Valid{"All validations pass?"}
Valid --> |No| Error["Return validation errors"]
Valid --> |Yes| Persist["Create or update Schedule record"]
Persist --> Success["Redirect with success message"]
Error --> End(["End"])
Success --> End
```

**Diagram sources**
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)

**Section sources**
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)

### Frequency Configuration and Recurring Templates
- The seeder generates schedules for each route across multiple days, rotating buses and applying fare adjustments for AC coaches. It also sets example blocked seats for demonstration.

```mermaid
flowchart TD
Init(["Initialize buses and routes"]) --> LoopDays["For each day offset (e.g., 0..7)"]
LoopDays --> LoopTimes["For each time slot template"]
LoopTimes --> AssignBus["Assign bus by index rotation"]
AssignBus --> AdjustFare["Adjust fare if AC coach"]
AdjustFare --> CreateSchedule["Create schedule with dep/arr/fare/blocked_seats"]
CreateSchedule --> NextSlot["Next time slot"]
NextSlot --> LoopTimes
LoopTimes --> Done(["Done"])
```

**Diagram sources**
- [ScheduleSeeder.php:13-63](file://backend/database/seeders/ScheduleSeeder.php#L13-L63)

**Section sources**
- [ScheduleSeeder.php:13-63](file://backend/database/seeders/ScheduleSeeder.php#L13-L63)

### Pricing and Relationship to Buses, Routes, and Bookings
- Each schedule carries a per-run fare and belongs to a specific bus and route.
- The seat map service computes pricing breakdowns based on configured charges and discounts, and returns seat class derived from coach type.

```mermaid
classDiagram
class Schedule {
+bus_id
+route_id
+departure_time
+arrival_time
+fare
+blocked_seats
+bookings()
}
class Bus {
+coach_type
+total_seats
+schedules()
}
class Route {
+boarding_points
+dropping_points
+schedules()
}
class SeatMapService {
+pricingBreakdown(seatCount, farePerSeat, applyGatewayCharges)
+seatClassForCoach(coachType)
}
Schedule --> Bus : "belongsTo"
Schedule --> Route : "belongsTo"
SeatMapService --> Schedule : "reads fare/coach_type"
```

**Diagram sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [SeatMapService.php:334-365](file://backend/app/Services/SeatMapService.php#L334-L365)

**Section sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [SeatMapService.php:334-365](file://backend/app/Services/SeatMapService.php#L334-L365)

### Conflict Prevention and Resource Utilization Engine
- Seat availability is computed by combining:
  - All valid seat codes for the bus layout.
  - Blocked seats stored on the schedule.
  - Active bookings (paid/sold/booked and recent pending within a short window).
  - Temporary holds held by other users in cache.
- Holds are implemented with a short expiry to reserve seats while users complete checkout, preventing race conditions.

```mermaid
flowchart TD
A["Build seat map for schedule"] --> B["Start with all seat codes"]
B --> C["Mark blocked seats"]
C --> D["Apply active bookings (PAID/SOLD/BOOKED + recent PENDING)"]
D --> E["Apply other users' holds from cache"]
E --> F["Return final availability map"]
```

**Diagram sources**
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)

**Section sources**
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)

### Real-Time Updates, Delay Notifications, and Automated Generation
- Real-time seat availability:
  - Frontend uses optimistic UI when selecting seats and calls hold/release endpoints.
  - Backend validates availability against DB and cache-based holds before granting a reservation.
- Delay notifications and automated schedule generation based on demand patterns are not present in the analyzed codebase. These features would require additional services, queues, and configuration beyond current implementation.

```mermaid
sequenceDiagram
participant User as "User"
participant FE as "BookingPortal"
participant API as "Backend"
participant SM as "SeatMapService"
participant Cache as "Server Cache"
User->>FE : Select seat
FE->>API : POST /seats/hold {schedule_id, seat_number}
API->>SM : holdSeat(scheduleId, seatNumber, userId)
SM->>Cache : Check existing holds
SM->>API : Validate DB availability
API-->>FE : {success, expires_at}
Note over FE : Optimistic UI shows selected seat with expiry
User->>FE : Cancel selection
FE->>API : POST /seats/release {schedule_id, seat_number}
API->>SM : releaseSeat(scheduleId, seatNumber, userId)
SM->>Cache : Remove hold
```

**Diagram sources**
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [SeatMapService.php:502-581](file://backend/app/Services/SeatMapService.php#L502-L581)

**Section sources**
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [SeatMapService.php:502-581](file://backend/app/Services/SeatMapService.php#L502-L581)

### Seasonal Adjustments and Special Holiday Scheduling
- No dedicated seasonal or holiday scheduling entities were found in the analyzed codebase. Operators can simulate these scenarios by:
  - Creating schedules with adjusted fares and dates via the admin controller.
  - Using the seeder pattern to generate special-day runs.
  - Blocking seats for maintenance or special events through the seat blocking feature.

[No sources needed since this section provides general guidance]

## Dependency Analysis
- Models:
  - Schedule depends on Bus and Route.
  - Bus and Route expose one-to-many relationships to Schedule.
- Services:
  - SeatMapService reads Schedule and related data to compute availability and pricing.
- Controllers:
  - Admin ScheduleController performs CRUD on Schedule with validation.
- Frontend:
  - BookingPortal interacts with backend endpoints for seat holds/releases and relies on client-side scheduleCache for search results.

```mermaid
graph LR
Schedule["Schedule Model"] --> Bus["Bus Model"]
Schedule --> Route["Route Model"]
SeatMap["SeatMapService"] --> Schedule
AdminCtrl["Admin ScheduleController"] --> Schedule
BookingUI["BookingPortal"] --> SeatMap
BookingUI --> Cache["Client scheduleCache"]
```

**Diagram sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [Bus.php:8-21](file://backend/app/Models/Bus.php#L8-L21)
- [Route.php:8-39](file://backend/app/Models/Route.php#L8-L39)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

**Section sources**
- [Schedule.php:8-33](file://backend/app/Models/Schedule.php#L8-L33)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [BookingPortal.jsx:209-269](file://frontend/src/components/BookingPortal.jsx#L209-L269)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)

## Performance Considerations
- Database indexing:
  - Indexes on bus_id, route_id, departure_time, and composite indexes improve query performance for schedule searches and assignments.
- Client-side caching:
  - scheduleCache stores search results keyed by origin, destination, date, and coach type with a default TTL to reduce repeated requests.
- Seat availability computation:
  - SeatMapService minimizes N+1 queries by selecting only necessary columns for paid bookings and uses cache for temporary holds to avoid heavy locking.

**Section sources**
- [2026_06_01_000040_create_schedules_table.php:24-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L24-L30)
- [scheduleCache.js:3-59](file://frontend/src/scheduleCache.js#L3-L59)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)

## Troubleshooting Guide
- Validation errors during schedule creation/update:
  - Ensure bus_id and route_id exist.
  - Ensure departure_time is today or later and arrival_time is after departure_time.
  - Ensure fare is numeric and non-negative.
- Seat not available:
  - Check if the seat is blocked on the schedule.
  - Verify no active bookings cover the seat (including recent pending within the allowed window).
  - Confirm no other user holds the seat in server cache.
- Seat hold/release issues:
  - Verify the seat code matches the bus layout.
  - Ensure the user releasing the seat is the same user who held it.
  - Check server cache for expired holds and clean up as needed.

**Section sources**
- [ScheduleController.php:10-47](file://backend/app/Http/Controllers/Admin/ScheduleController.php#L10-L47)
- [SeatMapService.php:452-500](file://backend/app/Services/SeatMapService.php#L452-L500)
- [SeatMapService.php:502-581](file://backend/app/Services/SeatMapService.php#L502-L581)

## Conclusion
The system provides robust schedule management with clear data models, validated admin operations, and a conflict-aware seat availability engine. Recurring schedules can be generated via seeders, and real-time seat holds protect against double-booking. Client-side caching improves responsiveness for schedule searches. Features such as delay notifications and automated schedule generation based on demand patterns are not implemented in the analyzed codebase and would require additional services and configuration.