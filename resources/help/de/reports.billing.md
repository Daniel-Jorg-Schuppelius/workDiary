---
title: "Abrechnung, Spesen, Auszahlungen und Umsatz"
topic: reports.billing
version: 3
keywords:
    - offene Forderungen
    - Fälligkeitsübersicht
    - unabgerechnete Zeiten
    - Umsatz je Kunde
    - Mahnstufen
    - Annahmequote Angebote
    - Spesenübersicht
    - Honorar Externe
    - Freelancer auszahlen
    - Zuschläge vorausplanen
    - Umsatz je Artikel
    - Kategorieumsatz
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
    - personalverwaltung
    - teamleitung
related:
    - invoices.manage
    - finance.dunning
    - finance.incoming-invoices
    - travel-expenses.manage
    - org.members
    - admin.surcharge-rules
    - articles.master
    - reports.economics
---

Diese Auswertungen bündeln das Geld rund um Leistungen und Personal: Stand
der Rechnungen und offenen Forderungen, noch nicht abgerechnete Zeit,
Spesen, Auszahlungen an externe Mitarbeiter, voraussichtliche Zuschläge aus
dem Schichtplan und den Umsatz je Artikel. Die meisten Seiten finden Sie
unter **Auswertungen** → **Finanzen & Audit**, die **Zuschlags-Prognose**
unter **Auswertungen** → **Team**.

## Zeitraum und Export

- Den Zeitraum wählen Sie über die Zeitraumwahl in der Kopfzeile. Die
  **Zuschlags-Prognose** blickt stattdessen vom laufenden Monat aus nach
  vorn.
- **PDF** lädt eine Druckfassung, unter **Export** stehen **CSV** und
  **Excel** bereit; welche Formate es gibt, steht beim jeweiligen Bericht.
  Exporte übernehmen die gesetzten Filter. Jeder Export wird im
  Audit-Protokoll vermerkt.

## Abrechnung

**Auswertungen** → **Finanzen & Audit** → **Abrechnung** öffnet die
**Abrechnungs-Auswertung**. Sie öffnet sich für Administratoren und Rollen
mit dem Recht **Alle Zeiteinträge sehen**; ohne dieses Recht wird der Zugriff
verweigert.

- Kacheln:
  - **Ausgestellt + Bezahlt (Σ Brutto)**: Bruttosumme der Rechnungen im
    Status **Gestellt**, **Teilweise bezahlt** oder **Bezahlt** mit
    Rechnungsdatum im Zeitraum (ohne Rechnungsdatum zählt das Anlagedatum).
  - **Offene Forderungen**: noch offener Betrag aller Rechnungen im Status
    **Gestellt** oder **Teilweise bezahlt**, unabhängig vom Zeitraum.
    Eingegangene Zahlungen und offene Sicherheitseinbehalte sind abgezogen;
    Pro-forma-Rechnungen, Gutschriften und Stornobelege zählen nicht. Die
    Kachel färbt sich rot, sobald
    eine davon mehr als 30 Tage überfällig ist; der Hinweis nennt ihre Zahl.
  - **Unbillte Zeit**: abrechenbare Zeiteinträge des Zeitraums, die noch
    über keinen Abrechnungsweg verbraucht wurden, mit Zahl der Einträge und
    dem voraussichtlichen Erlös aus den gespeicherten Beträgen.
- Diagramme: abrechenbare und nicht abrechenbare Stunden im Verlauf sowie
  **Umsatz je Kunde (Top 15)** aus lokalen Rechnungen und den aus dem
  Buchhaltungsprogramm gespiegelten Belegen. Ein Klick auf einen Kunden
  öffnet **Kunden & Projekte** für diesen Kunden.
- **Rechnungen nach Status**: **Anzahl**, **Netto** und **Brutto** je Status.
- **Aging – offene Posten**: die offenen Rechnungen nach Tagen über der
  Fälligkeit (ohne Fälligkeit ab Rechnungsdatum) in den Stufen **Aktuell**,
  1–7, 8–14, 15–30 und mehr als 30 Tage, jeweils mit dem offenen Betrag, und
  **Offen gesamt**.
- **Top-Kunden (ausgestellt + bezahlt im Zeitraum)**: **Kunde**,
  **Rechnungen** und **Brutto**; stammen Beträge aus dem
  Buchhaltungsprogramm, zeigt eine weitere Spalte **davon
  Buchhaltungsprogramm**. Teilweise bezahlte Rechnungen zählen mit.
- **Eingangs-E-Rechnungen (im Zeitraum)**: Eingänge je Status mit Anzahl und
  Bruttobetrag sowie die Zahl der an die Buchhaltung übergebenen.
- **Eingangs-Validierung & Mahnstufen**: **Validierung geprüft**,
  **Validierung bestanden**, **Validierung fehlgeschlagen** und die offenen
  Rechnungen je Mahnstufe 1 bis 3.
- **Angebote & Belegkette (im Zeitraum)**: Angebote je Status,
  **Annahmequote**, **Median Erstellung → Entscheidung** in Tagen, **Angebot
  → Rechnung**, **Pro-forma → Rechnung**, **Stornos / Gutschriften** und
  **Korrekturquote**.

Filter: **Kunde**, **Projekt**, **Mitarbeiter** und **Ausgeblendete Kunden
einbeziehen**. Kunde und Projekt wirken auf Rechnungen, Angebote und Zeiten,
der Mitarbeiter nur auf die Zeiten. Eingangsrechnungen und Mahnstufen
gelten immer für die ganze Organisation. Bei gewähltem Projekt entfallen die
Beträge aus dem Buchhaltungsprogramm, weil diese Belege kein Projekt kennen.
Export als PDF, CSV und Excel.

