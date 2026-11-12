---
title: "Drilldown von der Kennzahl zu den Einzelfällen"
topic: reports.drilldown
version: 3
keywords:
    - Drill-down
    - Kennzahl aufschlüsseln
    - KPI-Details
    - Zahlen nachvollziehen
    - Datenbasis anzeigen
    - Detailliste
    - offene Punkte je Kunde
    - Defektprotokolle
    - Wiederholdefekte
    - Ursachen
    - Bericht exportieren
    - Auswertung vertiefen
audience: []
related:
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.operations
---

In der **Kundenanalyse**, der **Auftragstypanalyse** und der
**Produktanalyse** sind Zahlen anklickbar. Der Drilldown listet die
Einzelfälle hinter der Zahl – mit dem Zeitraum aus dem Seitenkopf und
den Filtern, die beim Klick aktiv waren. Die Kopfzeile nennt Kunde,
Auftragstyp oder Bereich und den Zeitraum; **Zur Kundenanalyse**, **Zur
Auftragstypanalyse** bzw. **Zur Produktanalyse** führt zurück. Die
Seiten verlangen das Recht „Auswertungen einsehen“.

## Offene Punkte

**Drilldown: Offene Punkte** zeigt die noch offenen Punkte – am Kunden
selbst, an seinen Aufträgen und Projekten, an den Aufträgen eines
Auftragstyps oder an den Assets des gewählten Bereichs (dann mit der
Spalte **Asset**). Spalten: **Titel**, **Status**, **Severity**,
**Fällig** und **Zugewiesen**; zuletzt geänderte Punkte stehen oben.
Der Klick auf die Spalte **Eskaliert** öffnet die Liste mit **Nur
eskaliert**: dann erscheinen nur blockierte Punkte.

## Defektprotokolle

**Drilldown: Defektprotokolle** listet die Mängelprotokolle an Aufträgen
im Zeitraum; in der Kundenanalyse steht er hinter der Spalte
**Nacharbeit**. Spalten: **Titel**, **Status**, **Zeitpunkt**, **Erstellt
von** und **Auftrag** – der Auftrag öffnet sich per Klick. Die neuesten
Protokolle stehen oben.

## Ursachen und Wiederholdefekte

**Drilldown: Ursache (Auftragstyp)** zeigt zu einer Ursache aus der
Auftragstypanalyse zwei Listen: **Nacharbeit mit diesem Grund** (Datum,
Auftrag, Mitarbeiter, Minuten) und **Reklamationen mit dieser
Grundursache** (Nummer, Auftrag, Status).

**Drilldown: Wiederholdefekte** nennt je Asset die Defektmeldungen im
Zeitraum und in den letzten **12 Monaten** sowie die Schweregrade. Ab drei
Defekten in zwölf Monaten trägt die Zeile das Kennzeichen
**Wiederholdefekt**; der Asset-Name öffnet die Objektakte. Sie erreichen ihn
über die Zahl in der Spalte **Wiederholdefekte** der Produktanalyse: Dann
zeigt er nur die Wiederholdefekt-Fälle der angeklickten Zeile, und die
Kopfzeile nennt den Bereich und **Nur Wiederholdefekte**.

## Export

Offene Punkte und Defektprotokolle exportieren Sie als PDF, CSV oder
Excel, die Wiederholdefekte als CSV oder Excel. Der Export enthält
dieselben Zeilen wie die Liste und nennt Zeitraum und Filter. Die
Ursachen-Seite hat keinen Export.
