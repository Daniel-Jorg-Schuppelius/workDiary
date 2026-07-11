---
title: "Datenführerschaft"
topic: admin.data-ownership
version: 1
audience:
    - admin
related:
    - admin.tenants
    - finance.transfers
---

Diese Seite legt je Organisation fest, welches System für welchen
Datenbereich **führend** ist – damit nie zwei Systeme dieselben Daten
gegeneinander überschreiben.

**Die Matrix:** Für jeden Datenbereich (z. B. Aufgaben, Tickets,
Bestände, Kalender, Dokumente, Kunden) gilt genau **ein führendes
System**: entweder WorkDiary selbst („nativ", der Standard) oder
eine aktivierte Integration. Doppel-Führung ist strukturell
ausgeschlossen.

**Wirkung der Führung:** Führt WorkDiary selbst, bleiben Importe aus
Integrationen wie gewohnt über die Inbox erlaubt. Führt eine
Integration einen Bereich, darf nur noch sie dort schreiben –
Schreibversuche anderer Integrationen landen als Konflikt in der
Inbox statt Daten zu verändern. Jede Änderung der Führung wird
auditiert.

**Rechnungshoheit:** Für die Fakturierung gilt dasselbe Prinzip:
Genau ein Programm führt die Rechnungen – WorkDiary, Lexoffice oder
DATEV. Den Fakturierungsweg stellst du als **Standard je
Organisation** ein und kannst ihn **je Kunde** übersteuern. Es gilt
die Kaskade: Kunden-Einstellung vor Organisations-Standard, ohne
beides führt WorkDiary lokal.

**Folgen bei externer Hoheit:** Führt ein externes Programm die
Fakturierung eines Kunden, ist die **lokale Rechnungserstellung für
diesen Kunden gesperrt**. Abrechenbare Zeiten und Materialien gehen
stattdessen als **Übergabenachweis** an das führende Programm:
Übergaben entstehen zunächst als Entwurf, werden bestätigt und erst
mit der tatsächlichen Übergabe werden die Quellposten als abgerechnet
verbraucht – so kann nichts doppelt fakturiert werden. Die
verbindliche Rechnungsnummern-Vergabe bleibt vollständig beim
führenden Programm.

**Umstellung im Betrieb:** Eine Umstellung des Fakturierungswegs
wirkt nur auf künftige Vorgänge; bereits erstellte Belege bleiben
unverändert. Kläre vor dem Wechsel, welche offenen Posten noch über
den alten Weg abgeschlossen werden sollen.

**Empfehlung:** Halte die Matrix bewusst schlank – übertrage die
Führung nur dort an eine Integration, wo das Fremdsystem tatsächlich
die maßgebliche Datenquelle ist.
