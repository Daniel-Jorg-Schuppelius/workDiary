---
title: "Meine Auswertungen"
topic: reports.my-reports
version: 1
keywords:
    - Mein Monat
    - Mein Jahr
    - Arbeitsbilanz
    - eigene Stunden
    - Stundenübersicht
    - Monatsübersicht
    - Jahresübersicht
    - Überstunden prüfen
    - Soll-Ist-Vergleich
    - Stundennachweis drucken
    - Saldo
audience: []
related:
    - reports.overview
    - reports.attendance
    - time-accounts.flex
    - attendance.manage
    - time-entries.edit
---

Unter **Auswertungen** → **Persönlich** stehen jeder Person drei Auswertungen
der eigenen Zeiten zur Verfügung: **Mein Monat**, **Mein Jahr** und
**Arbeitsbilanz**. Sie zeigen ausschließlich Ihre eigenen Einträge. Grundlage
sind Ihre Zeiteinträge, bei der Arbeitsbilanz zusätzlich Ihre Stempelzeiten und
Ihr Arbeitszeitmodell. Die Auswertungen sind keine eigene Datenquelle: Stimmt
eine Zahl nicht, korrigieren Sie den Zeiteintrag oder die Stempelung – beim
nächsten Aufruf rechnet die Auswertung neu.

## Zeitraum wählen

Alle drei Seiten richten sich nach dem Zeitraum in der Kopfzeile. Klicken Sie
auf das Kalendersymbol (**Zeitraum auswählen**) und wählen Sie unter
**Schnellauswahl** etwa **Dieser Monat**, **Letzter Monat** oder **Dieses
Jahr**. Die Pfeile **Vorige Periode** und **Nächste Periode** blättern um einen
Zeitraum weiter; auf größeren Bildschirmen tragen Sie in der Kopfzeile auch ein
eigenes Von- und Bis-Datum ein und bestätigen mit **Übernehmen**. Der aktive
Zeitraum steht als Hinweis in der Filterleiste der Seite.

- **Mein Monat** zeigt immer den Kalendermonat, in dem der Zeitraum beginnt.
- **Mein Jahr** zeigt das Kalenderjahr, in dem der Zeitraum beginnt.
- **Arbeitsbilanz** wertet den Zeitraum genau vom ersten bis zum letzten Tag aus.

## Mein Monat

**Auswertungen** → **Persönlich** → **Mein Monat** listet alle Ihre
Zeiteinträge des Monats Tag für Tag auf.

- Jeder Tag beginnt mit einer Kopfzeile mit Datum, Tagessumme der Dauer und
  Tagessumme des Erlöses; Sonntage sind rot hervorgehoben.
- Darunter folgen die Einträge mit den Spalten **Zeit** (Beginn und Ende),
  **Art**, **Kunde / Projekt**, **Tätigkeit / Beschreibung** (Aufgabe und
  Beschreibung), **Dauer** und **Erlös**. Der Erlös ist der für den Eintrag
  berechnete Betrag; nicht abrechenbare Zeiten stehen mit 0 €.
- Über der Tabelle stehen die Monatssummen der Stunden und des Erlöses, am Ende
  die Zeile **Gesamt**.
- Zwei Diagramme: **Stunden pro Tag** als Verlauf über den Monat und **Stunden
  pro Woche nach Art**, je Kalenderwoche nach Art gestapelt.

Filter: **Kunde**, **Projekt** und **Art** mit **Alle**, **Arbeit**, **Reise**
(in der Tabelle als **Anfahrt** gekennzeichnet) und **Bereitschaft**. Eine
Auswahl wirkt sofort; **Zurücksetzen** hebt alle Filter auf.

Export: Der Knopf **PDF** erzeugt die Tagesliste mit Summen und einem Diagramm
der Stunden pro Tag. Im Menü **Export** finden Sie **CSV** und **Excel** mit
einer Zeile je Eintrag: Datum, Beginn, Ende, Art, Kunde, Projekt, Aufgabe,
Beschreibung, Minuten und Erlös. Alle Exporte übernehmen die gesetzten Filter.

