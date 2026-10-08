---
title: "Auswertungen verwenden"
topic: reports.overview
version: 3
keywords:
    - Berichte
    - Reports
    - Statistik
    - Kennzahlen
    - KPI
    - Drilldown
    - Bericht exportieren
    - Umsatz je Produkt
    - Mindestlohn prüfen
    - Lenk- und Ruhezeiten
    - Liquiditätsvorschau
    - Reporting
audience: []
related:
    - reports.my-reports
    - reports.attendance
    - reports.personnel
    - reports.operations
    - reports.fleet
    - reports.billing
    - reports.compliance
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.drilldown
    - reports.saved-views
    - exports.payroll
---

Der Menübereich **Auswertungen** in der Seitenleiste bündelt alle Berichte –
von der eigenen Monatsübersicht bis zu Finanz- und Prüfberichten. Auswertungen
verdichten vorhandene Daten wie Zeiten, Stempelungen, Abwesenheiten, Aufträge
und Rechnungen nach Zeitraum, Person, Team, Projekt oder Kunde. Sie sind keine
eigene Datenquelle: Korrekturen erfolgen am ursprünglichen Auftrag, Zeit-,
Abwesenheits- oder Stammdatensatz. Bei personenbezogenen und finanziellen
Auswertungen gilt das Need-to-know-Prinzip. Dieses Thema erklärt, wie die
Auswertungen aufgebaut sind, und führt zu den Fachthemen der einzelnen Berichte.

## Die Seite Übersicht

**Auswertungen** → **Übersicht** zeigt oben Ihre eigenen Kennzahlen für den
Zeitraum der Kopfzeile: **Meine Stunden**, **Erfasste Tage**, **Ø pro Tag**
(bezogen auf die erfassten Tage) und **Aktive Projekte** (Projekte, auf die Sie
Zeit gebucht haben). Das erste Diagramm zeigt Ihre Stunden – bis 31 Tage als
**Stunden pro Tag**, bis etwa ein halbes Jahr als **Stunden pro Woche**,
darüber als **Stunden pro Monat**. **Top-Projekte nach Stunden** nennt Ihre
zehn Projekte mit den meisten Stunden.

Darunter steht je Menügruppe eine Karte mit allen Auswertungen, die Sie öffnen
dürfen. Die Auswahl entspricht genau der Seitenleiste.

## Menü und Sichtbarkeit

Die Auswertungen sind in Gruppen geordnet: **Übersicht**, **Persönlich**,
**Team**, **Projekte & Kunden**, **Ressourcen** und **Finanzen & Audit**. Ein
Eintrag erscheint nur, wenn Ihre Organisation das zugehörige Modul nutzt und
Sie das nötige Recht haben. Die Gruppen **Team**, **Projekte & Kunden** und
**Ressourcen** setzen das Zusatzmodul Team-Auswertungen voraus. Einträge, die
Sie über „Menü anpassen & Alle Funktionen“ ausgeblendet haben, fehlen auch auf
der Übersichtsseite.

## Zeitraum

Die meisten Auswertungen richten sich nach dem Zeitraum in der Kopfzeile.
Klicken Sie auf das Kalendersymbol (**Zeitraum auswählen**) und wählen Sie unter
**Schnellauswahl** zum Beispiel **Heute**, **Diese Woche**, **Letzter Monat**,
**Dieses Quartal** oder **Letzte 90 Tage**. Die Pfeile **Vorige Periode** und
**Nächste Periode** blättern weiter; auf größeren Bildschirmen geben Sie in der
Kopfzeile auch ein eigenes Von- und Bis-Datum ein und bestätigen mit
**Übernehmen**. Der Zeitraum gilt für alle Seiten, bis Sie ihn ändern oder sich
abmelden; ohne Auswahl gilt **Dieser Monat**. Die Filterleiste zeigt ihn als
Hinweis.

Ausnahmen nennt das jeweilige Fachthema, zum Beispiel:

- **Mein Monat**, **Mein Jahr** und **Monat pro Mitarbeiter** zeigen den Monat
  bzw. das Jahr, in dem der Zeitraum beginnt.
- **Plan/Ist** hat eigene Felder **Von** und **Bis**, ohne Angabe gilt der
  laufende Monat.
- **Qualifikationen** und **Probleme & Schulung** zeigen den Stand von heute.

Ein Link mit Datumsangaben – etwa aus einer gespeicherten Auswertung – öffnet
den Bericht mit genau diesem Zeitraum.

## Filter

- Die Filterleiste bietet je nach Bericht Felder wie **Kunde**, **Projekt**,
  **Mitarbeiter**, **Team** oder **Status**. Eine Auswahl wirkt meist sofort;
  **Zurücksetzen** hebt alle Filter auf.
