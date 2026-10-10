---
title: "Anwesenheit, Plan/Ist und Abdeckung"
topic: reports.attendance
version: 7
keywords:
    - Anwesenheitsbericht
    - Stempelzeiten auswerten
    - Plan-Ist-Vergleich
    - Soll-Ist-Besetzung
    - Unterbesetzung
    - Schichtabdeckung
    - Mindestbesetzung
    - Verspätung
    - Kernzeit
    - Team-Monatsreport
    - Stunden je Mitarbeiter
    - Personentage
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - reports.my-reports
    - attendance.manage
    - planning.shifts
    - reports.utilization
    - reports.presence-emergency
---

Diese Auswertungen vergleichen, wer wann anwesend war, mit dem, was geplant
war: Stempelzeiten gegen das Arbeitszeitmodell, Schichten gegen die
Soll-Besetzung und geplante Auftragszeiten gegen gebuchte Zeiten. Dazu gehören
**Anwesenheit** und **Plan/Ist** im Menübereich **Auswertungen** →
**Persönlich** sowie **Coverage** und **Monat pro Mitarbeiter** unter
**Auswertungen** → **Team**. Alle Seiten zeigen nur Daten der aktiven
Organisation. Korrekturen nehmen Sie an der Stempelung, am Zeiteintrag, am
Arbeitszeitmodell oder im Schichtplan vor; die Auswertung ist keine eigene
Datenquelle.

## Anwesenheit

**Auswertungen** → **Persönlich** → **Anwesenheit** öffnet die
**Anwesenheits-Auswertung** für den Zeitraum aus der Kopfzeile (Kalendersymbol
**Zeitraum auswählen**).

Die Tabelle hat eine Zeile je Person und die Spalten:

- **Arbeitstage** und **Soll**: Tage und Sollzeit laut Arbeitszeitmodell je
  Wochentag. Feiertage und Tage mit genehmigter Abwesenheit – etwa Urlaub,
  Sonderurlaub, unbezahlter Urlaub oder Krankheit – haben kein Soll und zählen nicht als
  Arbeitstag, genau wie in der **Arbeitsbilanz** und im Arbeitszeitkonto. Ohne
  Arbeitszeitmodell stehen beide Werte auf 0.
- **Anwesend**: abgeschlossene Stempelungen nach Abzug der Pausen; laufende und
  stornierte Stempelungen zählen nicht.
- **Gebucht**: alle Zeiteinträge im Zeitraum, gleich welcher Art.
- **Saldo**: Anwesend minus Soll, rot bei Minus, grün bei Plus.

Die Zeile **Gesamt** und die Kacheln **Soll**, **Anwesend**, **Gebucht** und
**Saldo** fassen alle angezeigten Personen zusammen. Die Heatmap **Anwesenheit
je Mitarbeiter und Wochentag** zeigt, an welchen Wochentagen jemand wie lange
anwesend war; **Anwesenheit im Zeitverlauf** zeigt die Summe je Tag, bei
Zeiträumen über 62 Tagen je Kalenderwoche. Ein Klick auf eine Spaltenüberschrift
sortiert die Tabelle.

Die Filter **Bereich** mit **Nur eigene** oder **Gesamtes Team** (alle
Personen der Organisation) sowie **Mitarbeiter** und **Team** sehen
Administratoren und Personen mit dem Recht **Anwesenheiten einsehen**. Alle
anderen sehen nur ihre eigene Zeile.

Export: **PDF** mit Tabelle und Heatmap; im Menü **Export** **CSV** und
**Excel** mit Arbeitstagen, Soll, Anwesend, Gebucht und Saldo in Minuten je
Person und einer Gesamtzeile.

## Plan/Ist

**Auswertungen** → **Persönlich** → **Plan/Ist** vergleicht Soll und Ist in
mehreren Sichten, die Sie über Reiter oben auf der Seite wechseln:
**Anwesenheit**, **Team**, **Organisation**, **Schichten**, **Projekte** und
**Standorte**. Sie sehen nur die Reiter, für die Sie berechtigt sind.

Der Zeitraum wird hier nicht aus der Kopfzeile übernommen: Stellen Sie ihn in
der Filterleiste mit **Von** und **Bis** ein. Ohne Angabe gilt der laufende
Monat; beim Wechsel des Reiters bleibt der Zeitraum erhalten. Diese Seiten
bieten keinen Export.

### Reiter Anwesenheit

Die Seite **Plan/Ist Anwesenheit** zeigt Ihre eigenen Tage mit den Kacheln
**Plan**, **Ist**, **Δ** und **Warnungen** und je Tag die Spalten:

- **Plan**: Sollzeit laut Arbeitszeitmodell für den Wochentag; „—“ an Tagen
  ohne Arbeitszeitmodell oder ohne Arbeitstag sowie an Feiertagen und an Tagen
  mit genehmigter Abwesenheit wie Urlaub, Sonderurlaub, unbezahltem Urlaub oder Krankheit.
- **Ist**: die gestempelte Anwesenheit des Tages; stornierte Stempelungen
  zählen nicht.
