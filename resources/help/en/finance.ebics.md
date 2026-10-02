---
title: "EBICS bank access"
topic: finance.ebics
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.reconciliation
---

With EBICS (version 3.0), workDiary fetches daily statements directly from
the bank and submits payment runs — without downloading and uploading
files in online banking.

**Setup:** Under bank accounts, the bank icon opens the account’s EBICS
access. Enter the EBICS URL, host ID, customer ID and subscriber ID from
the bank’s access letter. Then, in this order: create keys, send them to
the bank (INI and HIA), download the initialisation letter, sign it and
send it to the bank. Once the bank has activated the access, fetch the
bank keys — only then is the access active.

**Daily statements:** An activated access fetches the statements
(camt.053) every morning and imports them into payment reconciliation;
“Fetch statements now” does so immediately. Statements already imported
are recognised and skipped.

**Payment runs:** A released payment run can be sent to the bank with
“Submit via EBICS” — the same file that is available for download, and
exactly once. The authorised signatory then authorises the payment
(electronic signature) at the bank.

**Security:** The keys are stored encrypted and are additionally protected
by a passphrase. Every step and every order is recorded in the access
history. If misuse is suspected, “Suspend access” blocks the keys at the
bank; the setup then starts again with new keys.
