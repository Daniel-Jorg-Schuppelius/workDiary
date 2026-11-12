---
title: "Guarantees"
topic: guarantees.overview
version: 1
keywords:
    - guarantee register
    - bank guarantee
    - surety bond
    - performance bond
    - warranty bond
    - retention money
    - release retention
    - guarantee deed
    - return guarantee
    - security deposit
audience: []
related:
    - invoices.manage
    - warranties.overview
    - contacts.manage
---

The guarantee register keeps issued and received guarantees — with issuer,
amount, term and the stored certificate.

**Direction:** *Received* guarantees come from suppliers and
subcontractors, *issued* ones are your own in favour of clients. The
direction determines who gets the certificate back in the end.

**Replacing a retention:** A guarantee can replace a retention. The
retention then counts as *secured*, the withheld amount is paid out and the
open receivable rises accordingly. A guarantee for less than the retention
is rejected — it would quietly reduce the security.

**Return:** After expiry the certificate is reclaimed and the case closed as
*returned*. A drawn guarantee stays documented permanently with date and
reason.

## Link to the warranty

A guarantee can point to a **warranty period**. Once that period has ended, the deadline check reports the **return** of the guarantee deed — just as after the release of a replaced retention. Likewise, a retention on an invoice takes the end of the selected warranty as its release date and moves along if the period changes.

## Return due and expiry

**Return due:** The tile and the filter show active guarantees whose security
purpose has ended — the replaced retention was released on the invoice, or the
linked warranty has run out. A replaced retention has already been paid out;
releasing it (invoice page, **Retentions** card) creates no new receivable and
only reports the deed as due for return.

**Expired:** Once the term has passed, the daily deadline check sets the
guarantee to *Expired*; the last day of the term still counts. In the register
you can also record this immediately with **Record as expired**. Expired
guarantees no longer count as active. The return of the deed can still be
recorded, and entering an extension (a new date under **Expires on**) makes
the guarantee active again.
