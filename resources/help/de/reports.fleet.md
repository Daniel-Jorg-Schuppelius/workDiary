---
title: "Fuhrpark, Fahrtenbuch und Lenkzeiten"
topic: reports.fleet
version: 1
keywords:
    - Fahrzeugauswertung
    - Kilometerstand
    - Tankkosten
    - Kosten pro Kilometer
    - steuerliches Fahrtenbuch
    - Privatfahrten
    - geldwerter Vorteil
    - 1-Prozent-Regel
    - Dienstwagen
    - Lenkzeiten
    - Ruhezeiten
    - Fahrtunterbrechung
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - assets.fleet
    - travel-expenses.manage
    - fleet.license-checks
    - reports.arbzg-compliance
    - admin.organization-settings
    - reports.overview
---

Diese Auswertungen betreffen Fahrzeuge und Fahrten: Kilometer und
Energiekosten je Fahrzeug, das steuerliche Fahrtenbuch eines Fahrzeugs, den
Vergleich von Fahrtenbuchmethode und 1-%-Regel sowie den Nachweis der Lenk-
und Ruhezeiten. Grundlage sind die Fahrten im **Fahrtenbuch** (**Reisen &
Spesen** → **Fahrtenbuch**), die Belege im **Tank & Ladelog** (**Fuhrpark** →
**Tank & Ladelog**) und die Fahrzeugdaten unter **Fuhrpark** →
**Fahrzeuge**.

## Zeitraum und Export

- Den Zeitraum wählen Sie über die Zeitraumwahl in der Kopfzeile. Der
  1-%-Vergleich rechnet stattdessen mit einem Kalenderjahr.
- **PDF** lädt eine Druckfassung, unter **Export** stehen **CSV** und
  **Excel** bereit. Exporte übernehmen die gewählten Filter; PDF- und
  CSV-Exporte werden im Audit-Protokoll vermerkt.

## Fuhrpark

**Auswertungen** → **Ressourcen** → **Fuhrpark** öffnet die
**Fuhrpark-Auswertung** mit Fahrten, Tankungen, Energiekosten und
Erstattungen je Fahrzeug.

- Kacheln: **Fahrzeuge**, **Σ km** (mit der Zahl der Fahrten), **Tankungen /
  Ladungen** (mit Litern und kWh), **Energiekosten** (mit der Summe der
  Erstattungen) und **Ø €/km**.
- Diagramme: **Kilometer je Fahrzeug (Top 15)** und die Kilometer im Verlauf.
- Tabelle je Fahrzeug: **Fahrzeug**, **Antrieb**, **Fahrten**, **km**,
  **Erstattung**, **Tankungen**, **Liter**, **kWh**, **Energiekosten**,
  **€/km** und **Tachostand**, mit Summenzeile.

So entstehen die Werte:

- **Fahrten**, **km** und **Erstattung** stammen aus den Fahrten im
  Fahrtenbuch, denen ein Fahrzeug zugeordnet ist und deren Datum im Zeitraum
  liegt.
- **Tankungen**, **Liter**, **kWh** und **Energiekosten** stammen aus den
  Tank- und Ladebelegen, die im Zeitraum begonnen haben.
- **€/km** teilt die Energiekosten durch die Kilometer; ohne Kilometer oder
  ohne Kosten bleibt das Feld leer.
- **Tachostand** ist der letzte Kilometerstand aus den Tank- und Ladebelegen
  des Zeitraums, sonst der beim Fahrzeug hinterlegte Stand.

Filter: **Bereich** (**Nur meine Fahrten** oder **Gesamter Fuhrpark**, nur
für Administratoren) und **Mitarbeiter**. Alle anderen sehen nur ihre
eigenen Fahrten und Belege. Export als PDF, CSV und Excel.

## Fahrtenbuch-Nachweis

**Auswertungen** → **Ressourcen** → **Fahrtenbuch-Nachweis** zeigt das
steuerliche Fahrtenbuch eines Fahrzeugs: km-Stände, Fahrtart, Ziel, Zweck und
Fahrer, Summen je Fahrtart und den privaten Anteil.

- Wählen Sie das **Fahrzeug**. Fahrzeuge mit **Fahrtenbuch-Modus** stehen
  oben und sind entsprechend markiert. Ohne Administratorrechte enthält die
  Liste Fahrzeuge ohne **Standardfahrer** und die Fahrzeuge, deren
  Standardfahrer Sie sind; Administratoren sehen alle Fahrzeuge.
- Ist das Fahrzeug nicht im Fahrtenbuch-Modus, weist ein Hinweis darauf hin,
  dass Fahrten ohne km-Stände und ohne Festschreibung steuerlich kein
  Fahrtenbuch sind. Den Modus schalten Sie beim Fahrzeug mit **Fahrtenbuch-Modus
  (steuerlich)** ein.
- Kacheln: **Fahrten** (mit der Zahl der festgeschriebenen), **Σ km**, je
  Fahrtart (**Betrieblich**, **Wohnung–Arbeitsstätte**, **Privat**) die
  Kilometer und **Privater Anteil** (private Kilometer im Verhältnis zu allen
  Kilometern).
- Tabelle: **Datum**, **Start-km**, **End-km**, **km**, **Fahrtart**,
  **Ziel**, **Zweck**, **Fahrer** und **Status** (**festgeschrieben**,
  **offen** oder **storniert**, dazu **unterschrieben** und
  **Stornofahrt**). Die Fußzeile nennt die Kilometer je Fahrtart.

