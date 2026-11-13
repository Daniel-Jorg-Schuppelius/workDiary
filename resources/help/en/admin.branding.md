---
title: "Branding"
topic: admin.branding
version: 3
keywords:
    - white label
    - corporate design
    - corporate identity
    - company logo
    - upload logo
    - dark logo
    - brand colours
    - company details
    - footer text
    - invoice footer
    - look and feel
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
    - invoices.manage
---

Branding defines the appearance of the current organization: logos,
display name, company details and colors. The settings apply to the web
interface and to generated PDF documents (e.g. invoices) and are stored
per organization.

You upload **logos** at the top of this page in the **Logos** card –
separately from the form below; you do not need to save the form for
them:

- **Logo (light variant)** is the default logo.
- **Logo (dark variant, optional)** appears in the dark color scheme and
  in PDFs for which you choose the dark variant. If one of the two
  variants is missing, the other one is used; without a logo of your own
  the WorkDiary logo appears.
- Choose a file and click **Upload**: the new image replaces the previous
  one immediately. **Remove** deletes the logo after a confirmation.
- PNG, JPG and WEBP are allowed up to the size shown below the field
  (2 MB by default); SVG is not accepted.

In the form below you maintain:

- **Master data**: display name of the app (browser title and header)
  and slogan.
- **Contact**: address, phone, email and web.
- **Legal & PDF footer**: VAT ID, tax number, account holder, bank, IBAN,
  BIC, trade register and the footer text for PDF documents.
  Enter the trade register entry together with the register court, for
  example “HRB 12345, Amtsgericht Musterstadt”; the e-invoice carries it as
  the organisation’s legal registration identifier (BT-30), frozen at issue.
- **Colors**: primary and accent color; they replace the base colors of
  the interface.
- **PDF configuration per document type**: logo variant (light, dark or
  no logo) and whether **Contact in header** and **Footer** appear.

Layout, letterhead and table style of the PDFs are set in document
design; the hint at the end of the page leads there if you are allowed
to open it.

Note: IBAN and BIC are checked and normalized on save (spaces removed,
uppercased). Cleared fields fall back to the system defaults. The "Edit
branding" permission is required (administrators always have it); you
only change the branding of your own organization.
