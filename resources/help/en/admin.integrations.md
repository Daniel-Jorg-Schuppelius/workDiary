---
title: "Managing integrations"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

This help applies to all integration admin pages – such as CalDAV,
WebDAV, Todoist, Zammad, Kimai/Clockify, mail intake, telephony,
team messengers, attendance terminals, shipping and SSO. All
connectors follow the same principles.

**Attendance terminals, kiosk and check-in points:** When registering a
terminal, two addresses are shown exactly once: the ingest address for hardware
terminals and the kiosk address that turns a tablet browser into a terminal.
Both contain the same device token; if it is lost, rotate the token or disable
the terminal. Badges read by the tablet's own NFC chip (Android Chrome) must be
stored as a hex ID without separators. Check-in points are QR codes or NFC
stickers at locations and vehicles: the print view provides the code, and the
same address can be written to a sticker with an NFC app. A code can be
photographed – to prove presence on site, set a radius. The position is then
only checked, not stored.

Badges can be replaced by a **terminal PIN**: administration sets it per person
with a personnel number; only a hash is stored, and after five failed attempts
it is locked for 15 minutes and can be unlocked here.

**Per organization:** Integrations are enabled and configured per
organization. Activation, credentials, health status and error
history always apply to the current organization only – the same
connector can be in a completely different state elsewhere.

**Credentials:** Tokens, passwords and device identifiers are stored
in the respective plugin configuration. Sensitive values are saved
encrypted and never appear in plain text again after saving – neither
in the UI nor in the audit trail.

**Health check and auto-disable:** Every connector is continuously
monitored for connection errors. If errors accumulate beyond the
configurable threshold, the connector is disabled automatically so it
cannot cause follow-up damage. Auto-disabled integrations stay
visible in the overview and are marked accordingly – once you have
fixed the cause (e.g. renewed an expired token) you can re-enable
them. A single faulty plugin never takes the application down with
it: errors are recorded in isolation.

**Incoming data – inbox first:** Imports never apply anything
blindly. Incoming records land in the integration inbox first, are
matched against existing data and are only applied after an
unambiguous match or your manual decision. Unclear cases and
conflicts remain as open inbox items until you resolve or discard
them.

**Outgoing changes – outbox:** Changes towards the external system
run through an outbox with automatic retry. If a delivery fails it is
attempted again; detected conflicts (e.g. when the external system
changed in the meantime) are routed back to the inbox for
clarification. Nothing gets lost and nothing is written twice.

**Recommendation:** After setting up a new connector, check its
health status, watch the inbox for unexpected conflicts for a few
days, and only then build automated workflows on top of it.

## Which integrations exist

The range keeps growing; the list below names the available integrations by
purpose, so you do not have to guess where something belongs:

- **Accounting and invoicing:** lexoffice, orgaMAX, sevDesk, easybill,
  BuchhaltungsButler, InvoicePlane, and the Peppol access point for sending
  electronic invoices.
- **Telephony and messaging:** sipgate and FRITZ!Box for incoming and outgoing
  calls, seven.io for text messages to critical recipients.
- **Shipping:** DHL, FedEx and UPS for labels and tracking.
- **Files and backups:** Nextcloud, WebDAV, Dropbox, Google Drive, SharePoint
  and S3 as storage or backup targets.
- **Calendar, contacts and mail:** Microsoft Graph, Google Calendar, CalDAV,
  CardDAV, and Calendly for booked appointments.
- **Projects and time:** Todoist, OpenProject, GitHub, GitLab, Toggl, Clockify,
  Kimai, Zammad.
- **Commerce and ERP:** JTL-Wawi, Billbee, Etsy.

An integration missing from this list does not exist — when in doubt, ask
rather than storing credentials somewhere not intended for them.