Die Kilometer einer Fahrt ergeben sich aus End- minus Start-km; fehlen die
Stände, zählt die erfasste Strecke, bei Hin- und Rückfahrt doppelt. Die Liste
enthält alle Fahrten des Fahrzeugs im Zeitraum, auch die anderer Fahrer.
Stornierte Originalfahrten bleiben durchgestrichen sichtbar, zählen aber in
keiner Summe.

Export als PDF, CSV und Excel, sobald ein Fahrzeug gewählt ist. CSV und Excel
enthalten zusätzlich Startadresse, Zeitpunkte von Festschreibung und
Unterschrift, die Storno-Kennzeichnung, die korrigierte Fahrt mit
Korrekturgrund sowie die Summen und den privaten Anteil.

## 1-%-Vergleich

Den **1-%-Vergleich** öffnen Sie über die gleichnamige Schaltfläche auf der
Seite **Fahrtenbuch-Nachweis**; einen eigenen Menüeintrag gibt es nicht. Er
stellt je Fahrzeug den geldwerten Vorteil nach der Fahrtenbuchmethode der
1-%-Regel gegenüber. Es ist eine vereinfachte Rechnung und keine
Steuerberatung.

- **Jahr**: das laufende und die sechs vorangegangenen Jahre; voreingestellt
  ist das Vorjahr.
- Aufgeführt sind nur Fahrzeuge im Fahrtenbuch-Modus, mit derselben
  Fahrzeugauswahl wie beim Fahrtenbuch-Nachweis.
- **Monate**: Monate mit Fahrten. **km gesamt**, **davon privat** und
  **davon Arbeitsweg** zählen nur Fahrten mit Start- und End-km; stornierte
  Originalfahrten zählen nicht.
- **Kosten gesamt**: Energiekosten aus den Tank- und Ladebelegen des Jahres
  plus sonstige Jahreskosten; der Tooltip zeigt beide Teile.
- **Fahrtenbuchmethode**: Gesamtkosten mal Anteil der privaten und der
  Arbeitsweg-Kilometer an allen Kilometern.
- **1-%-Regel**: **Bruttolistenpreis (€)**, auf volle 100 € abgerundet,
  davon 1 % je Nutzungsmonat plus 0,03 % je Kilometer **Entfernung
  Wohnung–Arbeit (km)** und Monat. Für Elektro- und begünstigte
  Hybridfahrzeuge, die ab 2019 angeschafft wurden, sinkt die Bemessung je
  nach **Anschaffungsdatum** und Listenpreis auf ein Viertel oder die Hälfte;
  die Zelle zeigt dann **Bemessung** mit 0,25 % oder 0,5 % statt 1 %. Ohne Listenpreis
  steht dort **Listenpreis fehlt**.
- **Günstiger** markiert die Methode mit dem niedrigeren Wert.

Mit dem Euro-Symbol öffnen Sie den Dialog **Sonstige Jahreskosten** für
Leasing, Versicherung, Kfz-Steuer, Wartung, Reparaturen und Abschreibung mit
**Betrag (€)** und **Hinweis**. Dafür brauchen Sie das Recht **Fahrzeuge
verwalten**. Die Seite bietet keinen Export.

## Lenkzeit-Nachweis

Der **Lenkzeit-Nachweis** ist ein Download für die Lenk- und Ruhezeiten je
Fahrer und Kalendertag. Sie finden ihn auf der Seite **ArbZG-Compliance**
(**Auswertungen** → **Finanzen & Audit**) im Menü **Export**. Er erscheint
nur, wenn in den Compliance-Einstellungen der Organisation unter **Lenk- und
Ruhezeiten** die Lenkzeitregeln eingeschaltet sind, und verlangt das Recht
**ArbZG-Compliance einsehen**.

- Ausgewertet werden die wirksamen Fahrten mit Abfahrts- und Ankunftszeit
  auf Fahrzeugen, bei denen **Lenk- und Ruhezeitregeln anwenden** gesetzt
  ist. Tachograph-Daten werden nicht gelesen.
- Spalten: **Fahrer**, **Personalnummer**, **Datum**, **Fahrzeuge**, **Erste
  Abfahrt**, **Letzte Ankunft**, **Lenkzeit**, **Längste Lenkphase ohne
  Unterbrechung**, **Unterbrechungen (Min.)**, **Ruhezeit davor** und
  **Befunde** des Tages.
- Der Download übernimmt Zeitraum und Mitarbeiterfilter der Seite und liefert
  eine CSV-Datei. Er wird im Audit-Protokoll vermerkt.

Die Befunde beruhen auf den Grenzwerten der VO (EG) 561/2006 bzw. FPersV:
höchstens 9 h Lenkzeit am Tag (zweimal je Woche 10 h), 56 h je Woche und
90 h in der Doppelwoche, 45 Minuten Fahrtunterbrechung nach 4,5 h (teilbar in
15 und 30 Minuten), 11 h tägliche Ruhezeit (höchstens dreimal je Woche 9 h)
und 45 h wöchentliche Ruhezeit (24 h mit Ausgleich). Das ist keine
Rechtsberatung; welche Vorschriften im Einzelfall gelten, klärt der Betrieb.
