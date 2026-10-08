---
title: "Customer Portal – Requests and orders"
topic: customer-portal.intakes
version: 2
keywords:
    - submit request
    - print job
    - print request
    - IT request
    - upload file
    - upload print files
    - print approval
    - accept quote
    - decline quote
    - send large files
    - upload link
    - reference number
audience: []
related:
    - customer-portal.overview
    - customer-portal.tickets
    - customer-portal.queries
---

Under **Requests & orders** you request services from your contractor, submit
files, answer questions, decide on quotes and approve print data. The page is
called **Requests and orders**. The **Status** of each record shows what is
happening and whether anything is needed from you.

## The overview

At the top are the **Request print** and **Request IT service** buttons, plus
**Submit files** as soon as at least one record accepts files. The table
lists your company's requests, the newest at the top:

- **Number** – the record number; a click opens the record.
- **Subject** and **Service type** (**Print** or **IT service**).
- **Status** – with a note below it when you need to do something.
- **Received** – the date of the request.

## Submitting a request

Click **Request print** or **Request IT service**. The **New request: …** form
contains:

- **Subject** (required), **Description** and **Requested date**. The
  requested date must not be in the past and is not yet a confirmed delivery
  commitment.
- For print, under **Details for Print**: **Product / service** (required),
  **Quantity**, **Final format**, **Colour**, **Preferred material** and
  **Pickup or shipping**; with **Shipping** also the **Delivery address**.
- For IT services, under **Details for IT service**: **Requested service**
  (required), **Affected device / system**, **Impact** and **Preferred
  delivery**. If **Objects** is released for your company, you can also select
  an **Affected object (optional)**. Do not enter passwords or access keys;
  these are agreed separately.
- **Files (optional)** – permitted formats, number and size are shown at the
  field. For print data, PDF, TIFF, EPS, AI, JPG, PNG, SVG and ZIP are
  allowed.

Selection fields such as **Final format**, **Colour**, **Impact** or
**Preferred delivery** are required. If you do not know the answer, choose
**Advice needed**. Then click **Submit request**. If a file is not permitted,
the form names it and nothing is saved. Accidentally submitting twice does
not create a second record.

If **Tickets** is also released, the order page of a service in the **Service
catalog** additionally offers a **Request service** button. It opens the same
form as an IT request with the details of the catalog service; the service's
file and photo fields are available there. The order is only placed once you
accept the quote.

## After submitting

You receive a record number, and an acknowledgement of receipt is sent to the
email address of your portal account. It only confirms receipt, not
acceptance of an order and not an appointment. Your contractor is notified.
You are also informed by email when the contractor asks a question, provides
a quote, requests a print approval, rejects the request or takes on the job.

## The status

Before the order is placed, the status shows for example:

- **Received — under review** or **In progress**.
- **Question pending** – please answer the question or submit missing files.
- **Quote available** – please review it and decide.
- **Quote accepted — order is being created**, **Partially accepted — scope is
  being agreed**, **Quote rejected**, **Quote expired** or **Quote is being
  revised**.
- **Rejected** – your contractor's **Reason** is shown at the top of the
  record.
- **Withdrawn** – you have withdrawn the record.

Once ordered, the status follows the job itself, for example **Print data is
being checked**, **Print approval required**, **In production** and **Ready
for pickup**, or for IT services **Ordered — scheduled**, **Waiting for your
response** and **Done — please confirm**.

## Working on a record

The detail page shows the number, service type, subject and status; if your
contractor needs something from you, the next step is highlighted below. It
continues with:

- **Your details** – date received, requested date, catalog service and object
  if any, your description and form details, stored as you submitted them.
- **Questions** – questions from **Your contractor** and your answers. As long
  as the record is open, write under **Your reply**, add files if needed and
  click **Send reply**. The text cannot be changed afterwards; your reply
  resumes processing.
- **Files** – the files visible to you with **File**, **Uploaded** and **Size**
  for download. As long as the record accepts files, select new files under
  **Further files** and click **Upload files**.

Existing files are never overwritten; each submission is added as a new file.
Alternatively use **Submit files** in the overview and choose the matching
record under **Record**. You do not see your contractor's internal notes and
files.

## Deciding on a quote

When your contractor provides a quote, the **Quote … (version …)** section
appears with the items (**Description**, **Quantity**, **Unit price**,
**Type**), the **Total (net plus VAT)**, the **Valid until …** date if any and
the terms.

Under **Your decision** the mandatory items are already ticked; tick options
and alternatives yourself if you want them. Unticked items count as not
ordered. Then click **Accept quotation** or **Reject quotation**; when
rejecting you can give a reason under **Reason (optional, only when
rejecting)**. Your decision is documented with a timestamp, and your
contractor is notified.

If the quote is being revised, the section says so; you can then only decide
on the new version. Once the binding period has expired, no decision is
possible any more, and you ask your contractor for a new version.

## Print approval

For print jobs, the **Print approval** section appears once the order has
been placed. While your contractor is checking the print data, a note is
shown there. When the contractor requests your approval, you see:

- **File** with version and the **Download file** link,
- **Checksum (SHA-256)**, which identifies exactly this file,
- the parameters **Final format**, **Quantity**, **Colour mode**,
  **Material/substrate** and, where applicable, **Pages** and **Finishing**,
- **Customer approval** – requested, approved or rejected, each with a date.

Check the file and parameters and click **Approve print data** or **Reject**.
A **Reason (only when rejecting)** is required to reject. With your approval
you confirm exactly this file and these parameters; printing only starts
after your contractor's internal approval. If the file is replaced, your
approval lapses and the new file needs a new approval.

## IT service: your ticket

When an IT request is ordered, a ticket is created from it. The record then
shows the **Your ticket** section with the ticket number; the conversation and
the solution continue there. If **Tickets** is released for your company,
open it with **Open ticket**.

## Large files via upload link

If your contractor offers an upload link, the **Files** section contains the
**Upload large files via Nextcloud** button. It creates a personal link with
**Password** and expiry date (**Valid until**). There you can upload even very
large files without seeing any other files. The files are transferred into
the record automatically and checked by the same rules as a direct upload.
**Transfer uploaded files now** does this immediately; **Last transferred**
shows the last synchronisation. The link ends on its expiry date or as soon as
the record no longer accepts files.

## Withdrawing a record

As long as the record is open and no quote has been accepted, **Withdraw
record** appears at the bottom. The status changes to **Withdrawn**; the
withdrawal is logged and your contractor is notified. After a quote has been
accepted, withdrawal is no longer possible in the portal.

## Limits

- Submitted details and sent replies cannot be changed; send additions as a
  reply or file.
- Replies are only possible as long as the record has not been ordered,
  rejected or withdrawn.
- After the order has been placed, a record only accepts files if your
  contractor has opened submission for it.
