---
title: "Buchhaltungswechsel"
topic: admin.accounting-migration
version: 1
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Der Buchhaltungswechsel führt kontrolliert von einer Buchhaltungssoftware
zur nächsten (erster unterstützter Pfad: Lexoffice → orgaMAX). WorkDiary
kopiert dabei nicht blind von System zu System, sondern ordnet beide
Fremdsysteme denselben lokalen Fachobjekten zu.

Ablauf:

1. **Planen**: Datenbereiche (Kunden, Lieferanten, Artikel, Belege) und
   Stichtag festlegen. Je Organisation ist genau ein Wechsel gleichzeitig
   möglich.
2. **Analyse (Dry-Run)**: liest beide Seiten und zeigt je Bereich, was
   bereits zugeordnet ist, was noch fehlt und wo es Konflikte gibt. Der
   Dry-Run schreibt in kein Fremdsystem.
3. **Zuordnen**: mehrdeutige oder verlustbehaftete Datensätze werden
   einzeln entschieden (zuordnen, überspringen, als Historie führen).
4. **Doppelbetrieb**: beide Verbindungen dürfen aktiv sein. Altbelege
   werden im Quellsystem zu Ende geführt.
5. **Umschalten**: ab dem Stichtag entstehen neue Fakturavorgänge
   ausschließlich im Zielsystem — die Übergabe an das Quellsystem ist für
   diese Kunden technisch gesperrt. Die Umschaltung bleibt blockiert,
   solange Konflikte oder unklare Schreibausgänge bestehen.
6. **Abschließen**: erst möglich, wenn keine offenen Altbelege und keine
   ungeklärten Zuordnungen mehr existieren. Das Protokoll (CSV) belegt
   Umfang, Zuordnungen und Abweichungen.

Grundsätze: Finalisierte Belege werden **nie** als neue Belege im
Zielsystem nachgebaut — sie bleiben mit Nummer, Status und Herkunft als
Historie auffindbar. Jeder Schritt ist in einer manipulationssicheren
Ereigniskette protokolliert.
