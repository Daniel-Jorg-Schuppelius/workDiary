---
title: "Lexoffice-Konflikte"
topic: admin.lexoffice
version: 4
keywords:
    - Lexware Office
    - Synchronisationskonflikt
    - Sync-Konflikt
    - Datenkonflikt
    - Datenabgleich
    - abweichende Daten
    - Konflikt lösen
    - lokale Werte behalten
    - Werte übernehmen
    - Kontakte abgleichen
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Hier lösen Sie Synchronisationskonflikte mit Lexoffice. Ein Konflikt
entsteht, wenn ein lokaler Datensatz (WorkDiary) und der zugehörige
Lexoffice-Kontakt in einem oder mehreren Feldern auseinanderlaufen und
in den Lexoffice-Einstellungen als **Konflikt-Strategie** die
**Manuelle Prüfung** gewählt ist (Standard). Bearbeitet werden die
Konflikte in der **Zuordnungs-Inbox**: Der Aufruf öffnet sie gefiltert
auf die Quelle **Lexoffice** und den Fall **Feld-Konflikt**.

In der Zuordnungs-Inbox:

- Je Konflikt stehen die abweichenden Felder als **Lokal** und
  **Remote** nebeneinander.
- Betroffen sind Kunden und Lieferanten, also die Kontakte aus
  Lexoffice.
- Über den Statusfilter rufen Sie auch erledigte Konflikte wieder auf.

Lösungswege je Konflikt:

- **Remote übernehmen**: aktualisiert den lokalen Datensatz mit den
  Lexoffice-Werten der abweichenden Felder.
- **Lokal behalten**: behält die lokalen Werte; die abweichenden
  Lexoffice-Werte werden nicht übernommen.
- **Verwerfen**: schließt den Konflikt ohne Änderung (z. B. bei bewusst
  unterschiedlichen Daten); er erhält den Status **Verworfen**.

Risiken: **Remote übernehmen** überschreibt lokale Werte. Prüfen Sie die
gegenübergestellten Daten genau, bevor Sie entscheiden. Beachten Sie,
dass bei Rechnungen die Faktura-Hoheit beim externen Programm liegt –
WorkDiary liefert dorthin zu.

Die Konflikt-Strategie gilt für Kontakte und Artikel. Artikelkonflikte
erscheinen nicht in der Zuordnungs-Inbox, sondern im Reiter
**Konflikte** des Lagers (**Lager** → **Konflikte**): Dort wählen Sie
**Lokal belassen**, **Lexoffice-Stand übernehmen** oder **Verwerfen** —
mit dem Recht **Artikel verwalten**.

Berechtigung: Die Zuordnungs-Inbox steht Administratoren und der Rolle
**Buchhaltung** offen.
