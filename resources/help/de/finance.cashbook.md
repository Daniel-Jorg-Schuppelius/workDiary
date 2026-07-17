---
title: "Kassenbuch"
topic: finance.cashbook
version: 1
audience:
    - admin
related:
    - invoices.manage
---

Das **Kassenbuch** dokumentiert Bareinnahmen und -ausgaben GoBD-konform
(MVP-414). workDiary ist kein Kassensystem (kein POS, keine TSE-Pflicht).

- **Unveränderlich**: Buchungen erhalten eine fortlaufende Belegnummer und
  eine Hash-Kette; Ändern und Löschen sind technisch ausgeschlossen.
- **Storno statt Löschen**: Korrekturen sind Gegenbuchungen mit
  Pflicht-Begründung; das Original bleibt erhalten.
- **Tagesabschluss**: Kassensturz mit Soll/Ist/Differenz; danach sind alle
  Buchungen bis zum Abschlussdatum festgeschrieben.
- **Barzahlung**: eine Einnahme kann eine Rechnung referenzieren — volle
  Deckung setzt die Rechnung auf „bezahlt".
- Das Kassenbuch ist Teil des **GoBD-Z3-Exports** (kassenbuch.csv).