- **Δ**: Ist minus Plan, Minuswerte in Rot.
- **Start P/I**: Beginn der Kernzeit laut Arbeitszeitmodell und erste
  Stempelung des Tages, dahinter die Abweichung in Minuten.
- **Warnungen**: verspäteter Beginn, wenn die erste Stempelung mehr als 15
  Minuten nach dem Kernzeitbeginn liegt, und Stundenabweichung, wenn das Ist
  um mehr als 10 % vom Plan abweicht. Tage ohne Plan – also auch Feiertage
  und Urlaubstage – erhalten keine Warnung.

Öffnen Sie die Seite aus dem Reiter **Team** oder **Organisation** für eine
andere Person, steht oben der Hinweis **Ansicht für** mit ihrem Namen.

### Reiter Team und Organisation

**Team** zeigt die Mitglieder eines Ihrer Teams; haben Sie mehrere Teams,
wählen Sie es im Feld **Team**. Administratoren und Personen mit dem
Organisationsrecht können jedes nicht archivierte Team wählen.
**Organisation** zeigt alle Personen der Organisation unter **Alle
Mitarbeitenden**.

Beide Sichten summieren je Person **Plan (h)**, **Ist (h)**, **Differenz (h)**
und **Warnungen** nach derselben Tageslogik wie der Reiter **Anwesenheit**. Die
Lupe **Details** öffnet die Tagesansicht der Person für denselben Zeitraum. Mit
dem Teamrecht geht das nur für Mitglieder Ihrer eigenen Teams.

### Reiter Schichten, Projekte und Standorte

- **Schichten**: Plan sind die veröffentlichten und bestätigten Schichten des
  Schichtplans mit der Dauer ihres Zeitfensters (Nachtschichten über
  Mitternacht eingeschlossen), Ist ist die Überschneidung der Stempelzeiten der
  eingeteilten Person mit diesem Fenster; stornierte Stempelungen zählen nicht.
  Kacheln **Plan**, **Ist**, **Differenz** und **Abdeckung** (Ist im Verhältnis
  zum Plan; unter 100 % hervorgehoben). Mit **Gruppierung** wählen Sie
  **Täglich** oder **Wöchentlich** für das Diagramm **Plan vs. Ist je Tag**
  bzw. **Plan vs. Ist je Woche**. Die Tabelle **Je Schichttyp** nennt
  **Schichten**, **Plan (h)**, **Ist (h)**, **Differenz (h)** und
  **Abdeckung**. Schichten ohne Zeitfenster sind mit **ohne Zeitfenster**
  markiert: Sie haben kein Plan, als Ist zählt die Tagesanwesenheit der Person.
- **Projekte**: Plan ist die Summe der geplanten Dauer der Aufträge, deren
  Zeitraum den gewählten Zeitraum berührt; Ist sind die gebuchten Zeiten je
  Projekt. Die geplante Dauer ist das Feld **Geplante Dauer (HH:MM)** am
  Auftrag; ist es leer, zählt die Servicedauer eines disponierten Auftrags, sonst die Länge des Zeitfensters bzw. die Dauer des
  Termins. **Aufträge (geplant)** zählt die Aufträge mit einer solchen Dauer.
  Kacheln **Plan**, **Ist**, **Differenz** und **Abrechenbar (Ist)**, das
  Diagramm **Top-Projekte: Plan vs. Ist** (die zwölf Projekte mit den meisten
  Ist-Stunden) und die Tabelle **Je Projekt** mit **Projekt**, **Kunde**,
  **Aufträge (geplant)**, **Plan (h)**, **Ist (h)**, **Abrechenbar (h)** und
  **Differenz (h)**. Projekte ohne geplante Aufträge tragen den Hinweis **ohne
  Solldaten** – das ist kein Alarm. Zeiten ohne Projekt stehen in der Zeile
  **Ohne Projekt**. Den Abgleich mit Zeit- und Geldbudgets liefert die
  **Wirtschaftlichkeit**.
- **Standorte**: Für Standorte gibt es keine Solldaten; die Sicht zeigt nur die
  Ist-Verteilung der Zeiten aus der standortbasierten Zeiterfassung. Kacheln
  **Ist**, **Ortsbesuche** und **Personen**, Diagramm **Ist-Zeiten je Standort
  (Top 15)** und Tabelle **Je Standort** mit **Standort**, **Kunde**,
  **Ortsbesuche**, **Personen**, **Ist (h)** und **Anteil**. Besuche an
  Geofences ohne zugeordneten Standort stehen unter **Ohne Standort-Zuordnung**
  mit dem Hinweis **Geofence ohne Standort**.

Lange Tabellen der Reiter **Projekte** und **Standorte** sind auf Seiten zu je
50 Zeilen verteilt; die Diagramme werten dagegen alle Zeilen aus, nicht nur
die angezeigte Seite.

## Coverage

**Auswertungen** → **Team** → **Coverage** vergleicht die Soll- mit der
Ist-Besetzung: Erfüllen die geplanten Schichten die Soll-Besetzung? Die
Auswertung rechnet nach denselben Regeln wie die Heatmap im Dienstplan.

