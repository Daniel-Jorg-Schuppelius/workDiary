---
title: "Organization and settings"
topic: admin.organization-settings
version: 3
keywords:
    - company settings
    - tenant settings
    - organization settings
    - map service
    - weather service
    - holiday calendar
    - regional public holidays
    - maintenance mode
    - enforce two-factor
    - dunning levels
    - geocoding
    - route planning
audience:
    - admin
related:
    - admin.tenants
    - admin.settings
    - admin.license
    - reports.arbzg-compliance
    - catalog.holidays
    - finance.dunning
    - invoices.manage
    - accounting.fixed-assets
    - account.ai-assistant
    - admin.notification-rules
    - dispatch.board
    - tours.manage
---

In the **Edit organisation** dialog you maintain your organization's master
data and all settings that apply to its members: working time rules and
approval stages, defaults for invoices and dunning, map and weather services,
the holiday region and maintenance mode. You open it from the system menu (the
**System** gear icon in the header) under **Organization** → **Organization**.
The entry is available to administrators and always opens your own
organization; platform operations reach the same dialog from the
**Organisations** list.

## How the settings work

- **Scope:** Every value applies to the whole organization. Where members,
  customers, projects or sites can have their own values, the section in
  question says so; their values then take precedence.
- **Defaults:** Many fields are empty and show “Default …” in grey, for
  example “Default 25”. An empty field uses the system-wide default: a value
  the operator has set in the system settings, otherwise the built-in value
  named by the grey text. A value you enter applies only to your organization
  and takes precedence over any system default.
- **Resetting:** If you clear a field and save, your value is removed and the
  default applies again.
- **Pre-filled fields:** Sections without a grey placeholder, such as the
  working time limits or the approval stages, show the value in effect and
  save it again the next time you save.
- **Saving:** **Save** stores all sections and tabs at once. If a value lies
  outside the permitted range, the dialog reports it at the field and saves
  nothing.
- **Deleting and deactivating:** Deactivation, data export and permanent
  deletion of an organization are reserved for platform operations (see
  “Organizations & tenants”); the dialog shows no buttons for them.

## Master data

- **Name** (required, at most 255 characters): name of the organization. It
  also serves as the company name of the e-invoice as long as no separate one
  is entered there.
- **Language** (required): interface language for all members who have not
  chosen their own language, and the language of their notifications and
  emails. Invoices, quotes, dunning letters and delivery notes appear in this
  language if the customer has no document language of their own.
- **Timezone** (required): display time zone for members without their own
  time zone. It also determines day boundaries such as “today” and the start
  of the week.
- **Date format** and **Time format**: default for all members who have not
  chosen their own format in their profile. The list shows every format with
  an example; **— Default —** uses the system format.
- **Missed clock-ins**: how missing clock-ins are added later – **Employee
  requests – HR approves** (default) or **Employee may add entries
  themselves**. Such entries are always marked as “manual” and remain visible
  in the correction inbox.

## Plan & status

- **Plan**: the organization's plan – **Free**, **Pro** or **Enterprise**.
  It is only displayed here: which modules are enabled follows from the
  installed license, and when a license is installed the organization takes
  over its plan. Only platform operations can change it, because switching to
  a smaller plan starts a grace period of 30 days for the modules that drop
  out, after which a nightly run removes the data of deletable modules.
- **Active**: whether the organization is locked is also switched by
  platform operations only. Members of a locked organization only see “This
  organisation has been deactivated. Please contact the operator.”
- **Security** – **Two-factor authentication mandatory for all members**:
  anyone who has not yet set up a second factor is taken to the setup after
  signing in and can only continue working afterwards; this also applies to
  customer portal accounts. As long as the obligation exists, the last factor
  cannot be removed and two-factor authentication cannot be switched off.

## Compliance mode

**Mode** determines how strictly the working time checks under the German
Working Hours Act (ArbZG) react:

- **Off**: no checks – neither in duty and shift planning nor in the ArbZG
  evaluation of recorded time and its unclear cases.
- **Warn** (default): violations are shown, but saving still works.
- **Block**: a planned shift with a hard violation cannot be saved unless the
  person planning deliberately overrides the check in the shift dialog. Hard
  violations are an overlap, a rest period that is too short, exceeded daily
  working time and a shift during approved vacation; the other rules only
  warn.

## Working time model

