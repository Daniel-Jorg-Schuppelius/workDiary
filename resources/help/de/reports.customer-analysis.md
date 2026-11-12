---
title: "Kundenanalyse"
topic: reports.customer-analysis
version: 2
keywords:
    - Kundenauswertung
    - Kundenstatistik
    - Kundenreport
    - Aufträge je Kunde
    - Zeit je Kunde
    - Nacharbeitsquote
    - nicht abrechenbare Zeit
    - Margenrisiko
    - Kunden-KPI
    - Kundenbericht
    - Kunden & Projekte
    - Stunden je Projekt
    - Erlös je Kunde
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.drilldown
    - reports.entry-type-analysis
    - reports.billing
---

Die **Kundenanalyse** zeigt je Kunde Aufwand, Nacharbeit und nicht
abrechenbare Zeit im Zeitraum aus dem Seitenkopf; sie verlangt das Recht
„Auswertungen einsehen“. **Kunden & Projekte** schlüsselt Stunden und
Erlös je Kunde und Projekt auf.

## Kundenanalyse

Spalten der Tabelle:

- **Aufträge** im Zeitraum, **Gesamt** (gebuchte Minuten),
  **Abrechenbar**, **Nicht abrechenbar** und deren **Anteil %** – ein
  hoher Anteil ist ein Frühindikator für Margenrisiken.
- **Nacharbeit**: Aufträge mit Mängelprotokoll im Zeitraum.
- **Offene Punkte** am Kunden, an seinen Aufträgen und Projekten, davon
  **Eskaliert** (blockiert).
- **Ø Min./Auftrag** und **Trend 30d**: Aufträge der letzten 30 Tage
  abzüglich der 30 Tage davor.

Filter: **Projekt**, **Mitarbeiter** und **Auftragstyp**, dazu
**Mindest-Aufwand (Minuten)**, **Kunden ohne Werte ausblenden** und
**Ausgeblendete Kunden einbeziehen** (Kunden mit „In Auswertungen
ausblenden“).

Klickziele: Der Kundenname öffnet die Kundenakte, **Aufträge** und
**Gesamt** das Auftragsbuch für den Zeitraum. **Nacharbeit**, **Offene
Punkte** und **Eskaliert** öffnen den Drilldown mit denselben Filtern.
Darüber stehen **Stunden je Kunde (Top 20)** – ein Klick öffnet Kunden &
Projekte für diesen Kunden –, der Auftragseingang im Verlauf, **Offene
Punkte je Kunde (Top 15)** sowie die Listen **Top 5 Aufwand**, **Top 5
Nacharbeit** und **Top 5 nicht abrechenbar**. Export als PDF, CSV oder
Excel.

## Kunden & Projekte

Die Tabelle **Kunde / Projekt** nennt je Kunde die Summe und darunter
die Projekte mit **Stunden** und **Erlös**, Projekte nach Stunden
absteigend; **Σ Std.** und **Σ €** stehen darüber. Der Erlös ist die
Summe der gespeicherten Beträge der Zeiteinträge. Die Diagramme zeigen
**Stunden je Kunde (Top 20)** – ein Klick filtert die Seite auf den
Kunden – und **Top-Projekte nach Stunden**.

Filter: **Kunde**, **Projekt** und **Ausgeblendete Kunden einbeziehen**.
Wer alle Zeiteinträge sehen darf, wählt unter **Bereich** zwischen
**Nur meine** und **Gesamtes Team** und filtert nach **Mitarbeiter**;
alle anderen sehen ihre eigenen Zeiten. Export als PDF, CSV oder Excel.
