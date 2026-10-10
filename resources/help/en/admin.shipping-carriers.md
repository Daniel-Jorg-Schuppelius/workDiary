---
title: "Shipping connections DHL, UPS and FedEx"
topic: admin.shipping-carriers
version: 2
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
    - shipment tracking
    - cancel shipment
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
for returns directly in WorkDiary and track your shipments. There is one connection per parcel service
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

You create a new connection in the form **Add connection**:

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
for DHL also the API key. If you select a carrier in this form that already has
a connection, WorkDiary refuses to save and shows a notice – you change
existing connections only via **Edit**.

To make changes, click **Edit** next to the connection in the **Existing
connections** list. The form is then called **Edit … connection** with the
carrier in the title, for example “Edit DHL connection”; the carrier cannot be
changed.

- **Label**, **Billing/account number**, **Sandbox / test environment** and
  **Active** are pre-filled with the stored values. What you change here
  applies after saving.
- User, password, API key and returns receiver ID are never displayed. Fields
  left empty keep the stored value; only a new entry replaces it.
- A **Billing/account number** left empty also keeps the stored value.
- **Cancel** leaves editing without saving.

## Existing connections

The **Existing connections** list shows the carrier, the label, the **Mode**
(**Sandbox** or **Production**) and the status (**Active** or **Inactive**) of
each connection. **Edit** opens the connection in the form. **Deactivate**
switches a connection off; it is then no longer offered for selection, and
shipments of this carrier are no longer checked. To reactivate it, open it with
**Edit**, tick **Active** and save.

## Creating labels

- **Shipping label for deliveries:** under **Manufacturing orders** you find
  the **Deliveries** section on the detail page of an order. For a delivery
  with a customer, select the connection, enter – if no parcels are recorded –
  the weight in grams and optionally length, width and height in centimeters,
  and click **Ship**. UPS and FedEx only use the dimensions if all three are
  given. Recorded parcels supply weight and dimensions themselves. The
  recipient is the customer of the delivery. Afterwards the delivery shows the
  status **Label created** with the parcel service and tracking number. There
  is one shipping order per delivery; a cancelled one does not count. On the
  delivery, **Download label** downloads the label again, **Check shipment
  status** retrieves the current status and **Cancel shipment** cancels it –
  details in the help on manufacturing orders.
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

## Shipment tracking

WorkDiary checks open shipments – status **Label created**, **In transit** or
**Delivery problem** – with the parcel service every hour in the default
setting. Each shipment is queried at most every three hours and only up to 60
days after it was created; after that it is no longer considered trackable.
The check takes over status and shipment history until the shipment is
**Delivered**.

- If a shipment changes to **Delivery problem**, WorkDiary triggers the
  notification **Delivery problem with a shipment**.
- The time of the last check is shown when you hover over the status on the
  delivery (**Last checked: …**).
- Checks only run via an active connection. If the request to the parcel
  service fails, it counts like other connection errors for the connection
  (see “Typical problems”).

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
- **“A connection for this carrier already exists. Please change it via
  “Edit”.”** You selected a carrier that is already connected in the form
  **Add connection**. Open the connection in the list with **Edit**.
- **“No active connection is configured for the selected carrier.”** The
  connection has been deactivated in the meantime.
- **“Could not cancel the shipment: …”** or **“Could not check the shipment
  status: …”** The parcel service rejected the request or could not be
  reached, or the connection is inactive. Once the shipment is in transit,
  cancelling is no longer possible.
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