- Soll je Tag und Schichttyp ist der Mindestwert (**Min**) der
  **Soll-Besetzung**, die Sie im Dienstplan je Schichttyp hinterlegen. Es gilt
  die genaueste Angabe: ein Eintrag für ein **Konkretes Datum** vor einem
  Eintrag für den **Wochentag**, dieser vor einem Eintrag, der **Immer** gilt.
  Einträge mit **Immer** gelten an jedem Tag des Dienstplans. Passt an einem
  Tag kein Eintrag, gilt für die Schichttypen, die im Dienstplan vorkommen,
  dessen **Mindestbesetzung pro Schicht**.
- Tage ohne Dienstplan haben nur dann ein Soll, wenn es planübergreifende
  Anforderungen gibt (Schalter **Für alle Dienstpläne** in der Soll-Besetzung). Gelten an einem Tag mehrere Dienstpläne, addieren sich
  ihr Soll und ihr Ist.
- Ist ist die Zahl der eingeplanten Schichten je Schichttyp und Tag. Es zählen
  nur Schichten mit dem Status **Veröffentlicht** oder **Bestätigt**; Entwürfe
  und abgesagte Schichten zählen nicht.
- Schichttypen ohne Soll im Zeitraum erscheinen nicht; Schichten an Tagen ohne
  Soll fließen nicht ein.
- Gezählt wird in Personentagen: eine Schicht einer Person an einem Tag ist ein
  Personentag.

Kacheln: **Schichttypen** (mit der Zahl der ausgewerteten Tage), **Soll
(Personentage)**, **Ist (Personentage)** mit der Differenz, **Erfüllung** (Ist
im Verhältnis zum Soll) und **Tage mit Unterdeckung**. Die Heatmap
**Deckungsgrad je Schichttyp und Wochentag** zeigt je Feld Ist/Soll und den
Prozentwert; **Fehlende Personentage je Woche** zeigt die Lücken je
Kalenderwoche. Die Tabelle **Pro Schichttyp** nennt **Soll**, **Ist**,
**Differenz**, **Erfüllung** und **Tage unter**; darunter listet **Tage mit
Unterdeckung** jeden betroffenen Tag mit **Datum**, **Schichttyp**, **Soll**,
**Ist** und **Lücke**.

Zeitraum aus der Kopfzeile, höchstens 400 Tage – ein längerer Zeitraum wird nach
400 Tagen abgeschnitten. Filter **Team**: Er zählt nur Schichten von
Teammitgliedern, das Soll bleibt unverändert. Export: **PDF** mit Heatmap und
Unterdeckungstagen, **CSV** und **Excel** mit den Werten je Schichttyp.

## Monat pro Mitarbeiter

**Auswertungen** → **Team** → **Monat pro Mitarbeiter** (Seitentitel
**Team-Monatsreport**) zeigt die gebuchten Stunden aller Personen über ein
Kalenderjahr – das Jahr, in dem der Zeitraum der Kopfzeile beginnt.

- Die Tabelle hat eine Zeile je Person mit Zeiteinträgen im Jahr, eine Spalte
  je Monat, die Jahressumme der Stunden und die Summe der Erlöse in Euro; die
  letzte Zeile summiert jeden Monat.
- Über der Tabelle stehen die Jahressummen der Stunden und Erlöse.
- Das Diagramm **Stunden je Mitarbeiter** zeigt die Jahressumme je Person mit
  einer Medianlinie, die Heatmap **Stunden je Mitarbeiter und Monat** die
  Verteilung über das Jahr.
- Es zählen alle Zeiteinträge, gleich welcher Art.

Filter: **Mitarbeiter** und **Team**. Export: **PDF** im Querformat mit
Heatmap, **CSV** und **Excel**.

## Wer was sieht

- **Anwesenheit**: Jede Person sieht ihre eigene Zeile. Die ganze
  Organisation sehen Administratoren und Personen mit dem Recht **Anwesenheiten
  einsehen**.
- **Plan/Ist**: Den Reiter **Anwesenheit** mit den eigenen Tagen sieht jede
  Person. Der Reiter **Team** braucht das Recht **Anwesenheitsbericht einsehen
  (Team)**, die Reiter **Organisation**, **Schichten**, **Projekte** und
  **Standorte** das Recht **Anwesenheitsbericht einsehen (Organisation)**.
  Administratoren sehen alle Reiter. In der Standardvergabe hat die Rolle
  Teamleitung das Teamrecht, die Geschäftsführung das Organisationsrecht und die
  Personalverwaltung beide.
- **Coverage** und **Monat pro Mitarbeiter** stehen nur Administratoren offen;
  anderen Personen zeigt das Menü sie nicht.
- **Coverage** und **Monat pro Mitarbeiter** setzen das Zusatzmodul
  Team-Auswertungen voraus; **Anwesenheit** und **Plan/Ist** stehen ohne das
  Modul zur Verfügung.
