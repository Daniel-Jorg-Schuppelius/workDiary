---
title: "Fees"
topic: club.fees
version: 1
audience: []
modules:
    - module.club
related:
    - club.members
    - club.groups
---

Fee management is part of the club basis and works without gradings. It requires the right “manage fees” (treasurer); the club administration reads along, group leaders see no fee data.

**Tariffs and rates:** Tariffs are freely named (e.g. adults, children, reduced, passive, family) and carry their amounts as rates per validity date: interval (monthly, quarterly, semi-annually, annually), amount, billing anchor (start month of the period), due days after period start, proration rule and an optional admission fee. New rates do not change already released claims. Department surcharges are separate positions for members with an active group assignment in the department. There are no built-in club rates.

**Fee accounts and payers:** A fee account is the paying person in the customer/debtor master. A parent can pay for several children without being a member. The assignment member → account and tariff is explicit with a period; never automatic by matching e-mail or IBAN. Per member at most one assignment applies at a time.

**Family fee:** A family tariff is a fixed household tariff: all members assigned to the account with this tariff yield exactly one base fee position per period. Alternatively individual fees remain with an explicit percentage discount and reason. There is no full family and individual base fee at the same time.

**Proration:** Each rate is either “full period” or “daily”. Daily counts the active calendar days of the period (assignment, joining/leaving) divided by the period days; every position is rounded to cents. Joining mid-month thus yields the corresponding share.

**Exemptions:** Exemptions and reductions are recorded explicitly with period and reason. A membership pause alone does not waive a fee.

**Age limits:** Tariffs may carry age limits. If a member no longer fits on the reference day, the daily check flags the assignment for review; fee management confirms the change with an effective date or keeps the tariff. Nothing changes automatically.

**Preview:** The fee preview shows for a billing month all positions of the periods starting in that month with their calculation basis. Incomplete assignments or tariffs without a rate appear as errors, not silently omitted. Claims arise only with the fee run.

**Fee run and claims:** A fee run freezes the preview of a billing month (all periods starting in that month). Errors in the preview block the release. The release creates exactly one claim with positions per fee account; repeating, catching up and parallel runs create no duplicates because every source and period can be claimed only once. Released amounts do not change with later tariff or family changes. If billing sovereignty is external (e.g. Lexoffice), preview and handover list remain possible while local release is blocked.

**Fee notice:** Every claim has a PDF notice with period, breakdown, due date and payment reference (the claim number). Sending by e-mail is separate from release; every attempt gets a delivery record, failures stay visible. The footer text of the notice (e.g. a note on tax/document assignment) is set in the club settings.

**Open items, cancellation, correction:** Claims are open, partially paid, paid or cancelled; overdue derives from due date and remaining amount. An unpaid claim can be cancelled with a reason, the periods become free for a new run. Corrections are separate, linked claims (additional claim or credit); released amounts are never overwritten. Leaving ends future fees but does not remove existing open items.

**Payments:** Payments are their own bookings on the fee account (transfer, cash, SEPA direct debit, other). Without a chosen claim they are applied by due date — a family bulk payment covers several claims; a remainder stays as credit and can be applied to later claims. The same money is never counted twice: when bank matching finds a credit whose amount is already booked manually or by collection, it links the transaction to that payment instead of creating a second one. Unmatching in bank reconciliation only reverts the bank payment; a manually booked payment stays.

**Chargeback:** A chargeback compensates exactly one payment (once), reopens the remainder and blocks re-collection until fee management lifts the block explicitly. A bank fee is created as its own linked additional claim; the original claim stays unchanged.

**Dunning:** Only overdue, unblocked claims can be dunned, in at most three levels (payment reminder, dunning notice, final dunning notice). Deadline and fee are set deliberately per dunning, not taken from invoice defaults; a dunning fee is its own linked claim. Dunnings exist as PDF and e-mail with delivery proof. Disputed or deferred claims get a dunning block with a reason.

**SEPA collection:** The collection proposal lists due remaining amounts of accounts with a usable mandate (the customer’s active mandate or one fixed on the account). A collection run is a direct-debit run of the finance module: release and pain.008 export happen there. Each attempt gets a unique reference (claim number and attempt number), the run reserves the position against re-collection. The export is not a payment — only “Book receipt” after the credit sets the claim to paid. Without the finance package, payments, dunning and preparation still work, only the SEPA export does not.
