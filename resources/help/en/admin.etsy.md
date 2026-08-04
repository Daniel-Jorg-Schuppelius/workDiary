---
title: "Connect Etsy"
topic: admin.etsy
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connects the organization's **Etsy shop** directly (Open API v3):
orders appear as an **order mirror**, shipment reports with tracking flow
back, and fees and payouts from the Etsy ledger are available for
reporting.

**Own seller app:** Each organization registers its own seller app at
etsy.com/developers (approved within minutes) and stores the **keystring**
and **shared secret** on the plugin card. The app's redirect URI must be
exactly the callback URL shown in the panel (HTTPS, no deviation). Then
"Connect to Etsy" — the shop is determined automatically; a shop can only
be bound to **one** organization.

**Inbox-first:** Buyers are never created blindly as customers. Unique
matches or already assigned repeat buyers are linked; everything else
appears as a suggestion in the integration inbox. Guest orders without an
Etsy buyer account stay in the mirror without a suggestion.

**Webhooks (optional):** In the Etsy webhook portal, register the URL shown
in the panel with the four order.* events and store the `whsec_…` secret on
the plugin card — new orders then appear immediately. Without a webhook
everything runs through the regular sync (the sync always remains the
reliable source).

**Report shipment:** The mirror action sends tracking number and carrier to
Etsy (Etsy notifies the buyer). Unknown carriers are submitted as "other".
Each order is reported at most once.

**Mind the deadline:** Etsy's refresh token expires 90 days after last use;
the health check warns in time, after that only reconnecting helps. Etsy
provides no test environment — tests run against the live shop under Etsy's
API testing policy (fees are charged for real).

*The term "Etsy" is a trademark of Etsy, Inc. This application uses the
Etsy API but is not endorsed or certified by Etsy, Inc.*
