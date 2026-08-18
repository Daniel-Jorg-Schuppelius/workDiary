---
title: "Rechnungen & Belege"
topic: invoices.manage
version: 2
audience: []
related:
    - contacts.manage
    - projects.manage
    - finance.transfers
    - travel-expenses.manage
---

Die Rechnungsübersicht verwaltet lokale Rechnungen und angebundene
Belege. Welcher Weg führend ist, hängt von der Organisation und der
eingesetzten Faktura-Integration ab.

Vor dem Erstellen müssen Kunde, Leistungszeitraum, Projektbezug,
Positionen, Steuerangaben und Empfängeradresse geprüft sein. Entwürfe
können ergänzt werden; versandte, gebuchte oder extern übergebene
Belege dürfen nicht still verändert werden.

PDF, Versand und externe Synchronisation sind Ausgaben desselben
dokumentierten Stands. Bei Fehlern nutze den vorgesehenen Storno- oder
Korrekturprozess statt Belegnummern oder Beträge nachträglich zu
überschreiben.

Seit MVP-462 zeigt der Erstell-Dialog eine **Vorschau** der
entstehenden Positionen (Anzahl, Dauer in Uhren- und Dezimalformat,
Betrag, Nachzügler-Warnung), sobald Kunde und Zeitraum gewählt sind.
Einzelne Zeiteinträge lassen sich dort per Häkchen vom Lauf
**ausschließen** — sie bleiben offen und erscheinen im nächsten Lauf.
Auf der Rechnung sind je Position die **Quell-Zeiteinträge**
aufklappbar; Stundenmengen erscheinen zusätzlich im Uhrenformat
(z. B. 1,50 h = 1:30 h).

**Mahnschreiben:** Beim Mahnen entsteht ein eigenes Mahnschreiben-PDF
(Stufe 1 = Zahlungserinnerung) mit Forderungsübersicht, optionaler
Mahngebühr und Zahlungsziel; die E-Mail enthält das Mahnschreiben und
die Original-Rechnung als Anhang. Ein neuer Beleg entsteht dabei nicht.
