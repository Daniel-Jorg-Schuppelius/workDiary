---
title: "Shipping connections DHL, UPS and FedEx"
topic: admin.shipping-carriers
version: 1
keywords:
    - shipping
    - DHL
    - UPS
    - FedEx
    - shipping label
    - print parcel label
    - return label
    - tracking number
    - parcel service
    - business customer portal
    - sandbox
    - carrier
audience:
    - admin
modules:
    - module.versand
related:
    - admin.integrations
    - admin.plugins
    - manufacturing.orders
    - claims.overview
    - admin.organization-settings
    - admin.operations
---

The **Shipping & Logistics** page – listed in the menu as **Shipping** – stores
the credentials for the parcel services DHL Paket, UPS and FedEx. With an
active connection you create shipping labels for deliveries and return labels
for returns directly in WorkDiary. There is one connection per parcel service
and organization; passwords and keys are stored encrypted.

## Prerequisites

- Your license includes the Shipping & Logistics module.
- The plugin of the parcel service is activated under **Plugins**: **DHL
  Paket**, **UPS** or **FedEx**. After that, the **Shipping** entry appears in
  the system menu (gear icon **System**) in the **Plugins** group.
- You have a business customer account with the parcel service:
  - **DHL:** user and password of the DHL business customer portal, an API key
    activated by DHL (dhl-api-key) and the billing number. For return labels
    also the returns receiver ID, which you create in the business customer
    portal.
  - **UPS:** client ID and client secret of a UPS developer app and your UPS
    account number (shipper number).
  - **FedEx:** client ID and client secret of a FedEx developer app and your
    FedEx account number.
- For UPS and FedEx, WorkDiary takes the sender address from the settings of
  the organization, section **E-invoice (XRechnung)**: **Company name** (if
  empty, the name of the organization), **Street and number**, **ZIP code** and
  **City**. If any of these details is missing, the label fails.
- The page is open to administrators of your organization.

## Creating or changing a connection

In the **Add / edit connection** section:

1. **Carrier**: DHL, UPS or FEDEX.
2. **Label**: a name under which the connection is offered later when creating
   labels, for example “DHL shipping warehouse”.
3. **User / client ID** and **Password / client secret**.
4. **API key (DHL only: dhl-api-key)**.
5. **Returns receiver ID (DHL only)**: required for DHL return labels.
6. **Billing/account number**: for DHL the billing number, for UPS the shipper
   number, for FedEx the account number.
7. **Sandbox / test environment**: connects to the test environment of the
   parcel service; no real shipments are created there.
8. **Active** and **Save**.

For a new connection, user/client ID and password/client secret are required,
for DHL also the API key.

To make changes, save the form again with the same carrier; this updates the
existing connection. The form always starts empty:

- Fields left empty for user, password, API key and returns receiver ID keep
  the stored value.
- Enter **Label** and **Billing/account number** again every time – an empty
  billing number is deleted.
- **Sandbox / test environment** and **Active** apply as they are set when
  saving. A sandbox connection therefore becomes a production connection if you
  do not tick the box again.

## Existing connections

The **Existing connections** list shows the carrier, the label, the **Mode**
(**Sandbox** or **Production**) and the status (**Active** or **Inactive**) of
each connection. **Deactivate** switches a connection off; it is then no
longer offered for selection. To reactivate it, save it again with **Active**
switched on.

## Creating labels

- **Shipping label for deliveries:** under **Manufacturing orders** you find
  the **Deliveries** section on the detail page of an order. For a delivery
  with a customer, select the connection, enter – if no parcels are recorded –
  the weight in grams and optionally length, width and height in centimeters,
  and click **Ship**. UPS and FedEx only use the dimensions if all three are
  given. Recorded parcels supply weight and dimensions themselves. The
  recipient is the customer of the delivery. Afterwards the delivery shows the
  status **Label created** with the parcel service and tracking number. There
  is one shipping order per delivery.
- **Return label for returns:** in the **Claim files**, select the connection
  for a return with the status **Announced**, enter the weight and click
  **Create return label**. The sender is the customer; their address must
  contain street, postcode and city. **Download label** gives you the file; if
  returns are enabled for the customer in the customer portal, they can also
  retrieve the label there.
- **Permission:** shipping labels can be created by anyone allowed to edit the
  manufacturing order; return labels by anyone with the **Inspect and restock
  returns** permission.

UPS delivers the label as an image (GIF), FedEx as a PDF. If the parcel service
rejects the order, WorkDiary discards the draft, and you can try again after
correcting the problem.

## Limits

- One connection per parcel service and organization.
- By default, DHL shipments run as DHL Paket national; only the operator of the
  installation can set a different product.
- You create customs documents for shipments outside the EU separately on the
  delivery (see the help on manufacturing orders).

## Typical problems

- **“A new connection requires user/client ID and password/client secret (DHL
  additionally: API key).”** Add the missing credentials.
- **No connection to choose from:** there is no active connection, or the
  delivery has no customer or already has a shipping order.
- **“No active connection is configured for the selected carrier.”** The
  connection has been deactivated in the meantime.
- **“Could not create the shipping label: …”** Check the credentials, the
  billing or account number, the **Sandbox / test environment** switch and the
  recipient's address. For UPS and FedEx, the sender address is often missing
  in the settings of the organization; for DHL return labels, the returns
  receiver ID.
- **“The return label needs the customer address (street, postcode, city).”**
  Complete the address in the customer record.
- **Checking credentials:** you run the health check of the parcel service
  under **Plugins**. If a connection fails, WorkDiary reports the disrupted
  connection under **Operations tasks**.
