---
title: "Connecting DATEV Online"
topic: admin.datev-online
version: 1
keywords:
    - DATEV Unternehmen online
    - DUO
    - DATEV interface
    - transfer posting batches
    - receipt images
    - send documents to DATEV
    - EXTF
    - tax advisor
    - client number
    - consultant number
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

The integration transfers finalised booking batches and document images
directly to DATEV Unternehmen online — without downloading and uploading
files by hand.

**Prerequisites:** An app registration at DATEV (client ID and client
secret from the DATEV developer portal) and a DATEV user with access to
the client. Enter the credentials in the plugin settings and register the
redirect address shown there in the DATEV app registration. As long as
DATEV has not approved production use, keep “Use sandbox” switched on.

**Sign in and choose the client:** “Sign in with DATEV” leads to the DATEV
login and back. Then choose the client (consultant number-client number)
from the list of clients released to you.

**Booking batches:** Finalised batches from the DATEV export can be handed
over as an EXTF import with “Transfer to DATEV”. The consultant and client
numbers in the batch must match the connected client. DATEV processes the
import in the background; the nightly run checks the result, “Check import
status” does so immediately. A failed import can be transferred again
after correction.

**Document images:** When switched on, the nightly run transfers issued
invoices as “Rechnungsausgang” and received invoices as “Rechnungseingang”
— each document exactly once and only from the configured date (default:
the day of signing in). “Transfer now” starts the run immediately.

**Disconnect:** The connection can be disconnected at any time; data
already transferred stays in DATEV.
