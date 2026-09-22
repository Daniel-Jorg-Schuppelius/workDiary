---
title: "Lexware supplements: plan, feature matrix and handover"
topic: lexware.supplements
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - articles.lexoffice
---

Under **Billing → Lexware supplements** you store the booked Lexware Office plan (S, M, L, XL or “Unknown / special contract”) with source, confirmation date and — for trial accounts — the end date and the confirmed successor plan. The page works without an API connection.

**Feature matrix:** For each function you see whether it is **included in Lexware** in your plan, whether workDiary offers it as a **supplement** or whether it is **planned** (expansion). With an unknown plan there is no reliable statement about Lexware; local functions remain usable under their own prerequisites. In the first package workDiary supplements standard/e-invoices, quotes and dunning for S from the existing functions, and **recurring invoices** via billing schedules for S/M/L.

**Activating a supplement:** Only supplements deliberately activated in the plan profile appear as “Available in workDiary”. Prerequisites are the module “Sales & invoicing”, the invoicing authority **workDiary** (an externally billed customer receives no local series — switching is a separate process with an effective date) and the permission to view invoices. The plan is orientation, not authorisation; a higher plan takes nothing away.

**Handover list:** Under “Lexware handover list” you find the issued documents of locally billed customers in the selected header period with separate invoice, dispatch and handover status. **Export** downloads the frozen original of each document as PDF with SHA-256 plus a mapping list (CSV) as a package — a download for you, not a claimed Lexware import format. **Confirm manually** acknowledges the handover with user, time and note. “Exported” or “confirmed” never means “booked” or “paid”; cancellation and credit note remain separate documents referring to the original.

**Automatic handover:** The route “automatic” requires your own API key (plan XL) and a proven handover route; until then manual export remains the standard route. Open handovers stay visible when the plan changes.

**Permissions:** Everyone with “List invoices” sees the pages; only “Finance configuration” changes the plan profile; export and confirmation require “Export invoices”.