**Default working-time type** is the working time model of all members who
have no model of their own, and the preset for new models: **Flexitime**
(default), **Fixed weekly hours**, **Per weekday** or **Trust-based working
time**. A model of your own per person takes precedence.

## Working time limits

The limits apply to duty and shift planning and to the ArbZG evaluation of
recorded time:

- **Max hours/day** (1–24, default 10): daily working time without breaks.
- **Min rest (h)** (1–24, default 11): rest between two working days or
  shifts.
- **Max hours/week** (1–168, default 48).
- **Max consecutive days** (1–14, default 6): consecutive working days in
  shift planning.
- **Night time from (hour)** (20–23, default 23) and **Night time until
  (hour)** (4–7, default 6): night window for checking night work. Under the
  ArbZG it runs from 11 pm to 6 am, in bakeries and patisseries from 10 pm to
  5 am.
- **Frame-time tolerance (min.)** (0–240, default 15): only when clockings
  exceed the working time frame of the working time model by more than these
  minutes does an unclear case arise.
- **Flex traffic light: yellow from (min.)** (default 1200, i.e. 20 hours)
  and **Flex traffic light: red from (min.)** (default 2400, i.e. 40 hours):
  colouring of the flexitime balance in the working time account and on the
  dashboard. Plus and minus hours count the same. If the red value is below
  the yellow value, the yellow value also applies to red.

## Approvals

- **Vacation approval stages**, **Overtime approval stages** and **Time
  correction approval stages**: each **Single-stage (one approval)** (default)
  or **Two-stage (four-eyes principle)** – a request then needs two approvals.
- **Commercial: responsible role**, **Technical: responsible role** and
  **HR: responsible role**: approval steps of a contract negotiation appear in
  the approval inbox under “Approvals” for the role assigned to their step
  kind. All roles except Customer can be selected. Left empty, the default
  applies: **Default (Accounting)**, **Default (Team Lead)** or **Default
  (Personnel Administration)**. Approval directly on the record remains
  possible.
- **Book requested absences immediately as provisional (rejection removes
  them)**: requested absences already take effect in planning before
  approval, marked as provisional; a rejection removes them. Only approved
  absences are still billed and exported.
- **Enable presence board (current presence)**: unlocks the **Current
  presence** page (off by default).

## Driving and rest times

