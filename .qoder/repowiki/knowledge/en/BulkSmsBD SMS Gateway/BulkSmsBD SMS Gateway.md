---
kind: external_dependency
name: BulkSmsBD SMS Gateway
slug: bulksmsbd
category: external_dependency
category_hints:
    - vendor_identity
scope:
    - '**'
---

### BulkSmsBD
- Alternative SMS driver (`driver = 'bulksmsbd'`) supported by `SmsGatewayService`.
- Sends form POST with fields `api_key`, `type=text`, `number`, `senderid`, `message`.
- Requires both `api_key` and `sender_id` (unlike SMS.NET.BD).