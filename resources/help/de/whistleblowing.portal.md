---
title: "Meldeportal konfigurieren"
topic: whistleblowing.portal
version: 1
audience:
    - admin
related:
    - whistleblowing.cases
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Hier konfigurierst du das öffentliche Meldeportal deiner Organisation
(`/compliance/portal`). Pro Organisation gibt es genau ein Portal.
Die Verwaltung erfordert die Berechtigung
**whistleblowing.settings.manage** sowie die Zwei-Faktor-
Authentifizierung der Meldestelle.

Einstellungen:

- **Aktiv (`is_enabled`)**: schaltet das öffentliche Portal frei.
- **Anonyme Meldungen zulassen**: erlaubt Meldungen ohne jede
  Identitätsangabe.
- **Vertrauliche Meldungen zulassen**: erlaubt Meldungen mit
  vertraulich behandelter Identität.
- **Einleitungstext** und **Standardsprache** für die Melder-Sicht.
- **Aufbewahrung (Monate)**: Frist für die kontrollierte Löschung
  abgeschlossener Fälle.

**Portal-Link (Slug)**: Der öffentliche Link enthält einen zufälligen
Slug (z. B. `wb-xxxxxxxxxxxx`) und ist **nicht** aus dem
Organisationsnamen ableitbar. Über **Link rotieren** erzeugst du
einen neuen Slug.

Risiko: Nach dem Rotieren sind **bereits verteilte Links sofort
ungültig**. Verwende das nur, wenn ein Link nicht mehr genutzt werden
soll, und kommuniziere den neuen Link anschließend aktiv.
