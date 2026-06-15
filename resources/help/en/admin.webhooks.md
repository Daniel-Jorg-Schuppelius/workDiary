---
title: "Webhooks"
topic: admin.webhooks
version: 1
audience:
    - admin
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Webhooks send outgoing event notifications to external systems (e.g. an
ERP, an automation platform or your own tool). Whenever a subscribed
event occurs, WorkDiary delivers a signed JSON payload via HTTPS `POST`
to your URL.

Typical flow:

1. **Create a webhook**: enter a label and target URL (HTTPS).
2. **Subscribe to events** (checkboxes). Only selected events trigger a
   delivery.
3. The **signing key** is shown once in plaintext — copy it now.
   Afterwards it is only stored encrypted and can be rotated when needed.
4. Use **Send test event** to verify reachability and signature checking.

## Payload

The payload is deliberately minimal and light on personal data:

```json
{
  "event": "openIssue.assigned",
  "occurred_at": "2026-06-14T12:00:00+00:00",
  "organization": { "id": 1 },
  "data": {
    "subject_type": "OpenIssue",
    "subject_id": 42,
    "title": "..."
  }
}
```

Enrich with additional fields via the REST API when required.

## Verifying the signature

Every delivery carries the following headers:

- `X-WorkDiary-Signature: sha256=<hmac>`
- `X-WorkDiary-Timestamp: <unix-time>`
- `X-WorkDiary-Event: <event-key>`

The HMAC is computed over `<timestamp>.<body>` with the signing key:

```
expected = HMAC_SHA256(timestamp + "." + raw_body, signing_key)
```

Compare `expected` to the signature value in constant time and reject
requests with a stale timestamp (replay protection).

## Reliability

Failed deliveries are retried with backoff. After several consecutive
failures the endpoint is **disabled automatically**; save it as active to
re-enable it. The per-endpoint delivery log shows the status, HTTP code
and time of recent attempts.