- Manche Felder sehen nur Administratoren, etwa **Bereich** mit **Nur eigene**
  oder **Gesamtes Team**. Alle anderen sehen dort nur ihre eigenen Daten.
- Kunden, die in ihren Stammdaten mit **In Auswertungen ausblenden** markiert
  sind, fehlen in den kunden- und projektbezogenen Berichten. Der Schalter
  **Ausgeblendete Kunden einbeziehen** – er erscheint nur, wenn es solche
  Kunden gibt – nimmt sie wieder auf; wählen Sie einen solchen Kunden direkt
  im Filter, wird er ebenfalls angezeigt.
- Einen eingestellten Bericht speichern Sie als benannte Ansicht, siehe
  „Gespeicherte Auswertungen“.

## Export

- **PDF** lädt eine Druckfassung im Dokumentdesign Ihrer Organisation; das
  Menü **Export** bietet **CSV** und **Excel**. Nicht jeder Bericht hat alle
  Formate, manche haben keinen Export.
- Exporte übernehmen Zeitraum und Filter der Seite.
- CSV-Dateien sind in der Standardeinstellung mit Semikolon getrennt und in
  UTF-8 gespeichert. Die ersten Zeilen beginnen mit # und nennen den Bericht,
  den Erstellungszeitpunkt und einen Fingerabdruck der Filter – so lässt sich
  eine Datei später ihrem Stand zuordnen.
- Exporte werden mit Bericht, Format und Filtern im Audit-Log vermerkt.

## Drilldown

Viele Kennzahlen, Diagrammpunkte und Tabellenzeilen sind anklickbar und führen
zu den Datensätzen dahinter oder zu einer genaueren Sicht – etwa von **Mein
Jahr** zu **Mein Monat** oder vom Reiter **Team** in **Plan/Ist** zu den Tagen
einer Person. Mehr dazu unter „Drilldown von Kennzahl zu Auftrag“.

## Rechte

- Die persönlichen Auswertungen stehen allen offen und zeigen nur eigene
  Daten.
- Organisationsweite Analysen von Kunden, Erlösen und Lieferanten verlangen
  das Recht **Auswertungen einsehen** oder die Administratorrolle.
- Manche Berichte haben ein eigenes Recht, etwa **Anwesenheitsbericht
  einsehen (Team)** für Plan/Ist oder **Sicherheitsereignis-Register sehen**
  für den Arbeitsschutz.
- Einige Teamsichten – etwa **Coverage** oder die Teamansicht von **Urlaub &
  Flex** – bleiben Administratoren vorbehalten.
- Jede Auswertung zeigt nur Daten der aktiven Organisation.

## Welche Auswertung wofür

Die folgende Übersicht folgt den Menügruppen und nennt je Bericht das
Fachthema mit allen Einzelheiten.

### Persönlich

**Mein Monat**, **Mein Jahr** und **Arbeitsbilanz** zeigen Ihre eigenen
Zeiten Tag für Tag, über das Jahr und im Abgleich mit dem Soll – siehe „Meine
Auswertungen“. **Anwesenheit** und **Plan/Ist** stehen ebenfalls in dieser
Gruppe; sie gehören zum nächsten Abschnitt.

### Anwesenheit, Planung und Zeitkonten

- **Anwesenheit**, **Plan/Ist**, **Coverage** und **Monat pro Mitarbeiter**
  vergleichen Stempelzeiten, Schichten und gebuchte Stunden mit Soll und
  Planung – siehe „Anwesenheit, Plan/Ist und Abdeckung“.
- **Woche pro Mitarbeiter** zeigt je Person die Stunden jedes Wochentags mit
  Wochensumme, höchstens zwölf Wochen auf einmal. Die Sicht auf alle Personen
  haben Administratoren und Personen mit dem Recht **Alle Zeiteinträge sehen**.
- **Auslastung** setzt erfasste, abrechenbare und fakturierte Zeit ins
  Verhältnis – siehe „Auslastung & Realisierung“.
- **Notfall-Anwesenheit** zeigt, wer im Gebäude, außer Haus oder abwesend ist –
  siehe „Notfall-Anwesenheitsliste“.
- **Zuschlags-Prognose** schätzt aus den geplanten Diensten die zu erwartenden
  Zuschlagsminuten je Monat und Lohnart; sie verlangt das Recht **Auswertungen
  einsehen** – siehe „Abrechnung, Spesen, Auszahlungen und Umsatz“.
- **Zeitkonten** und **Periodenvergleich** erklärt das Thema „Zeitkonten“, den
  **Urlaubsplan** das Thema „Urlaubsplan (Jahresübersicht)“.

### Personal

