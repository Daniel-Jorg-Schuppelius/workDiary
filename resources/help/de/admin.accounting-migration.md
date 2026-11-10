---
title: "Buchhaltungswechsel"
topic: admin.accounting-migration
version: 3
keywords:
    - Softwarewechsel
    - Systemwechsel Buchhaltung
    - Anbieterwechsel
    - Umstieg
    - Lexoffice
    - orgaMAX
    - sevDesk
    - easybill
    - Lexoffice zu orgaMAX
    - Datenmigration
    - Probelauf
    - Dry-Run
    - Parallelbetrieb
    - Umstellungsstichtag
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Der Buchhaltungswechsel führt kontrolliert von einer Buchhaltungssoftware
zur nächsten. Quelle und Ziel sind frei wählbar unter Lexoffice, orgaMAX,
sevDesk und easybill, in beide Richtungen. WorkDiary kopiert dabei nicht
blind von System zu System, sondern ordnet beide Fremdsysteme denselben
lokalen Fachobjekten zu.

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

Bei sevDesk und easybill gilt:

- **Stammdaten** gleicht der Wechsel über die Kontaktverknüpfung ab: Kunden
  und Lieferanten, die schon mit dem Programm verknüpft sind (Übergabe,
  Kontakt-Push oder Kontaktabgleich), zählen als zugeordnet.
- **Artikel** lassen sich mit sevDesk und easybill nicht abgleichen, weil
  workDiary deren Artikelstamm nur spiegelt und nicht mit lokalen Artikeln
  verknüpft. Wählen Sie den Bereich dann ab. Das Formular markiert ihn
  („nicht mit sevDesk, easybill“); scheitert die Planung daran, bleiben
  Quelle, Ziel und Stichtag stehen und der Bereich ist abgewählt.
- Die **Beleghistorie** stammt aus dem Belegspiegel. Lassen Sie vor der
  Analyse und vor dem Abschluss den Belegabruf laufen, damit offene
  Altbelege vollständig erfasst sind.
- Die Belegbilder liegen weiter im Programm. Sichern Sie sie vor dem Ende
  des Vertrags über dessen Export.

Grundsätze: Finalisierte Belege werden **nie** als neue Belege im
Zielsystem nachgebaut — sie bleiben mit Nummer, Status und Herkunft als
Historie auffindbar. Jeder Schritt ist in einer manipulationssicheren
Ereigniskette protokolliert.
