---
title: "Intake file"
topic: sales.lead-intakes
version: 1
keywords:
    - Intake file
    - Intake
    - Client acceptance
    - New customer check
    - Initial assessment
    - Anti-money laundering
    - AML
    - Identification
    - Beneficial owners
    - Politically exposed person
    - Risk level
    - ID copy
    - Check package
    - Second review
audience: []
modules:
    - module.vertrieb
related:
    - sales.leads
    - customers.deadlines
    - forms.fill
---

The **intake file** belongs to the lead and accompanies a new customer from
the enquiry to setup: interview form, documents, the checks your industry
profile requires, and the decision. Only then does the lead become a
customer — with service scope and deadline attributes taken from the file.

## Opening a file

The lead shows the **Intake file** panel with “Open intake”. The file takes
over the check packages currently enabled for the organisation and freezes
them: if a package changes later, the file stays as it was.

## Stages

**Enquiry → Interview → Documents → Decision → Setup.** You choose the first
stages freely; setup follows from the acceptance and ends with the conversion
of the lead (“set up”). The **Intakes** tile on the dashboard counts open
files per stage.

## The form

The form is a long form with sections, progress and a table of contents. Each
field is saved when you leave it; **interview mode** enlarges the touch areas
for the tablet during the meeting. The base part asks for request, requested
services, start and notes; then come the **deadline attributes** of the enabled
rule sets and, per check package, its fields and its **document list** with
ticks and files.

## Check packages

| Package | Profile | Content |
| --- | --- | --- |
| Anti-Money Laundering Act (GwG) | Tax advice (enabled) | Identification, verification with a document, beneficial owners, transparency register, politically exposed person, purpose, risk factors, risk level |
| Initial care assessment | Home care (enabled) | Care level and notice, benefit type, powers of attorney and guardianship, advance directive, emergency contacts, GP, care contract |
| B2B new customer | IT, building cleaning, crafts (can be enabled) | VAT ID (formal check), credit report, payment terms, prepayment |

Packages are enabled under **Intakes → Settings** (permission “Decide
intakes”). This is also where the **update per AML risk level** (default:
yearly) and the **second review** are set.

## Decision

Accept or reject, always with a reason — only with the permission “Decide
intakes”. Acceptance checks all mandatory details and the package rules: for
a politically exposed person the risk level must be high, “low” and “high”
require a reason, and whoever verified with an ID or register extract files
the copy. With the **second review**, another person confirms the acceptance
or returns it with a reason. Acceptance with the AML package creates the
record “Identification and risk record” on the lead.

**Conversion:** if a package requiring a decision is active (AML, initial care
assessment), a lead only becomes a customer after acceptance. Without such a
package, conversion stays as before. On conversion the customer takes over the
requested services as service scope, the deadline attributes, the care level
and the VAT ID and payment terms as a proposal.

## Restricted visibility

Identity details, the ID copy and health details can only be seen and
recorded by people with their own permission (“identity details …”, “health
details …”) — even someone who can see the customer cannot see them
otherwise: not in the form, the documents, the PDF or the subject access
report. They are stored encrypted.

## Update and retention

After acceptance the **update period** runs (AML per risk level). Shortly
before, a notification reminds you and the work queue shows the file; “Update”
creates a new **version**, the old one remains as history.

The AML file is retained for **five years from the end of the calendar year**
in which the business relationship ends (§ 8 (4) GwG). Enter the end on the
customer in the intake file panel. Until then the file survives the
anonymisation of the lead and the archiving of the customer; afterwards the
retention scan proposes it for deletion. Rejected intakes run from the
rejection. Packages without their own rule go with the anonymisation of the
lead.

## PDF

“PDF” produces the file in paper layout: header table, sections, tick boxes,
each check package on its own page, footer with date, customer and page
number. “File as document” stores the PDF on the customer or the lead —
always without identity and health details.
