---
kind: external_dependency
name: SMS.NET.BD SMS Gateway
slug: sms-net-bd
category: external_dependency
category_hints:
    - vendor_identity
scope:
    - '**'
---

### SMS.NET.BD
- Default SMS gateway (driver `smsnetbd`, default URL `https://api.sms.net.bd/sendsms`).
- Unlike other drivers it does NOT require a `sender_id`; only `api_key` and `api_url` are mandatory.
- Messages are sent as form POST with fields `api_key`, `msg`, `to`. Phone numbers are normalized to `880...` format before dispatch.