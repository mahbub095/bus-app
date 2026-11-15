---
kind: external_dependency
name: ZiniPay Payment Gateway
slug: zinipay
category: external_dependency
category_hints:
    - vendor_identity
    - auth_protocol
scope:
    - '**'
---

### ZiniPay
- Primary online payment provider for bus bookings. Integration lives in `App\Services\ZinipayService`.
- Two operations: create invoice (`POST /v1/payment/create`) returns `payment_url` + `invoice_id`; verify status (`POST /v1/payment/verify`) returns a `status` string such as COMPLETED/PENDING/FAILED.
- Frontend calls are driven by `VITE_ZINIPAY_API_KEY` env var and `frontend/src/zinipay.js`.