**Apply driving time rules (vehicles flagged "Apply driving and rest time
rules")** (off by default) checks trips against the limits of Regulation (EC)
561/2006 and the German FPersV: daily and weekly driving time, breaks as well
as daily and weekly rest. Only trips with vehicles on which **Apply driving
and rest time rules** is also set are checked – both switches must be on.
This is not legal advice: which regulations apply in an individual case is
for the business to clarify.

## Active rules

Here you switch off individual checks; by default all are on. A rule that is
switched off is no longer checked, and the mode **Off** switches off all of
them. The first eight rules concern shift planning:

- **Overlapping shifts**: two shifts of the same person overlap.
- **Minimum rest**: the rest period between two shifts is too short.
- **Daily working time** and **Weekly working time**: the limit is exceeded.
- **Consecutive days**: more working days in a row than permitted.
- **Vacation conflict**: the shift falls within requested or approved
  vacation.
- **Qualification match**: the person lacks a qualification that the staffing
  requirement of the shift demands.
- **Holiday booking**: the shift falls on a public holiday of the holiday
  region (**Region & holidays** tab) or on one of your own holidays under
  **Holidays**.

The last four check the clockings and create unclear cases in the ArbZG
evaluation:

- **Missing check-out stamp**: an attendance remains open beyond the day.
- **Stamp on a day off**: clocked on a day that is free according to the
  working time model or the duty plan.
- **Stamp during absence**: clocked despite an approved full-day absence such
  as vacation or sickness.
- **Working time frame (clockings)**: clocking outside the working time
  frame, beyond the tolerance.

## Advanced settings

The last section groups further defaults in tabs: **Lists**, **Invoicing**,
**File uploads**, **Input limits**, **Notifications**, **Interface**,
**Routing & maps**, **Travel**, **Region & holidays**, **Weather** and
**Maintenance**. Besides the invoicing defaults, the **Invoicing** tab also
contains dunning, e-invoice, fixed assets, shipping and customs, online
payment, AI assistants, rental terms, fleet, claim patterns, recurring
problems and time import. The note “Leave empty to use the system default.”
applies to all tabs. The following sections are in the order of the tabs.

## Lists

How many entries a list shows per page, each from 1 to 500: **Timesheets**,
**Duty plans**, **Customers** (also suppliers and third-party customers),
**Tours**, **Vehicles**, **Tags**, **Archive** (each tab of the archive page),
**Notifications, operations tasks, maintenance windows, problem reports** and
the three lists of the remote support inbox (**Remote support inbox:
unassigned devices**, **Remote support inbox: multi-customer devices**,
**Remote support inbox: sessions per device card**). How many recently used
entries the dashboard shows is set in the **Interface** tab. The list size of
the platform operations' organization overview is a system setting under
**Settings (registry)**.

## Invoicing

- **Default tax rate (%)**: if empty, workDiary determines the tax rate of
  domestic invoices from the tax rules. A rate you enter applies to all
  locally created domestic invoices and takes precedence over the tax rules.
- **Default currency (ISO-4217)**: default currency of the organization; the
  amounts of a document stay in the customer's currency.
- **Time unit for positions** (up to 8 characters, default h): unit of the
  time positions in the transfer.
- **Default service (article)**: provides name, unit, standard text and – if
  no rate can be found – the price of the transfer positions. Billing rules on
  the project take precedence. Without articles in the article master the
  list stays empty.
- **Template: transfer introduction** and **Template: transfer closing
  remark** (up to 2000 characters each): copied into the receipt when a
  transfer is created and editable there. Placeholders: :customer, :from,
  :to, :channel. Without a template for the closing remark, the customer's
  invoice text applies.
- **Default hourly rate (revenue)**: applies when neither entry, customer
  agreement, employee, activity, project nor customer sets a rate. If empty,
  such time stays at 0.00.
- **Assembly costing rate**: values the assembly time of an article in the
  sale price suggestion; if empty, the default hourly rate applies.
- **Default billing increment (minutes)** (1–1440): rounds billable time up
  to this increment when neither project nor customer sets one; empty =
  minute-exact.
- **Default grouping gap (minutes)** (0–1440): entries separated by at most
  this gap are merged into one block when billing; empty = no grouping.
- **Billing channel**: default billing channel of the organization, for
  example **WorkDiary (local)** or **Lexoffice leads**; customers can
  override it individually. If empty, **— WorkDiary (default) —** applies.
  The field only appears with the permission “Manage finance configuration”.

How invoices are created is described in the topic “Invoices & documents”.

## Dunning

Level defaults for single dunning and the dunning run; the procedure is
described in the topic “Dunning”.

- For each of levels 1 to 3: **Level 1: grace period (days)** and so on – for
  level 1 the days overdue before the payment reminder becomes due, for
  levels 2 and 3 the days since the last dunning notice (default 7 each);
  **Level 1: fee (EUR)** and so on (default 0.00); **Level 1: payment
  deadline (days)** and so on (default 14, 10 and 7 days).
- **Calculate default interest**: **Fixed rate** (default) or **Base rate +
  percentage points** – the base rate under § 247 BGB is fetched monthly from
  the Bundesbank.
- **Surcharge (percentage points)**: base-rate mode only. Guidance under
  § 288 BGB: 5 percentage points towards consumers, 9 in business
  transactions; your company decides the amount.
- **Default interest (% p.a.)**: fixed rate only; 0 = no interest shown.

Default interest only appears in the dunning letter; it is not booked.

## E-invoice (XRechnung)

Seller details for XRechnung output (EN 16931) of locally created invoices:
**Company name** (empty = name of the organization), **Street and number**,
**ZIP code**, **City**, **Country code (ISO 3166-1)**, **VAT ID**, **Tax
number**, **Contact: name**, **Contact: email** (valid address), **Contact:
phone**, **IBAN**, **BIC** and **Account holder**.

Three fields also affect all locally created invoices:

- **Country code (ISO 3166-1)** (two letters, default DE): the seller's
  country. The tax determination uses it to distinguish domestic, EU and
  non-EU invoices.
- **Payment term (days)** (0–365): applies when neither the invoice nor the
  customer has a payment term; empty or 0 = 14 days.
- **Small business (§ 19 UStG)**: all invoices that workDiary creates show no
  VAT and carry the note “No VAT in accordance with § 19 UStG (German small
  business scheme).”; the XRechnung receives tax category E (exempt). The tick
  takes precedence over **Default tax rate (%)** and reverse charge.

## Accounting: four-eyes principle

The **Four-eyes principle** switch in the **Accounting** group requires a second person to approve: whoever prepares an entry or a direct booking (cash discount, write-off, clearing booking, internal transfer, opening balances, special prepayment) does not post it themselves; whoever compiles a SEPA payment run does not release it themselves. Direct bookings are then created as drafts in the **Posting inbox** and only take effect once posted. Without the switch, WorkDiary posts them immediately.

## Fixed assets: low-value assets and pool

Net value limits for the fixed asset register. The defaults follow § 6 (2)/(2a)
EStG (as of 2026); check them when the law changes.

- **Low-value asset limit** (default 800): limit for the immediate write-off
  of low-value assets.
- **Pool from (above)** (default 250), **Pool up to** (default 1000) and
  **Pool years** (1–20, default 5).
- **Price increase for replacement forecast (% p.a.)** (0–50, default 0).

Details are in the topic “Fixed asset register and depreciation”.

## Shipping and customs

**EORI number**: customs number of the company (country code and up to 15
characters, e.g. DE1234567). It appears as sender information on commercial
and pro forma invoices for shipments outside the EU.

## Online payment

- **Payment provider**: only needed if several providers are active; default
  **Automatic (first active provider)**. You activate the providers (Stripe,
  Mollie or SumUp) as a plugin with their own credentials.
- **Payment link on the invoice and in the email** (on by default): payment
  link and QR code appear on the invoice and in the email. When switched off,
  online payment remains possible in the customer portal.

## AI assistants (MCP)

**Allow AI assistants via MCP** (off by default): AI assistants such as Claude
or ChatGPT can connect with the consent of individual users and read or
create drafts with their permissions. When switched off, no new connection is
possible; existing connections get no tools and cannot be renewed. The
connection itself is described in “Connect AI assistant”.

## Rental terms in equipment rental

- **Handover only with signed rental terms**: you keep the rental terms as the
  customer agreement “Rental terms (equipment rental)” with version and
  signature.
- **Allow direct booking in the customer portal**: customers reserve
  available equipment released for the portal immediately and bindingly; the
  managers are notified.
- **Radius around the site (m)** (50–50,000, default 500): if the reported
  position of rented equipment is further away from the rental's site, the
  deviation is reported. Without a site, the customer's geofences apply.

## Fleet

**No new trip if a mandatory inspection is overdue** (off by default): if the
MOT, accident-prevention check or another mandatory inspection of the
assigned asset is overdue or blocked, no trip can be recorded from today.
Past trips remain recordable.

## Claim patterns

How many similar claims trigger a hint – same lot, same article with the same
defect type or cause, or same supplier: **Threshold (cases)** (2–50, default
3) within **Time window (days)** (7–365, default 90).

## Recurring problems

This early warning finds customers and objects for which a conspicuously high
number of helpdesk tickets come in within the chosen period.

- **Tickets from** (2–50, default 3): minimum number of tickets for a warning.
- **Window (days)** (7–365, default 90): period up to today, measured by the
  date the tickets were reported.

How workDiary counts:

- All tickets with a customer count, regardless of their status. Tickets with
  an object count per customer and object, tickets without an object per
  customer.
- If a customer or an object reaches the threshold, the warning “Recurring
  tickets: …” is created with the number, the period, a link to the object or
  customer and the recommendation to clarify the cause with the customer,
  inspect or replace the object and consider a work instruction or training.
  At most the 20 cases with the most tickets are shown.
- The warnings appear under **Reports** → **Projects & customers** →
  **Problems & training** in the **Recurring problems** area (for
  administrators and people with the permission “View reports”) and in the
  **Notable findings** dashboard tile.
- In addition, the notification “Early warning from reports” goes once per
  customer or object to the Team Lead and Administrator roles. You change
  recipients and channels under **Notification rules**.

## Time import

**Assign time to projects by keyword** (on by default): applies to imported
time, for example from remote support, Toggl or Kimai. If the text of an
imported entry contains the name or a keyword of a project of the same
customer, it is booked there instead of the default project or the
assignment inbox. Only unambiguous matches are booked.

## File uploads

Size limits for uploads in kilobytes (1 to 1,048,576 KB, i.e. up to 1 GB):
**CSV import** (default 10,240 KB, 10 MB), **Customer attachment**
(10,240 KB), **Attachments (general)** (25,600 KB, 25 MB) and **Print data**
(262,144 KB, 256 MB). Larger files are rejected on upload.

## Input limits

Character and range limits for form fields, each from 1:

- **Attendance**: **Note, max characters** (default 1000), **Device ID, max
  characters** (64) and **Break, max minutes** (600).
- **Tags**: **Tag name, max characters** (60).
- **Comments**: **Comment body, max characters** (5000).
- **Duty plans**: **Note, max characters** (2000).

## Notifications

**Message preview, max characters** (20–500, default 120): this many
characters of the message text are shown in a push notification; the rest is
cut off.

## Interface

- **Calendar** – **Slot length in minutes**: grid of the week view;
  appointments without an end get this length. Permitted values are 10, 15,
  20, 30 or 60 (default 30).
- **Dashboard** – **Number of recent items** (default 5): how many recently
  used entries the dashboard shows.

## Nominatim (geocoding)

Nominatim is a geocoding service based on OpenStreetMap: it converts an
address into coordinates. workDiary uses it in the **Logbook**: when you
leave the **From (address)** or **To (address)** field, workDiary looks up
the address, and the address found appears as a tooltip on the field. The
address entered is sent to the configured service.

- **Base URL** (full address, up to 255 characters): address of the
  Nominatim service. If empty, the operator's default applies. If your own
  address is in an internal network, such as a self-hosted server, workDiary
  only queries it if the operator has enabled this for your organization.
- **Contact email**: sent with every request. Nominatim's usage rules require
  the querying application to identify itself with a contact address.
- **Requests per second** (1–50, default 1): workDiary waits accordingly
  between two requests. The public Nominatim service allows at most one
  request per second; higher values are only intended for your own server.
- Results are cached per service address (365 days by default); the same
  address is not queried again during that time.
- If the service cannot be reached or finds nothing, no tooltip appears; the
  input itself is not affected.

## OSRM (routing)

OSRM calculates driving routes on the road network. workDiary uses it

- when optimizing a tour: order of the stops by real road distances, route
  line on the map as well as planned distance and driving time, to which the
  time spent at the stops is added;
- for the **Idle-time suggestions** of the **Control center**: additional
  driving time there and back for an order that would fit into a free time
  slot.

If OSRM cannot be reached, workDiary continues with straight-line distances:
tours can still be planned, only without a route line, and the suggestions
carry the note **rough estimate (straight-line distance)**.

- **Base URL** (full address, up to 255 characters): address of the OSRM
  server. For your own address in an internal network the same approval by
  the operator applies as for Nominatim.
- **Profile (e.g. driving)** (up to 32 characters, default driving): travel
  profile of the server. Which profiles exist, for example for cycling or
  walking, is determined by the OSRM server.
- **Timeout (seconds)** (1–120, default 10): how long workDiary waits for an
  answer before falling back to straight-line distances.

## Map tiles

Map tiles are the image sections the maps in workDiary are made of, for
example for **Tours**, on the map of the **Control center** and in crisis
management. Each user's browser loads them directly from the configured tile
server.

- **Tile URL template** (full address, up to 255 characters): address of the
  tile server with the placeholders {z} for the zoom level and {x} and {y} for
  the tile position. The default is the OpenStreetMap tile server. workDiary
  automatically allows the browser to load images from this server.
- **Maximum zoom** (1–22, default 19): strongest magnification of the maps.
  Choose at most the level the tile server delivers.
- The attribution at the bottom of the map is set by the operator's base
  configuration; it cannot be changed here.

## Travel billing

In the **Travel** tab you decide whether invoices include travel. With
**Calculate travel automatically** (off by default), workDiary adds a travel
position to the project or material billing of a customer for every tour
with a stop at that customer; the position carries the date of the tour. Cancelled tours and
travel already billed do not count.

- **Mode**: **Flat rate** or **Kilometers**.
- **Item text** (up to 50 characters, default “Travel” in the language of the
  billing): text of the invoice position, supplemented by the date or the
  kilometers.
- **Flat rate (net €)**: amount per trip in **Flat rate** mode; without an
  amount, no position is created.
- **Rate (€/km)**: price per kilometer in **Kilometers** mode.
- **Kilometer source**: **Always from the company location** – straight-line
  distance from the company location to the customer's address – or
  **Depending on the route (actual km)** – the kilometers from the logbook for
  this customer on that day, otherwise the planned tour distance.
- **Round trip (×2, company location only)**: doubles the straight-line
  distance.
- **Company location latitude (lat)** and **Company location longitude
  (lng)**: coordinates of the company location; if empty, the starting point
  of the tour applies.

If the customer's coordinates are missing in kilometer mode, no position is
created. For individual customers you override the values in the customer
dialog under **Travel (override)**.

## Jurisdiction & holidays

**Holiday region (country / state)** determines which public holidays apply
to your organization. You can choose Germany with **Nationwide (without
regional holidays)** and all 16 federal states, **Austria (nationwide)** as
well as **Switzerland (nationwide)** and all 26 cantons. Regional holidays
such as Corpus Christi or Reformation Day only apply in certain states – so
choose the region of your business. If empty, the system-wide default
applies, which the first entry of the list names as “Default …”.

The region takes effect wherever workDiary takes holidays into account,
including:

- holiday surcharges and customer agreements with a holiday rule;
- working days of vacation and sickness, the vacation account and the
  flexitime target;
- the ArbZG evaluation, for example for work on public holidays, and the
  duty plan rule **Holiday booking**;
- the calendar, week view, duty plan, absence calendar and **Current
  presence** views;
- SLA deadlines in the helpdesk and filing deadlines of tax returns, which
  move to the next working day;
- holiday prices in equipment rental.

You maintain your own holidays or rest days under **Holidays**; they apply in
addition to the region. A site can deviate under **Sites** in the **Holiday
region** field (default **Organization's holiday rules**); this applies to
the surcharges of the time recorded there.

## Automatic weather fetch

**Fetch weather automatically when a protocol is created** (off by default):
when a protocol is created, workDiary fetches a weather snapshot for the
location and time of the protocol in the background and attaches it as
evidence.

- The location is given by the coordinates of the site the protocol concerns,
  otherwise those of the customer – also via the project or the order of the
  protocol. Without coordinates nothing happens.
- Projects can deviate: in the project you choose on, off or **Inherit (org
  setting)** under **Automatic weather fetch**. The choice also applies to
  subprojects.
- **Weather service**: **Open-Meteo** (default) works worldwide and without
  sign-up. **Deutscher Wetterdienst (DWD)** provides official German station
  data (licence CC BY 4.0, attribution “Deutscher Wetterdienst”), only for
  locations in Germany with a station in range.
- **DWD: maximum station distance (km)** (1–200, default 30): if no active DWD
  station lies within this distance, no snapshot is created – better no value
  than a wrong one.

## Weather warnings for dispatching

**Weather warnings for dispatched assignments** (on by default): workDiary
checks the daily forecast every hour for the assignments of the next three
days, including today, and reports when a threshold is breached.

- Orders scheduled within this period that are assigned to someone or
  planned and are neither done nor cancelled are checked. The coordinates come
  from the order address, otherwise from the customer; without coordinates
  there is no check.
- Only **Open-Meteo** provides forecasts. If DWD is selected as the **Weather
  service**, no warnings are created.
- Thresholds (empty = default):
  - **Rain (mm/day)** – default 20; warns from this daily total.
  - **Gusts (km/h)** – default 60.
  - **Frost below low of (°C)** – default 0; warns when the daily low reaches
    or falls below this temperature.
  - **Heat above high of (°C)** – default 30.
- workDiary reports each breach exactly once per assignment, day and
  threshold – by default to the assigned person and the Team Lead role. You
  set recipients and channels under **Notification rules** for the event
  “Weather warning for assignment”; SMS is also possible for this event. If
  the event is switched off there, workDiary does not fetch any forecasts.

## Maintenance mode

In the **Maintenance** tab you temporarily lock workDiary for your
organization.

- **Enable maintenance mode**: all members who are not administrators see a
  maintenance page instead of the application; signing in and out remains
  possible. Administrators keep working and see the notice “Maintenance mode
  active — non-administrators currently see a maintenance page.” at the top,
  with the **Settings** link back to this dialog.
- **Notice shown on the maintenance page** (up to 300 characters).
- **Expected end** (optional, in your local time): after this time the
  maintenance mode ends automatically; the notice shows it as “Until: …”, the
  maintenance page as “Expected available again: …”.
- **Also pause terminal/webhook ingest** (off by default): without this tick,
  clock-in terminals as well as telephony and location ingest keep running
  during maintenance.

Switching maintenance mode on and off is recorded in the audit log.
