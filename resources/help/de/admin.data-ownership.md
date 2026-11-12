---
title: "Datenführerschaft"
topic: admin.data-ownership
version: 3
keywords:
    - führendes System
    - Master-System
    - Single Source of Truth
    - Datenhoheit
    - Rechnungshoheit
    - Fakturahoheit
    - Fakturierungsweg
    - Lexoffice
    - DATEV
    - Übergabenachweis
    - doppelte Rechnungen vermeiden
    - Synchronisationskonflikt
    - Führerschaftskonflikt
    - einmalig übernehmen
audience:
    - admin
related:
    - admin.integration-inbox
    - admin.tenants
    - finance.transfers
---

Diese Seite legt je Organisation fest, welches System für welchen
Datenbereich **führend** ist – damit nie zwei Systeme dieselben Daten
gegeneinander überschreiben.

**Die Matrix:** Für jeden Datenbereich – Aufgaben, Tickets, Bestände, Kalender, Dokumente, Kunden und Lieferanten – gilt genau **ein führendes System**: WorkDiary selbst (der Standard) oder eine aktivierte Integration. Zur Wahl stehen je Bereich nur Integrationen, die ihn tatsächlich schreiben können. **Bestände** stellen Sie nicht hier um, sondern in der Anbindung der Warenwirtschaft (etwa JTL-Wawi → Bestandsführung); die Matrix zeigt die dortige Einstellung. Doppel-Führung ist strukturell ausgeschlossen.

**Wirkung der Führung:** Führt WorkDiary selbst, bleiben Importe aus Integrationen wie gewohnt erlaubt. Führt eine Integration einen Bereich, darf nur noch sie dort schreiben. Will eine andere Integration schreiben – ein Ticket aus Zammad anlegen oder schließen, eine Calendly-Buchung oder einen Kalendertermin übernehmen, einen Eröffnungsbestand aus JTL-Wawi buchen, eine Aufgabe importieren –, schreibt sie nichts. Stattdessen entsteht im **Integrations-Eingang** ein Fall „Datenführerschaft“: **Einmalig übernehmen** schreibt den Stand genau einmal, **Verwerfen** lässt ihn fallen, und derselbe Vorgang kommt nicht wieder. Lesen und Anzeigen bleiben immer erlaubt.

**Wechsel der Führung:** Jede Änderung wird auditiert. Offene Konflikte bleiben beim Wechsel im Eingang, die Matrix nennt ihre Zahl je Bereich. Lässt die neue Führung den Schreibweg zu, schließt der nächste Abgleich den Fall selbst; sonst übernehmen oder verwerfen Sie ihn dort.

**Extern geführte Ticket-Queues:** Importiert Zammad Service-Tickets in eine Queue, führt Zammad diese Queue. Neue Tickets entstehen dort nur über den Import; manuelle Anlage, Mail-Buchung und Mail-Antworten in diese Queue nimmt WorkDiary nicht an. Status und Kundenkommunikation pflegen Sie im führenden System.

**Dokumente:** Führt eine Integration die Dokumente, legt nur sie über den Cloud-Dokumenteingang DMS-Dokumente und neue Versionen an. Dateien anderer Cloud-Verbindungen und abgerufene Belege anderer Programme (etwa aus easybill) warten als Fall „Datenführerschaft“; **Einmalig übernehmen** lädt die Datei neu und legt sie an. Eigene Uploads, Protokolle und Belege bleiben in WorkDiary möglich – das DMS ist zugleich der Nachweisspeicher von WorkDiary.

**Kunden und Lieferanten:** Diese Zeile ist zugleich die Stammdatenhoheit gegenüber der Buchhaltung. Wählen Sie ein Buchhaltungsprogramm (Lexoffice, sevDesk, easybill), führt es die Stammdaten: WorkDiary überträgt keine Kontakte dorthin, und der Kontaktabgleich des Programms übernimmt Namen, Steuerdaten und Anschriften. Andere Programme ändern dann keine Kunden und Lieferanten, die Toggl-Migration legt keine an; deren Stände warten im Integrations-Eingang. „Buchhaltung (jedes Programm, das Kunden schreibt)“ lässt jedes angebundene Programm schreiben. Lokale Änderungen bleiben möglich, gehen aber nicht zurück, und der nächste Abgleich überschreibt sie. Die Kundennummern vergibt weiterhin der Nummernkreis, nicht diese Zeile.

**Übernommene Fälle:** Will die Integration später einen neuen Stand desselben Vorgangs schreiben – etwa eine geänderte Aufgabe oder eine neue Dateirevision –, öffnet sich der übernommene Fall wieder. Verworfene Fälle bleiben verworfen.

**Rechnungshoheit:** Für die Fakturierung gilt dasselbe Prinzip:
Genau ein Programm führt die Rechnungen – WorkDiary, Lexoffice oder
DATEV. Den Fakturierungsweg stellen Sie als **Standard je
Organisation** ein und können ihn **je Kunde** übersteuern. Es gilt
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
unverändert. Klären Sie vor dem Wechsel, welche offenen Posten noch über
den alten Weg abgeschlossen werden sollen.

**Empfehlung:** Halten Sie die Matrix bewusst schlank – übertragen Sie die
Führung nur dort an eine Integration, wo das Fremdsystem tatsächlich
die maßgebliche Datenquelle ist.
