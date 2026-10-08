---
title: "Customer intakes"
topic: customer.intakes
version: 1
keywords:
    - customer request
    - portal request
    - print job
    - print request
    - IT request
    - process request
    - convert to order
    - reject request
    - ask customer
    - link quote
    - upload link
    - Nextcloud
    - customer files
audience: []
related:
    - customer.queries
    - print.orders
---

Customers submit print or IT requests with files in the customer portal under **Requests and orders**. Each intake receives a reference number and appears here with customer, service type, status, requested date and the person in charge. Open intakes are always listed; closed ones are limited by the period in the header.

How to work on an intake:

- **Assign** takes over the intake; it moves to "In progress".
- **Ask a question** sends the customer a question including files. The intake then waits for the customer; a reply or additional files resume the processing.
- **Internal note** stays in-house — the customer never sees the note or its files.
- **Quote**: create a new quote or link an existing one of the customer. After approval and sending, the customer decides in the portal; link a revised version anew — an earlier consent never applies to another version.
- **Hand over** creates the print order or ticket after acceptance. A partial acceptance requires a documented scope reconciliation. Repeated or simultaneous hand-overs do not create a second record.
- **Reject** requires a reason the customer will read. This is no longer possible once a quote has been accepted.

After the hand-over, the operational record is authoritative; the customer sees its status. **Open uploads** lets the customer attach further files to the handed-over intake. If an email to the customer fails, the intake shows a notice — stored data and decisions remain unaffected.

**Upload link (Nextcloud):** If the Nextcloud plugin is set up with its own credentials for the upload channel, the customer opens an upload link with password on the intake. WorkDiary transfers new files every 15 minutes or via "Fetch now", checks them like portal uploads and revokes the link after the last transfer as soon as the intake no longer accepts files or the link expires. Errors are shown on the link and in the history; the files also remain in Nextcloud.
