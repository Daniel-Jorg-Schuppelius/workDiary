---
title: "Disposal & proofs"
topic: disposal.overview
version: 1
audience: []
related:
    - assets.fleet
    - customer-portal.overview
---

The disposal case manages old-equipment disposal as an audit-proof
process: pickup at the customer, device list with waste codes (AVV/EWC),
data media treatment according to DIN 66399, handover to the certified
disposal contractor with proofs, and completion with a customer record in
the portal.

**Status chain:** Created → Picked up → In treatment → Handed over to
disposal contractor → Completed. The treatment step can be skipped when no
data media are involved. Cancellation is possible until completion, is
final and is logged in the chain of custody together with the reason.

**Device list:** Each item carries category, manufacturer/model, serial
number, quantity, weight and the waste code (AVV/EWC). The "hazardous"
classification is derived automatically from the asterisk in the waste
code — it is never set by hand. Items can only be changed until the
handover to the disposal contractor.

**Data media treatment:** For every device containing data media the
treatment is documented — data medium type, method (e.g. software wipe,
degaussing, shredding or removal for destruction), DIN 66399 material
category with security level, plus the person performing it and an
evidence reference. The material category is prefilled to match the data
medium type.

**Disposal contractor handover:** Handovers to the certified disposal
contractor are recorded with proof type (e.g. transfer note, consignment
note, disposal certificate), document number, handover date and EfbV
certificate reference. An uploaded proof is archived as a DMS document.

**Completion:** The completion check of the case requires four
preconditions — at least one device item, the customer's takeover
signature, a documented treatment for every data-bearing device and, for
hazardous waste, a disposal contractor proof. On completion the customer
record is generated as a PDF, released in the customer portal, and linked
assets are decommissioned. Completion and cancellation require the
"Complete and cancel disposal cases" permission.

**Report:** The disposal report evaluates completed cases in the selected
period — disposed quantities per customer, per month and per waste code
(AVV/EWC), each with the hazardous share.
