---
kind: external_dependency
name: k6 Load Testing Suite
slug: k6
category: external_dependency
category_hints:
    - framework_behavior
scope:
    - '**'
---

### k6
- Dedicated load-testing suite separate from the app: scenarios live under `k6/scenarios/` (smoke, soak, spike, stress, combined, booking flow, seat layout/race, promo validation, login, admin, homepage load), helpers under `k6/helpers/`, and shared HTTP client/API clients under `k6/lib/`.
- Uses a shared `k6/config/index.js` and `k6/lib/client.js` for base URL/auth setup; test scripts import these rather than duplicating request logic.