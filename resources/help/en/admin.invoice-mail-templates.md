---
title: "Invoice mail templates"
topic: admin.invoice-mail-templates
version: 1
audience:
    - admin
related:
    - invoices.manage
    - admin.handbook
    - admin.notification-rules
---

Invoice mail templates define the email texts for sending invoices. A
template consists of a name, subject and an HTML and a text body.

Actions:

- **Create/Edit**: maintain name, subject and both bodies.
- **Set default**: exactly one template acts as the default. When a
  new one is marked as default, the others in the same scope lose that
  marking.
- **Search**: filter by name or subject.
- **Delete**: removes the template.

The subject and body can use **placeholders** that are replaced when
sending, among them:

- `customer_name`, `customer_email`
- `invoice_number`, `invoice_date`, `due_date`
- `total`, `currency`
- `company_name`, `document_label`, `custom_text`

Templates are organization-scoped. Management requires the billing
permission. The actual sending happens in the invoice area (see
**Manage invoices**).
