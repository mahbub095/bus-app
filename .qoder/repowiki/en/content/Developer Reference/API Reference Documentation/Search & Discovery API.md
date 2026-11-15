# Search & Discovery API

<cite>
**Referenced Files in This Document**
- [api.php](file://backend/routes/api.php)
- [SearchController.php](file://backend/app/Http/Controllers/API/SearchController.php)
- [CoachServicesService.php](file://backend/app/Services/CoachServicesService.php)
- [SeatMapService.php](file://backend/app/Services/SeatMapService.php)
- [Schedule.php](file://backend/app/Models/Schedule.php)
- [Route.php](file://backend/app/Models/Route.php)
- [Station.php](file://backend/app/Models/Station.php)
- [Bus.php](file://backend/app/Models/Bus.php)
- [2026_06_01_000010_create_stations_table.php](file://backend/database/migrations/2026_06_01_000010_create_stations_table.php)
- [2026_06_01_000030_create_routes_table.php](file://backend/database/migrations/2026_06_01_000030_create_routes_table.php)
- [2026_06_01_000040_create_schedules_table.php](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php)
- [SearchForm.jsx](file://frontend/src/components/SearchForm.jsx)
- [BookingPortal.jsx](file://frontend/src/components/BookingPortal.jsx)
- [scheduleCache.js](file://frontend/src/scheduleCache.js)
- [customer-api.js](file://k6/lib/customer-api.js)
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
This document describes the Search & Discovery API for bus schedules, focusing on route search, schedule filtering, price comparison, and availability checking. It covers request parameters (origin/destination, date ranges, coach type), search algorithms, result sorting, pagination behavior, example queries, performance optimization techniques, and caching strategies used by both frontend and backend.

## Project Structure
The search feature spans a small set of backend routes/controllers/services and models, plus a frontend search form and cache layer:
- Public API route exposes GET /api/search.
- Controller validates inputs and delegates to CoachServicesService.
- Service queries Schedule with related Bus, Route, Stations, and paid bookings; builds seat maps and pricing.
- Models define relationships between Station, Route, Bus, and Schedule.
- Frontend provides a search form and caches results client-side.

```mermaid
graph TB
Client["Client App"] --> API["GET /api/search"]
API --> Ctrl["SearchController.search()"]
Ctrl --> Svc["CoachServicesService.search()"]
Svc --> DB_Sched["Schedule model"]
Svc --> DB_Route["Route model"]
Svc --> DB_Station["Station model"]
Svc --> DB_Bus["Bus model"]
Svc --> SeatSvc["SeatMapService"]
SeatSvc --> Cache["Redis/Memory Cache<br/>holds + config"]
```

**Diagram sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)
- [SeatMapService.php:367-382](file://backend/app/Services/SeatMapService.php#L367-L382)

**Section sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)

## Core Components
- Public endpoint: GET /api/search under maintenance-mode middleware.
- Controller: Validates from/to/date/coach_type and returns JSON.
- Service: Filters schedules by route(s), date window, optional coach type; enriches with seat map, pricing, boarding/dropping points; sorts by departure_time ascending.
- Seat Map Service: Builds seat status map, computes pricing breakdown, formats boarding/dropping points.

Key responsibilities:
- Validation and routing: [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31), [api.php:26-32](file://backend/routes/api.php#L26-L32)
- Search algorithm and enrichment: [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)
- Seat map and pricing: [SeatMapService.php:367-382](file://backend/app/Services/SeatMapService.php#L367-L382)

**Section sources**
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)
- [SeatMapService.php:367-382](file://backend/app/Services/SeatMapService.php#L367-L382)

## Architecture Overview
End-to-end flow for a search request:

```mermaid
sequenceDiagram
participant FE as "Frontend"
participant API as "API Router"
participant CTRL as "SearchController"
participant SVC as "CoachServicesService"
participant DB as "DB (Schedules/Routes/Stations/Buses)"
participant SEAT as "SeatMapService"
FE->>API : GET /api/search?from=&to=&date=&coach_type=
API->>CTRL : route to search()
CTRL->>CTRL : validate(from,to,date,coach_type)
CTRL->>SVC : search(fromId,toId,date,coachType)
SVC->>DB : query schedules with joins
SVC->>SEAT : formatSchedulePayload(schedule,bookings)
SEAT-->>SVC : seat_map, booked_seats, boarding/dropping, pricing
SVC-->>CTRL : enriched schedule list
CTRL-->>FE : JSON array of schedules
```

**Diagram sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)
- [SeatMapService.php:367-382](file://backend/app/Services/SeatMapService.php#L367-L382)

## Detailed Component Analysis

### Endpoint: GET /api/search
- Path: /api/search
- Method: GET
- Authentication: None (public)
- Maintenance mode: Protected by CheckMaintenanceMode middleware group

Request parameters:
- from (required): station id (integer). Validated against stations table.
- to (required): station id (integer). Validated against stations table.
- date (required): journey date in YYYY-MM-DD.
- coach_type (optional): filter by bus coach type (e.g., AC or Non AC).

Response:
- Array of schedule objects including:
  - id, departure_time, arrival_time, fare
  - bus details (operator_name, coach_number, coach_type, total_seats, seat_layout, seat_layout_grid, logo_url)
  - route details (distance, duration, from, to)
  - seat_map, booked_seats, boarding_points, dropping_points
  - seat_class, pricing (per-seat breakdown)
  - available_seats_count
  - seat_bookings (only when includeSeatBookings is true internally; not exposed via this endpoint)

Sorting:
- Results are sorted by departure_time ascending.

Pagination:
- No server-side pagination is implemented for this endpoint. The service may return all matching schedules. A fallback path returns up to 50 most recent schedules when no future schedules exist and no specific date is requested.

Example requests:
- Basic route search: GET /api/search?from=1&to=2&date=2026-01-01
- Filter by coach type: GET /api/search?from=1&to=2&date=2026-01-01&coach_type=AC

Complex queries:
- Combine origin/destination with coach type and exact date to narrow results.
- Use multiple sequential searches with different dates to compare prices across days.

Error handling:
- Validation errors for missing or invalid from/to/date will be returned by Laravel’s validator.
- If no routes match the provided stations, an empty array is returned.

**Section sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)

### Search Algorithm and Filtering Logic
The search algorithm performs:
- Route identification:
  - If both from and to are provided, find routes where departure_station_id = from and arrival_station_id = to.
  - If only from is provided, find routes departing from that station.
  - If only to is provided, find routes arriving at that station.
- Date filtering:
  - If date is provided, filter schedules where departure_time falls within the start/end of that day.
  - If date is omitted, default to today and select schedules with departure_time >= start of today.
- Fallback behavior:
  - If no future schedules exist and no specific date was requested, fetch up to 50 latest schedules ordered by departure_time descending.
- Coach type filtering:
  - If coach_type is provided and not “All”, filter schedules by bus.coach_type case-insensitively.
- Enrichment:
  - For each schedule, build seat map, compute pricing, and assemble boarding/dropping points.

```mermaid
flowchart TD
Start(["Start search"]) --> BuildQuery["Build base query with eager loads"]
BuildQuery --> RouteFilter{"from/to provided?"}
RouteFilter --> |Both| RoutesBoth["Find routes by departure+arrival"]
RouteFilter --> |Only from| RoutesFrom["Find routes by departure"]
RouteFilter --> |Only to| RoutesTo["Find routes by arrival"]
RoutesBoth --> ApplyDate["Apply date filter"]
RoutesFrom --> ApplyDate
RoutesTo --> ApplyDate
ApplyDate --> HasFuture{"Any future schedules?"}
HasFuture --> |Yes| SortAsc["Order by departure_time asc"]
HasFuture --> |No and no date| Fallback["Fetch latest 50 desc"]
SortAsc --> CoachFilter{"coach_type provided?"}
Fallback --> CoachFilter
CoachFilter --> |Yes| FilterByType["Filter by bus.coach_type"]
CoachFilter --> |No| Enrich["Enrich with seat map/pricing"]
FilterByType --> Enrich
Enrich --> Return(["Return schedules"])
```

**Diagram sources**
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)

**Section sources**
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)

### Price Comparison and Availability Checking
- Fare per schedule is included in the response.
- Pricing breakdown per seat is computed by SeatMapService.pricingBreakdown using configuration values for service charge, gateway charge, and discounts.
- Availability:
  - seat_map indicates per-seat status (available, blocked, sold_* or booked_* based on gender).
  - booked_seats lists occupied seats.
  - available_seats_count counts seats marked available.
  - Holds from other users are reflected in seat_map unless explicitly disabled during certain operations.

```mermaid
classDiagram
class Schedule {
+id
+bus_id
+route_id
+departure_time
+arrival_time
+fare
+blocked_seats
}
class Bus {
+id
+operator_name
+coach_number
+coach_type
+total_seats
+seat_layout
+seat_layout_grid
+logo_url
}
class Route {
+id
+departure_station_id
+arrival_station_id
+distance
+duration
+boarding_points
+dropping_points
}
class Station {
+id
+name
}
class SeatMapService {
+formatSchedulePayload(schedule, bookings, userId)
+pricingBreakdown(seatCount, farePerSeat, applyGatewayCharges)
+buildSeatMap(schedule, bookings, userId, applyHolds)
}
Schedule --> Bus : "belongsTo"
Schedule --> Route : "belongsTo"
Route --> Station : "departureStation"
Route --> Station : "arrivalStation"
SeatMapService --> Schedule : "reads"
```

**Diagram sources**
- [Schedule.php:8-34](file://backend/app/Models/Schedule.php#L8-L34)
- [Bus.php:8-22](file://backend/app/Models/Bus.php#L8-L22)
- [Route.php:8-40](file://backend/app/Models/Route.php#L8-L40)
- [Station.php:8-23](file://backend/app/Models/Station.php#L8-L23)
- [SeatMapService.php:367-382](file://backend/app/Services/SeatMapService.php#L367-L382)

**Section sources**
- [SeatMapService.php:343-382](file://backend/app/Services/SeatMapService.php#L343-L382)
- [SeatMapService.php:194-244](file://backend/app/Services/SeatMapService.php#L194-L244)

### Data Model Relationships Relevant to Search
- Station has many routes as departure and arrival endpoints.
- Route connects departure and arrival stations and has many schedules.
- Bus has many schedules.
- Schedule belongs to Bus and Route and has many bookings.

```mermaid
erDiagram
STATION {
int id PK
string name UK
}
ROUTE {
int id PK
int departure_station_id FK
int arrival_station_id FK
string distance
string duration
json boarding_points
json dropping_points
}
BUS {
int id PK
string operator_name
string coach_number
string coach_type
int total_seats
string seat_layout
json seat_layout_grid
string logo_url
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
STATION ||--o{ ROUTE : "departure_station_id"
STATION ||--o{ ROUTE : "arrival_station_id"
BUS ||--o{ SCHEDULE : "has many"
ROUTE ||--o{ SCHEDULE : "has many"
```

**Diagram sources**
- [2026_06_01_000010_create_stations_table.php:11-15](file://backend/database/migrations/2026_06_01_000010_create_stations_table.php#L11-L15)
- [2026_06_01_000030_create_routes_table.php:14-28](file://backend/database/migrations/2026_06_01_000030_create_routes_table.php#L14-L28)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)
- [Bus.php:8-22](file://backend/app/Models/Bus.php#L8-L22)
- [Route.php:8-40](file://backend/app/Models/Route.php#L8-L40)
- [Schedule.php:8-34](file://backend/app/Models/Schedule.php#L8-L34)

**Section sources**
- [2026_06_01_000010_create_stations_table.php:11-15](file://backend/database/migrations/2026_06_01_000010_create_stations_table.php#L11-L15)
- [2026_06_01_000030_create_routes_table.php:14-28](file://backend/database/migrations/2026_06_01_000030_create_routes_table.php#L14-L28)
- [2026_06_01_000040_create_schedules_table.php:14-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L14-L30)

### Frontend Integration and Caching Strategy
- SearchForm collects from, to, date, and coach_type.
- BookingPortal calls GET /api/search with these parameters and updates local state.
- scheduleCache stores results keyed by from, to, date, coach_type with TTL-based expiration.

```mermaid
sequenceDiagram
participant UI as "SearchForm"
participant Portal as "BookingPortal"
participant Cache as "scheduleCache"
participant API as "GET /api/search"
UI->>Portal : user submits search params
Portal->>Cache : get(from,to,date,coachType)
alt cached entry exists and not expired
Cache-->>Portal : data
Portal-->>UI : render cached results
else no cache or expired
Portal->>API : fetch search results
API-->>Portal : JSON array
Portal->>Cache : set(from,to,date,coachType,data)
Portal-->>UI : render fresh results
end
```

**Diagram sources**
- [SearchForm.jsx:1-109](file://frontend/src/components/SearchForm.jsx#L1-L109)
- [BookingPortal.jsx:349-379](file://frontend/src/components/BookingPortal.jsx#L349-L379)
- [scheduleCache.js:1-64](file://frontend/src/scheduleCache.js#L1-L64)

**Section sources**
- [SearchForm.jsx:1-109](file://frontend/src/components/SearchForm.jsx#L1-L109)
- [BookingPortal.jsx:349-379](file://frontend/src/components/BookingPortal.jsx#L349-L379)
- [scheduleCache.js:1-64](file://frontend/src/scheduleCache.js#L1-L64)

## Dependency Analysis
- Route registration depends on api.php grouping public routes under maintenance-mode middleware.
- SearchController depends on CoachServicesService for business logic.
- CoachServicesService depends on Eloquent models (Schedule, Route, Station, Bus) and SeatMapService for enrichment.
- SeatMapService uses application cache for holds and configuration for pricing.

```mermaid
graph LR
API["routes/api.php"] --> CTRL["SearchController"]
CTRL --> SVC["CoachServicesService"]
SVC --> MODEL_SCHED["Schedule"]
SVC --> MODEL_ROUTE["Route"]
SVC --> MODEL_STATION["Station"]
SVC --> MODEL_BUS["Bus"]
SVC --> SEAT["SeatMapService"]
```

**Diagram sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)

**Section sources**
- [api.php:26-32](file://backend/routes/api.php#L26-L32)
- [SearchController.php:14-31](file://backend/app/Http/Controllers/API/SearchController.php#L14-L31)
- [CoachServicesService.php:19-124](file://backend/app/Services/CoachServicesService.php#L19-L124)

## Performance Considerations
- Database indexes:
  - routes: departure_station_id, arrival_station_id, composite (departure_station_id, arrival_station_id).
  - schedules: bus_id, route_id, departure_time, composite (route_id, departure_time), composite (bus_id, departure_time).
- Query efficiency:
  - Eager loading of bus, route.departureStation, route.arrivalStation, and bookings avoids N+1 queries.
  - Paid bookings scope selects only necessary columns to reduce payload size.
- Sorting and fallback:
  - Ascending sort by departure_time ensures chronological ordering.
  - Fallback fetch of latest 50 schedules prevents long waits when no future schedules exist.
- Frontend caching:
  - scheduleCache reduces repeated network calls with TTL-based entries.
- Load testing:
  - k6 scenario for coach search demonstrates high-volume lookups.

Recommendations:
- Keep date filters precise to limit time-range scans.
- Prefer specifying both from and to to leverage composite indexes on routes.
- Avoid unnecessary includes of seat_bookings unless required by admin flows.
- Tune cache TTL in scheduleCache to balance freshness vs. performance.

**Section sources**
- [2026_06_01_000030_create_routes_table.php:24-28](file://backend/database/migrations/2026_06_01_000030_create_routes_table.php#L24-L28)
- [2026_06_01_000040_create_schedules_table.php:24-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L24-L30)
- [CoachServicesService.php:21-72](file://backend/app/Services/CoachServicesService.php#L21-L72)
- [SeatMapService.php:38-47](file://backend/app/Services/SeatMapService.php#L38-L47)
- [scheduleCache.js:1-64](file://frontend/src/scheduleCache.js#L1-L64)
- [coach_search.js](file://k6/scenarios/coach_search.js)

## Troubleshooting Guide
Common issues and resolutions:
- Validation failures:
  - Missing or invalid from/to/date triggers validation errors. Ensure station ids exist and date matches YYYY-MM-DD.
- Empty results:
  - No routes match the provided stations or no schedules fall within the date window. Verify station ids and date range.
- Stale cache:
  - Frontend may show outdated results if cache TTL is too long. Clear or invalidate cache entries for the specific query.
- Seat availability discrepancies:
  - Holds from other users can mark seats as blocked. Refresh the seat map or wait for hold expiration.

Operational checks:
- Confirm routes exist for given stations.
- Validate schedule departure times relative to current time.
- Inspect cache keys and TTLs in scheduleCache.

**Section sources**
- [SearchController.php:16-21](file://backend/app/Http/Controllers/API/SearchController.php#L16-L21)
- [CoachServicesService.php:28-72](file://backend/app/Services/CoachServicesService.php#L28-L72)
- [scheduleCache.js:1-64](file://frontend/src/scheduleCache.js#L1-L64)

## Conclusion
The Search & Discovery API provides a focused, efficient interface for finding bus schedules by route and date, with optional coach type filtering. Results are enriched with seat availability and pricing information, enabling robust frontends to display real-time availability and perform price comparisons. Proper indexing, eager loading, and client-side caching ensure good performance under load. For advanced needs like pagination or multi-date batch queries, consider extending the service layer while preserving the existing validation and enrichment patterns.