## Spesen

**Auswertungen** → **Finanzen & Audit** → **Spesen** öffnet den
**Spesen-Report**: Spesen je Mitarbeiter und Kategorie über den Zeitraum,
gerechnet mit Bruttobeträgen nach dem Belegdatum.

- Diagramme: Spesen je Monat (bzw. Woche oder Tag) nach Kategorie, die vier
  größten Kategorien einzeln und der Rest zusammengefasst, sowie
  **Top-Verursacher (Top 15)**.
- Kacheln: **Summe (Brutto)**, **Mitarbeiter**, **Kategorien** und
  **Monate**.
- Tabelle mit einer Zeile je **Mitarbeiter** und **Kategorie**, einer Spalte
  je Monat und der **Summe**, darunter **Top-Kategorien**.

Filter: **Bereich** (**Nur eigene** oder **Gesamte Organisation**, nur für
Administratoren), **Mitarbeiter**, **Team**, **Projekt** und **Status**. Ohne
Administratorrechte sehen Sie nur Ihre eigenen Spesen. Die Seite bietet
keinen Export.

## Externe Auszahlungen

**Auswertungen** → **Finanzen & Audit** → **Externe Auszahlungen** berechnet
die Beträge, die an externe Mitarbeiter im Zeitraum zu zahlen sind. Der
Menüpunkt erscheint mit dem Recht **Personal-/Lohndaten verwalten**.

Berücksichtigt werden Mitarbeiter, deren **Vergütungsmodell** beim
Mitarbeiter auf **Pauschal** oder **Nach Zeitaufwand** steht:

- **Pauschal** mit Intervall **Monatlich**: **Pauschalbetrag (€)** mal Zahl
  der Monate im Zeitraum.
- **Pauschal** mit Intervall **Pro Einsatz**: Pauschalbetrag mal Zahl der
  Tage mit Zeiteinträgen.
- **Pauschal** mit Intervall **Einmalig**: der Pauschalbetrag einmal.
- **Nach Zeitaufwand**: erfasste Zeit mal **Stundensatz (Vergütung, €)**.

Die Tabelle zeigt **Mitarbeiter**, **Modell**, **Berechnungsbasis** und
**Betrag** mit Gesamtsumme. Diagramme zeigen die Auszahlungen im Verlauf und
**Auszahlungen je Externem (Top 15)**. Im Verlauf steht eine Monatspauschale
einmal je Monat im Abschnitt mit dessen erstem Tag im Zeitraum; die Summe des
Verlaufs entspricht damit der Tabelle. Alle Beträge sind brutto, ohne Steuer
und Sozialversicherung. Filter: **Mitarbeiter**. Die Seite bietet keinen
Export.

## Zuschlags-Prognose

**Auswertungen** → **Team** → **Zuschlags-Prognose** schätzt die
Zuschlagsminuten je Monat und Lohnart auf Grundlage der geplanten Dienste im
**Schichtplan**. Der Menüpunkt erscheint für Administratoren und mit dem
Recht **Auswertungen einsehen**.

- **Monate**: 3, 6 oder 12 Monate ab dem laufenden Monat.
- **Mitarbeiter**: alle aktiven Mitarbeiter oder eine Person.
- Tabelle: **Lohnart**, **Regel**, eine Spalte je Monat und **Summe**, mit
  einer Gesamtzeile.

Gerechnet wird mit den aktiven **Zuschlagsregeln**; abgesagte Dienste zählen
nicht. Es ist eine reine Vorschau ohne Standortbezug: Regeln, die vom
Standort abhängen, greifen erst beim Stempeln. Abgerechnet wird
ausschließlich über den Zeitexport. Export als CSV und Excel.

## Umsatz je Produkt

**Auswertungen** → **Finanzen & Audit** → **Umsatz je Produkt** zeigt Menge,
Nettoumsatz und Anteil je Artikel. Der Menüpunkt erscheint für
Administratoren und mit dem Recht **Alle Zeiteinträge sehen**.

- Datenbasis: Positionen lokaler Rechnungen, Abschlags- und
  Schlussrechnungen mit Rechnungsdatum im Zeitraum im Status **Gestellt**,
  **Teilweise bezahlt** oder **Bezahlt**; Gutschriften und Stornobelege
  mindern mit negativer Menge. Dazu kommen Rechnungen und Gutschriften, die
  aus dem Buchhaltungsprogramm gespiegelt werden. Aus einer lokalen Rechnung
  übergebene Belege zählen nur einmal, Entwürfe und stornierte Belege gar
  nicht.
- Kacheln: **Nettoumsatz gesamt**, **davon aus dem Buchhaltungsprogramm**,
  **Artikel mit Umsatz** und **Anteil ohne Artikelbezug** (Positionen ohne
  gewählten Artikel).
- Diagramm der umsatzstärksten Artikel; wie viele es zeigt, legen Sie mit
  **Top-N im Diagramm** fest (3 bis 50, Vorgabe 10). Ein Klick öffnet den
  Artikel.
- **Umsatz nach Kategorie**: **Kategorie**, **Artikel**, **Nettoumsatz** und
  **Anteil**.
- Tabelle: **Artikelnummer**, **Artikel**, **Menge**, **Einheit**,
  **Nettoumsatz**, **Anteil**, **Belege** und **Quelle** (**lokal** oder der
  Name der angebundenen Buchhaltung). Positionen ohne Artikel stehen
  gesammelt unter **ohne Artikelbezug**.

Export als PDF, CSV und Excel.