**Urlaub & Flex**, **Krankheiten**, **Qualifikationen**, **Arbeitsschutz** und
**Probleme & Schulung** beschreibt das Thema „Personal: Urlaub, Krankheit, Qualifikation,
Arbeitsschutz“. Der **Kohortenvergleich** stellt Kennzahlen vor und nach einer
Fortbildung gegenüber – siehe „Kohortenvergleich (vor/nach Fortbildung)“.
**Schulungen** gehört zum „Trainingsmanagement“, die Kursauswertung
**Auswertung** zur „Lernplattform“.

### Kunden und Projekte

- **Kundenanalyse** und **Kunden & Projekte** erklärt das Thema „Kundenanalyse“,
  **Kundenwert** und **Kundenbindung** haben eigene Themen gleichen Namens,
  **Auftragstypanalyse** steht in „Auftragstyp-Analyse“.
- **Operations**, **Zeitaufteilung**, **Prozedur-Abweichungen**, **Blockierte
  Prozedurläufe**, **Produktanalyse**, **Projekt-Details** und **Inaktive
  Projekte** sind betriebliche Auswertungen – siehe „Betrieb: Zeitaufteilung, Prozeduren, Material, Notdienst“. Dort ist
  auch die **Datenqualität** beschrieben.
- **SLA** und **SLA-Verträge** beschreibt das Thema „SLA, Verträge & Service-Level“.

### Ressourcen

- **Fuhrpark** zeigt je Fahrzeug Kilometer, Verbrauch, Tank- und Ladekosten
  sowie die Kosten je Kilometer. Der
  **Fahrtenbuch-Nachweis** liefert das steuerliche Fahrtenbuch je Fahrzeug und
  Zeitraum mit Fahrtarten und privatem Anteil, dazu den **1-%-Vergleich**. Der
  **Lenkzeit-Nachweis** belegt Lenk- und Ruhezeiten je Fahrer. Alle drei
  erklärt das Thema „Fuhrpark, Fahrtenbuch und Lenkzeiten“.
- **Materialien** und **Notdienst** gehören zu „Betrieb: Zeitaufteilung, Prozeduren, Material, Notdienst“.
- **Cloud-Dokumenteingang** erklärt das gleichnamige Thema.

### Finanzen und Audit

- **Wirtschaftlichkeit**, **Zahlungsverhalten**, **Lieferantenanalyse** und
  **Lieferantenwert** haben eigene Themen gleichen Namens.
- **Abrechnung** und **Umsatz je Produkt** – siehe „Abrechnung, Spesen, Auszahlungen und Umsatz“. **Umsatz je
  Produkt** rechnet aus lokalen Rechnungen und aus den Rechnungen angebundener
  Systeme wie Lexoffice, auch je Artikelkategorie; Gutschriften und
  Stornobelege mindern den Umsatz.
- **Spesen** fasst Spesen je Person, Kategorie und Monat zusammen; die Sicht
  auf alle Personen haben nur Administratoren. **Externe Auszahlungen**
  berechnet die Vergütung externer Mitarbeitender ohne Lohnabrechnung und ist
  nur mit dem Recht **Personal-/Lohndaten verwalten** sichtbar. Beide
  beschreibt das Thema „Abrechnung, Spesen, Auszahlungen und Umsatz“.
- **Finanzberichte** und **BWA & Budget** gibt es nur bei lokal geführter
  Buchhaltung. Sie lesen ausschließlich festgeschriebene Buchungen; dazu gehört
  die **Liquiditätsvorschau**, standardmäßig auf dreizehn Wochen – siehe
  „Abschluss und Auswertungen“.
- **ArbZG-Compliance** prüft die Ist-Arbeitszeiten gegen das
  Arbeitszeitgesetz – siehe „ArbZG-Compliance“. Die Reiter **Dashboard** und
  **Verstoß-Historie** dieser Seite beschreibt das Thema „Nachweise: Audit-Aktivität, Compliance und Mindestlohn“. Von dort
  erzeugen Sie auch die Nachweise für Aufsichtsbehörden: den **MiLoG-Nachweis
  (Zoll)** zur Mindestlohn-Prüfung mit Beginn, Ende und Dauer je Arbeitstag –
  ebenfalls in „Nachweise: Audit-Aktivität, Compliance und Mindestlohn“ – und, wenn Ihre Organisation Lenkzeiten
  erfasst, den **Lenkzeit-Nachweis** über Lenk- und Ruhezeiten je Fahrer –
  siehe „Fuhrpark, Fahrtenbuch und Lenkzeiten“.
- **Audit-Aktivität** fasst das Audit-Log nach Ereignis, Person und
  Objektart zusammen und steht nur Administratoren offen – siehe
  „Nachweise: Audit-Aktivität, Compliance und Mindestlohn“.
