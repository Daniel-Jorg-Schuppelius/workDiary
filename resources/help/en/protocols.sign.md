---
title: "Sign a protocol"
topic: protocols.sign
version: 4
keywords:
    - signature
    - get signed
    - digital signature
    - customer signature
    - e-signature
    - sign off
    - acceptance sign-off
    - signing link
    - sign on tablet
    - revision
audience: []
related:
    - protocols.create
---

Signing a protocol completes the acceptance bindingly. A **signed
protocol is never silently overwritten**; corrections create a new
revision.

Before signing:

- **All mandatory items** must be filled in.
- **At least one photo** is required per mandatory photo item, if the
  template is configured that way.
- **Open items** may remain open – they are printed in the PDF together
  with responsibility and due date.

## Mandatory signatures

An **acceptance protocol** needs the customer's signature, a **handover
protocol** those of the customer and the contractor. As long as one of them is
missing, the protocol cannot be completed; the detail page names the missing
role. For all other types the signature is optional.

In the **Finalize / sign** dialog you record one signature. If a mandatory
role is still missing afterwards, the signature is saved and the protocol
stays open – open the dialog again for the next person. The last mandatory
signature locks the protocol.

## Ways to sign

- **On site**: the person signs in the signature box of the dialog, with a
  finger, a pen or the mouse.
- **Paper**: you upload a photo or scan of the signed page (JPG, PNG, WebP or
  PDF) and confirm that the original is on file. The file is then attached to
  the protocol.
- **Signature link**: via **Send signature link** the person receives the link
  by email; it is also shown once on the page. The person reviews the protocol
  without signing in, signs in the signature box, rejects it or asks a
  question. The link is usually valid for 7 days and only once.
- **Customer portal**: a customer-visible protocol that has been submitted for
  review appears in the portal at the order or object under **Awaiting
  acceptance**. The customer views it there and signs.

Signatures via link and portal do not complete the protocol themselves.
Complete it afterwards via **Finalize / sign**; no further signature is needed
then.

The signature image is shown with the signatures on the detail page and in the
PDF, each with name, role, time and kind of signature. On completion the PDF is
created and attached to the protocol.

## Signature and content

A signature applies to exactly the content that was presented. Signatures that
do not finalize the protocol immediately are therefore recorded only after
**Submit for review**: the content is then frozen. This also applies to the
signature link and the customer portal. If you set the protocol **back to
draft**, the existing signatures and open signature links lapse; the
**History** lists them as **Signature discarded**. After completion no link can
be redeemed any more.

When the customer signs via link or portal, the person who issued the link or
submitted the protocol for review is notified – including whether the protocol
can now be finalized. If the customer rejects with a reason, open items are
created at the order, the protocol goes back to draft and you are notified as
well.

## In the customer portal

If a protocol's **Visibility** is set to **Customer-visible**, the customer
sees it in the portal as soon as it has been submitted for review – for
acceptance – and after completion as PDF: for an order in its case file, for an
object in the object record. Drafts stay invisible in the portal. Every
download from the portal appears in the protocol's **History** as **PDF
downloaded in the customer portal** with the name of the portal account.

**Simultaneous changes:** When signing and completing, WorkDiary checks the protocol’s status again. If someone has meanwhile returned it to draft or completed it, the signature is rejected and no image is stored.