## Mein Jahr

**Auswertungen** → **Persönlich** → **Mein Jahr** zeigt Ihre Stunden über das
ganze Kalenderjahr.

- Die Kachel **Jahressumme** nennt die Stunden des Jahres.
- Die Heatmap **Stunden pro Tag** hat eine Zeile je Monat und eine Spalte je
  Tag (1 bis 31). Je kräftiger ein Feld gefärbt ist, desto mehr Stunden; die
  Färbung richtet sich nach dem höchsten Tageswert des Jahres. Beim Überfahren
  erscheinen Datum und Stunden, Sonntage sind rot markiert.
- Das Balkendiagramm **Stunden pro Monat** zeigt die Monatssummen.
- Ein Klick auf einen Monatsnamen in der Heatmap oder auf einen Balken öffnet
  **Mein Monat** für genau diesen Monat, mit denselben Filtern.

Filter: **Kunde**, **Projekt** und **Art** wie bei **Mein Monat**. Einen Export
gibt es auf dieser Seite nicht.

## Arbeitsbilanz

**Auswertungen** → **Persönlich** → **Arbeitsbilanz** vergleicht für den
gewählten Zeitraum Soll, Anwesenheit und erfasste Zeit. Die Kacheln oben:

- **Soll**: Sollzeit aus Ihrem Arbeitszeitmodell. Feiertage und Tage mit
  genehmigtem Urlaub haben kein Soll.
- **Anwesenheit**: Ihre Stempelzeiten abzüglich Pausen. Stornierte Stempelungen
  zählen nicht; eine laufende Stempelung wird bis zum aktuellen Zeitpunkt
  mitgerechnet.
- **Erfasst**: Ihre Zeiteinträge der Arten Arbeit und Anfahrt. Bereitschaft
  sowie Einträge mit der Tätigkeit **Pause** oder **Abwesenheit** zählen nicht.
- **Unverteilt**: Anwesenheit, die noch nicht durch Zeiteinträge belegt ist
  (Anwesenheit minus Erfasst, nie negativ).
- **Saldo**: Erfasst minus Soll – grün bei Plus, rot bei Minus.

Darunter stehen die Diagramme **Ist- und Soll-Stunden je Tag** (bei Zeiträumen
über 62 Tagen **Ist- und Soll-Stunden je Kalenderwoche**) und **Ist- und
Soll-Stunden je Monat**, jeweils mit einer Medianlinie. Der Block **Verteilung
nach Tätigkeit** nennt die erfassten Stunden je Tätigkeit. Die Tabelle zeigt je
Tag **Datum**, **Soll**, **Anwesenheit**, **Pause**, **Erfasst**, **Unverteilt**
und **Saldo** sowie die Zeile **Summe**; Tage ganz ohne Soll, Anwesenheit und
Erfassung werden ausgelassen. Ein Klick auf eine Spaltenüberschrift sortiert.

Export: **PDF** mit Kennzahlen und Tagestabelle.

## Wer was sieht

- **Mein Monat** und **Mein Jahr** zeigen immer nur Ihre eigenen Einträge, auch
  für Administratoren.
- Die **Arbeitsbilanz** zeigt standardmäßig Ihre eigene Bilanz. Nur
  Administratoren sehen eine Filterleiste mit **Mitarbeiter** und **Team** und
  können damit die Bilanz einer anderen Person derselben Organisation öffnen;
  **Team** grenzt dabei nur die Auswahlliste der Mitarbeitenden ein.
- Die Arbeitsbilanz rechnet nur den gewählten Zeitraum. Den fortgeschriebenen
  Stand Ihres Arbeitszeitkontos zeigt sie nicht – dazu siehe „Arbeitszeitkonto
  & Monatsfreigabe“.
