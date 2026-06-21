---
title: "Rechnungs-Mail-Vorlagen"
topic: admin.invoice-mail-templates
version: 1
audience:
    - admin
related:
    - invoices.manage
    - admin.handbook
    - admin.notification-rules
---

Rechnungs-Mail-Vorlagen definieren die E-Mail-Texte für den
Rechnungsversand. Eine Vorlage besteht aus Name, Betreff sowie einem
HTML- und einem Text-Inhalt.

Aktionen:

- **Anlegen/Bearbeiten**: Name, Betreff und beide Inhalte pflegen.
- **Standard festlegen**: genau eine Vorlage gilt als Standard. Wird
  eine neue als Standard markiert, verlieren die übrigen Vorlagen im
  selben Geltungsbereich diese Markierung.
- **Suchen**: Filtern nach Name oder Betreff.
- **Löschen**: entfernt die Vorlage.

In Betreff und Inhalt lassen sich **Platzhalter** verwenden, die beim
Versand ersetzt werden, u. a.:

- `customer_name`, `customer_email`
- `invoice_number`, `invoice_date`, `due_date`
- `total`, `currency`
- `company_name`, `document_label`, `custom_text`

Vorlagen sind organisationsbezogen. Die Verwaltung erfordert die
Berechtigung zur Abrechnungs-/Faktura-Verwaltung. Der eigentliche
Versand erfolgt im Rechnungsbereich (siehe **Rechnungen verwalten**).
