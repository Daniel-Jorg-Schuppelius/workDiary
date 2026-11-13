---
title: "Wishes of the organizations"
topic: admin.wishes
version: 1
keywords:
    - platform view wishes
    - bundle wishes
    - vendor channel
    - wish to the vendor
    - answer of operations
audience:
    - admin
related:
    - wishes.overview
    - admin.settings
---

The platform view shows all wishes that organizations have forwarded to
operations – for platform operators only. You see the cleaned text, type, page,
app version, number of votes and the organization. Who wrote or agreed with the
wish, and the original text, stay in the organization.

## Bundling, status and answer

- **Bundle:** Assign similar wishes to a leading wish of the same type. The
  list shows leading wishes only; the votes of the bundle are added up.
- **Status and answer of operations** are set on the leading wish. They apply
  to the whole bundle and appear in each organization’s wish as “Answer from
  platform operations”; authors and voters receive a notification.

## Vendor channel (self-hosted installation)

If the vendor runs the installation, this view already is the vendor. In a
self-hosted installation you can send individual wishes to the vendor by mail –
only if you switch on the channel in the **System settings** with
`wishes.vendor_channel` (default off; without it, no wish leaves the
installation). The vendor address is in `wishes.vendor_address`; if empty, the
stored contact address applies.

1. Adjust the **text for the vendor** if needed and save.
2. Check the **preview**: recipient, reply address, subject and exactly the
   text that will be sent – reference, type, text, page, app version, number of
   votes and you as the sender. The organization and names are never included.
3. **Send to the vendor** sends exactly this mail. If something has changed in
   the meantime (such as the number of votes), the app asks for a new preview.

The vendor’s answer comes back to you by mail. Enter it under **Vendor’s
answer**; the organization sees it on the wish.
