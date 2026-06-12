# DATEV- und Finanzschnittstelle für Import und Export

## Status

In Progress — erstes Inkrement umgesetzt (2026-06-10): Fakturierungsweg-
Steuerung (billing_mode je Org/Kunde, Sperre der lokalen Rechnungserstellung
bei externer Hoheit), Übergabenachweise (billing_transfers mit getrennten
Kanälen Zeit/Material, Payload-Hash, Hash-Ketten-Events, audit:verify),
Zeitkorrektur-Guard, Lexoffice-Positionsübergabe als Rechnungsentwurf über
die bestehende API, Datei-Übergabepaket (CSV, ehrlich ohne DATEV-
Formatanspruch) als Platzhalter für die Desktop-API, module.finance
(Enterprise). Zweites Inkrement (2026-06-12): E-Rechnung-MVP umgesetzt —
XRechnung-konformes UBL-2.1-XML (CIUS XRechnung 3.0) für lokale
Ausgangsrechnungen im Pfad „WorkDiary führt" (`XRechnungGenerator` mit
Pflichtfeld-Preflight: Verkäuferstammdaten je Org in
`settings['einvoice']`, Leitweg-ID/BT-10 je Kunde als
`customers.buyer_reference`, Steuerkategorien S/Z/E inkl. § 19 UStG,
SEPA-Zahlweg 58, Download-Route `invoices.einvoice` mit Hoheits-Sperre via
BillingModeResolver). Bewusst offen am E-Rechnungs-MVP: ZUGFeRD
(PDF/A-3-Einbettung), Schematron-/KoSIT-Validierung (benötigt Java; der
Preflight ersetzt keine vollständige EN-16931-Regelprüfung), Peppol-Versand,
Empfang eingehender E-Rechnungen. Weiterhin offen: DATEV-Desktop-API-Adapter
(Phase 0/1), Buchungsstapel, Zahlungsabgleich, Storno-/Differenzübergaben.

## Ziel

WorkDiary soll Finanzdaten in verbreiteten Formaten importieren, normalisieren,
prüfen und wieder exportieren können. Freigegebene Zeit-, Zuschlags- und
Abrechnungsdaten sollen als nachvollziehbare DATEV- und Finanzexporte
bereitstehen und später optional direkt an die DATEV Desktop API übertragen
werden können.

Das Feature ersetzt den bestehenden `DatevLodasProfile`-Export nicht sofort.
Der bisherige, ausdrücklich nur LODAS-nahe CSV-Export bleibt als einfacher
Kompatibilitätsmodus erhalten, bis ein fachlich geprüftes Zielformat
implementiert und abgenommen ist.

## Warum

Der aktuelle Export enthält nur
`Personalnummer;Datum;Lohnart;Stunden`. Das ist für einfache Importabläufe
nützlich, aber kein vollständiges oder zertifiziertes DATEV-Format.

Eine belastbare Schnittstelle muss:

- eingehende Finanzdateien sicher erkennen, vorprüfen und normalisieren,
- Importe vor der fachlichen Übernahme als Vorschau darstellen,
- freigegebene Quelldaten reproduzierbar in ein konkretes Zielformat abbilden,
- Mandanten-, Berater-, Wirtschafts- und Abrechnungsdaten validieren,
- Formatversion und Mapping-Konfiguration dokumentieren,
- Import, Export, Download und Übertragung revisionsnah protokollieren,
- technische Formatfehler vor der Übernahme oder Übergabe sichtbar machen,
- Formatkonvertierungen ohne unnötigen Verlust fachlicher Informationen
  ermöglichen.

## Produktempfehlung für WorkDiary

Die Finanzschnittstelle soll kein eigenständiger Universal-Konverter und keine
Finanzbuchhaltung werden. Für WorkDiary entsteht der höchste Nutzen aus fünf
gerichteten Abläufen:

1. **DATEV-Faktura:** Abrechenbare Zeiten und verwendete Produkte/Materialien
   werden getrennt einem DATEV-Auftrag übergeben, damit die Rechnung in DATEV
   erstellt werden kann.
2. **Buchhaltungsübergabe:** Ausgangsrechnungen, Gutschriften und freigegebene
   Spesen werden als prüfbarer DATEV-Buchungsstapel an Steuerberatung oder
   Buchhaltung übergeben.
3. **Zahlungsabgleich:** Bankumsätze werden aus CAMT.053 oder MT940 eingelesen
   und offenen Rechnungen beziehungsweise Erstattungen als Vorschläge
   zugeordnet.
4. **Lohnübergabe:** Freigegebene Zeiten und Zuschläge werden über ein
   fachlich bestätigtes DATEV-Lohnprofil ausgegeben.
5. **E-Rechnung:** Lokal erzeugte Ausgangsrechnungen werden als XRechnung
   oder ZUGFeRD (EN 16931) ausgegeben, damit der Pfad „WorkDiary führt" die
   gesetzliche B2B-E-Rechnungspflicht erfüllt (Ausstellungspflicht
   gestaffelt 2027/2028).

Quer über alle Abläufe gilt: Ist ein externes Fakturierungsprogramm im
Einsatz (DATEV Faktura oder Lexoffice), hat es die Rechnungshoheit;
WorkDiary liefert ausschließlich geprüfte Positionen (Stunden, Material) zu
und liest Status zurück (siehe „Führendes System"). WorkDiary tritt nie als
zweite Rechnungsinstanz neben ein führendes Programm.

Weitere Finanzformate sind sinnvoll, wenn sie einen dieser Abläufe ermöglichen
oder die Migration zu WorkDiary erleichtern. Eine freie Konvertierung von
beliebigen Dateien ohne Bezug zu WorkDiary-Objekten ist keine priorisierte
Produktfunktion.

## Priorisierte Anwendungsfälle

### Priorität 1: Zeiten und Produkte an DATEV Faktura/Auftragswesen

Dieser Ablauf beantwortet einen konkreten Kundenbedarf: Zeiten und verwendete
Produkte werden in WorkDiary erfasst und geprüft, die eigentliche Rechnung wird
jedoch in DATEV erstellt. Beide Quellen bleiben bis zur erfolgreichen Übergabe
getrennt steuerbar.

WorkDiary besitzt dafür bereits wesentliche Ausgangsdaten:

- abrechenbare `TimeEntry`-Datensätze mit Datum, Dauer und Beschreibung,
- aufgelöste Stundensätze als historischer Snapshot,
- Kunde, Projekt, Tätigkeit und gegebenenfalls Endkunde,
- Rundungs-/Taktungsregeln über die bestehende Rechnungsaggregation,
- verwendete Produkte/Materialien mit Menge, Einheit, Preis und Steuer,
- verknüpfbare Auslagen und Reisekosten,
- Monatsfreigaben, signierte Stundenzettel und Korrekturprozess.

#### Durch DATEV dokumentierter Schnittstellenstand

Der DATEV-Überblick
[Schnittstellen in den DATEV-Programmen](https://help-center.apps.datev.de/documents/1080789)
mit Stand 9. Juni 2026 bestätigt für die hier relevanten Produkte:

- `DATEV Mittelstand Faktura mit Rechnungswesen` kann Artikel-Stammdaten per
  ASCII importieren. Für Buchungs- und Stammdaten stehen außerdem
  DATEV-Format, DATEV Buchungsdatenservice, Rechnungsdatenservice 2.0 und
  DATEVconnect zur Verfügung.
- `DATEV Auftragswesen next` führt im offiziellen Überblick für Import und
  Export Artikeldaten per ASCII sowie Belegvorlagen als weiteren Datentyp auf.
- Eine Standard-Dateischnittstelle für Zeitbuchungen, Materialverwendungen
  oder unfertige Rechnungspositionen wird dort nicht ausgewiesen.

Das Detaildokument
[Geschäftspartner oder Artikel im CSV-Format importieren oder exportieren](https://help-center.apps.datev.de/documents/9259119)
mit Stand 28. Mai 2026 beschreibt CSV mit ASCII-, ANSI- oder
Unicode-Zeichensatz für Geschäftspartner- und Artikel-Stammdaten. Es bezieht
sich ausdrücklich auf das am PC installierte `DATEV Auftragswesen` innerhalb
der DATEV-Mittelstand-Faktura-Produkte, nicht auf die Cloud-Anwendung
`DATEV Auftragswesen next`.

Daraus folgen drei getrennte Integrationswege:

1. **Artikelstammdaten:** Produkte aus WorkDiary als versionierte CSV-Datei
   bereitstellen beziehungsweise DATEV-Artikeldaten einlesen.
2. **Abrechenbare Positionen:** Zeiten und konkrete Materialverwendungen nur
   über eine fachlich und technisch bestätigte Order-Management-/DATEVconnect-
   Funktion übertragen.
3. **Fertige Rechnungen und Buchungen:** Rechnungsdatenservice,
   Buchungsdatenservice oder DATEV-Buchungsstapel verwenden, wenn WorkDiary
   selbst die Rechnung erstellt.

Artikelstammdaten-Import ist damit nicht gleichbedeutend mit der Übergabe einer
konkreten Materialverwendung an einen Auftrag oder eine Rechnung.

Das DATEV SDK stellt im Bereich Order Management unter anderem Aufträge,
Teilaufträge, Kostenpositionen, Gebührensätze, Abrechnungsstände und Rechnungen
bereit. `ExpensePostingsEndpoint` unterstützt das schreibende Anlegen einer
auftragsbezogenen Leistungs-/Auslagenbuchung. Die derzeitige
`InvoicesEndpoint`-Abbildung ist dagegen lesend. Der erste Integrationsweg
lautet daher:

1. DATEV-Auftrag und zulässige Kostenpositionen lesen.
2. Freigegebene WorkDiary-Zeiten als eigene Leistungsübergabe an den Auftrag
   übertragen.
3. Verwendete Produkte/Materialien als davon getrennte Produktübergabe an den
   Auftrag übertragen.
4. Rechnung und Abrechnungsstatus weiterhin in DATEV erzeugen.
5. DATEV-Rechnungs- und Auftragsstatus nach WorkDiary zurücklesen.

Vor einer Umsetzung ist mit einer realen DATEV-Installation zu bestätigen,
welches DATEV-Produkt und welcher konkrete Faktura-Workflow durch die Desktop
API abgedeckt werden. Die Bezeichnung „DATEV Faktura“ wird im Feature als
fachlicher Kundenbegriff verwendet; die technische Zuordnung erfolgt gegen die
tatsächlich verfügbare Order-Management-API.

#### Erforderliche Zuordnungen

| WorkDiary              | DATEV Order Management                               |
| ---------------------- | ---------------------------------------------------- |
| Organisation           | DATEV-Organisation/Niederlassung                     |
| Kunde                  | DATEV-Mandant                                        |
| Projekt/Auftrag        | Auftrag und optional Teilauftrag                     |
| Benutzer               | DATEV-Mitarbeiter-GUID                               |
| Tätigkeit/Leistungsart | Kosten- und gegebenenfalls Gebührenposition          |
| Arbeitsdatum           | `work_date`                                          |
| Dauer                  | DATEV-Zeiteinheiten                                  |
| Beschreibung           | Kommentar/Buchungstext                               |
| abrechenbar            | `isbillable`                                         |
| Material/Produkt       | eigene Kostenposition, Menge, Einheit und Betrag     |
| Auslage/Reise          | Kostenbetrag oder Einheiten, sofern fachlich passend |

Die Einheit für `time_units`, Rundung, Tagesaggregation und Gebührensatz muss
aus DATEV-Konfiguration und Auftrag abgeleitet werden. WorkDiary darf nicht
ungeprüft annehmen, dass eine Einheit einer Minute oder einem
Dezimalstundenwert entspricht.

#### Führendes System

**Grundprinzip:** Sobald ein externes Fakturierungsprogramm im Einsatz ist
(DATEV Faktura/Auftragswesen oder Lexoffice), hat dieses Programm die Hoheit
über die Rechnungen. WorkDiary greift dann ausschließlich unterstützend ein:
Es liefert geprüfte, abrechenbare Stunden und Materialien als Positionen zu
und liest den Abrechnungsstatus zurück. In diesem Modus erzeugt WorkDiary
keine eigenen Rechnungen, keine Rechnungsnummern und keine konkurrierenden
Belege. Die lokale Rechnungserstellung ist nur der Weg für Organisationen
ohne externes Fakturierungsprogramm.

Pro Organisation oder Kunde wird genau ein Fakturierungsweg gewählt:

- **Externes System führt (Standard, sobald vorhanden):**
  - *DATEV führt:* WorkDiary überträgt Leistungs- und Produktbuchungen in
    getrennten Übergabeläufen; DATEV vergibt Rechnungsnummer, erstellt und
    finalisiert die Rechnung.
  - *Lexoffice führt:* WorkDiary übergibt abrechenbare Zeiten und
    Materialien über die bestehende Lexoffice-API als Positionen
    (z. B. Rechnungsentwurf); Lexoffice erstellt und finalisiert die
    Rechnung. Dieser Kanal folgt denselben Regeln wie die DATEV-Übergabe
    (Freigabe, getrennte Kanäle, Übergabenachweis, Statusrücklauf).
- **WorkDiary führt (nur ohne externes Fakturierungsprogramm):** WorkDiary
  erzeugt eine lokale `Invoice` und übergibt sie später als Beleg/Buchung an
  die Buchhaltung.

Dieselben Zeiten oder Materialverwendungen dürfen nicht gleichzeitig lokal
fakturiert und an ein führendes externes System übergeben werden. Die bestehenden
Markierungen `TimeEntry.exported` und `MaterialUsage.billed` reichen langfristig
nicht aus, weil sie verschiedene Ziele und Revisionen nicht unterscheiden.
Benötigt wird ein zielbezogener Übergabenachweis je Zeiteintrag,
Materialverwendung oder aggregierter Übergabeposition.

#### Getrennte Übergabekanäle

Zeit und Produkte/Materialien werden fachlich als zwei unabhängige Kanäle
behandelt:

| Kanal            | Quellen                                      | Typische DATEV-Abbildung                                    |
| ---------------- | -------------------------------------------- | ----------------------------------------------------------- |
| Leistungen/Zeit  | `TimeEntry`, Taktung, Tätigkeit, Mitarbeiter | Zeiteinheiten, Mitarbeiter, Leistungs-/Gebührenposition     |
| Produktstamm     | `Material` oder Produktkatalog               | Artikel-Stammdaten per CSV oder API                         |
| Produktverbrauch | `MaterialUsage`, Menge, Einheit, Preis       | Auftragsbezogene Kostenposition, Einheiten und Kostenbetrag |

Für jeden Kanal gelten eigene:

- Auswahl und Vorschau,
- DATEV-Positions-Mappings,
- Aggregationsregeln,
- Übergabeläufe und Quellnachweise,
- Fehler-, Wiederholungs- und Korrekturbehandlung.

Eine Organisation kann damit zunächst nur Zeiten übergeben und Materialien
später separat nachreichen. DATEV kann beide Kanäle anschließend demselben
Auftrag und gegebenenfalls derselben Rechnung zuordnen. WorkDiary darf sie bei
der Übertragung dennoch nicht zu einer undifferenzierten Gesamtposition
zusammenfassen.

Die Trennung entspricht dem bestehenden WorkDiary-Abrechnungsmodell:
Leistungsrechnungen werden aus Zeitblöcken erzeugt, Materialrechnungen aus
`MaterialUsage`. Die DATEV-Integration übernimmt dieses Prinzip unabhängig
davon, ob DATEV daraus getrennte oder gemeinsame Rechnungen erzeugt.

#### Freigabe und Aggregation

Übertragen werden nur:

- abrechenbare Zeiten,
- fachlich freigegebene beziehungsweise gesperrte Zeiträume,
- noch nicht lokal fakturierte oder an DATEV übertragene Quellen,
- Datensätze mit vollständigem Kunden-, Auftrags-, Mitarbeiter- und
  Positions-Mapping.

Für Produkte/Materialien gelten zusätzlich:

- Verwendung ist einem Auftrag/Projekt und Leistungszeitraum zugeordnet,
- Menge und Einheit sind positiv und DATEV-kompatibel,
- Preis- und Steuerbehandlung sind fachlich geklärt,
- Material wurde weder lokal fakturiert noch bereits an DATEV übergeben,
- Produkt-/Materialposition ist auf eine zulässige DATEV-Kostenposition
  abgebildet.

Die Vorschau zeigt vor der Übertragung sowohl Einzelquellen als auch die
tatsächlich entstehenden DATEV-Buchungen. Konfigurierbare Aggregationen:

- je Zeiteintrag,
- je Tag, Mitarbeiter und Tätigkeit,
- je Abrechnungszeitraum und Leistungsposition.

Materialien werden unabhängig davon aggregiert:

- je einzelner Materialverwendung,
- je Tag und Produkt,
- je Abrechnungszeitraum, Produkt und Einheit.

Zeit und Material dürfen in der Vorschau gemeinsam kontrolliert, aber nur als
getrennte Übergabepakete bestätigt werden.

Nach erfolgreicher Übertragung werden Quellreferenzen, DATEV-Auftrag,
DATEV-Posting-ID, Payload-Hash und Zeitpunkt gespeichert. Korrekturen erfolgen
nicht durch stilles Überschreiben, sondern als nachvollziehbare Storno-,
Korrektur- oder Differenzbuchung entsprechend der von DATEV unterstützten
Semantik.

Dabei ist der bestehende WorkDiary-Korrekturprozess der Auslöser, nicht nur
die DATEV-Seite: `TimeCorrectionRequest` kennt Übergabenachweise heute nicht.
Eine genehmigte Korrektur an einer bereits übertragenen Zeit muss entweder
durch den Übergabenachweis blockiert werden oder automatisch eine
Differenz-/Stornoübergabe anstoßen. Gleiches gilt für nachträgliche
Änderungen an bereits übergebenen Materialverwendungen.

### Priorität 2: DATEV-Buchungsübergabe

Dieser Ablauf verwendet bereits vorhandene WorkDiary-Daten:

| WorkDiary-Quelle                   | Buchhalterische Bedeutung        | Voraussetzung                             |
| ---------------------------------- | -------------------------------- | ----------------------------------------- |
| gestellte `Invoice`                | Debitorische Ausgangsrechnung    | Erlös-, Debitoren- und Steuerkonten       |
| `Invoice` vom Typ Gutschrift       | Rechnungskorrektur/Gutschrift    | Referenz zum Ursprungsbeleg               |
| freigegebene `Expense`             | Aufwand oder Auslagenersatz      | Aufwands-, Vorsteuer- und Gegenkonto      |
| abrechenbare `Expense` an Rechnung | Bestandteil der Ausgangsrechnung | keine doppelte Übergabe als eigener Erlös |
| Reisekosten und Verpflegung        | Aufwand/Erstattung               | fachliche Kategorien und Konten-Mapping   |
| Kunden und Lieferanten             | Debitoren-/Kreditorenstamm       | eindeutige externe Nummern                |

Vor dem Export fehlen der Anwendung insbesondere:

- organisationsbezogene Sachkonten und Steuerschlüssel,
- Debitoren-/Kreditorennummern oder eine dokumentierte Vergaberegel,
- Belegfeld-, Buchungstext- und Kostenstellenregeln,
- Festlegung, welche Status als buchungsreif gelten,
- Schutz vor doppelter Übergabe desselben Vorgangs.

Der erste Buchhaltungsexport sollte deshalb nur explizit ausgewählte,
buchungsreife Vorgänge eines abgeschlossenen Zeitraums enthalten. Bei
Rechnungen sind das grundsätzlich gestellte oder bereits bezahlte Vorgänge,
bei Spesen fachlich freigegebene Vorgänge. Entwürfe, stornierte Vorgänge,
abgelehnte Spesen und noch nicht freigegebene Spesen sind ausgeschlossen.
Ob eine bereits an eine Kundenrechnung angehängte Spese zusätzlich als Aufwand
übergeben wird, muss über eine eindeutige Buchungsregel entschieden werden.

#### Belegübergabe

Ein Buchungsstapel ohne Belege ist für die Kanzlei nur die halbe Übergabe.
Spesen und Rechnungen besitzen in WorkDiary bereits Anhänge (Belegfotos,
PDF-Rechnungen). Das Konzept muss deshalb in Phase 0 entscheiden:

- ob Belegdateien Teil des Exportpakets werden (Dateiablage je Buchungssatz
  mit Referenz im Belegfeld),
- ob perspektivisch eine Beleglink-Übergabe unterstützt wird (BEDI-GUID,
  DATEV Unternehmen online / Belegbilderservice),
- oder ob die Belegübergabe ausdrücklich außerhalb des Funktionsumfangs
  bleibt und die Kanzlei Belege weiterhin separat erhält.

Ein stillschweigendes Weglassen ist keine Option — der Weg muss dokumentiert
und in der Prüfansicht sichtbar sein.

### Priorität 3: Bankimport und Zahlungsabgleich

CAMT.053 ist das bevorzugte erste Importformat; MT940 dient als verbreiteter
Fallback. Der Import erzeugt zunächst Bankumsätze in einem Prüfbereich und
ändert keine Rechnungs- oder Spesenstatus automatisch.

Zuordnungsvorschläge berücksichtigen:

- Rechnungsnummer, Kundenreferenz und End-to-End-ID,
- Betrag, Währung und Soll/Haben-Kennzeichen,
- IBAN/BIC und Name der Gegenpartei,
- Buchungs- und Wertstellungsdatum,
- bereits zugeordnete Teilzahlungen und Gutschriften.

Ein Nutzer bestätigt anschließend:

- Zahlung einer oder mehrerer Rechnungen,
- Teilzahlung oder Überzahlung,
- Erstattung einer freigegebenen Spese,
- nicht zuordenbaren Umsatz,
- bereits bekannten oder doppelten Umsatz.

Der Abgleich muss darüber hinaus die häufigsten Praxisfälle beherrschen:

- **Skonto:** Unterzahlung innerhalb einer konfigurierbaren Skonto-/
  Toleranzregel (je Kunde oder Zahlungsbedingung) wird als vollständige
  Zahlung mit Skontoabzug vorgeschlagen; der Abzug braucht eine eigene
  buchhalterische Behandlung (Erlösschmälerung) statt einer offen
  bleibenden Rest-Teilzahlung.
- **Cent-Toleranzen:** Rundungsdifferenzen unterhalb eines konfigurierbaren
  Schwellwerts dürfen einen Vorschlag nicht verhindern, bleiben aber
  sichtbar ausgewiesen.
- **Sammelbuchungen:** CAMT-Entries mit mehreren Transaktionsdetails
  (Batch-Buchungen) werden vor der Vorschlagsbildung in Einzelumsätze
  aufgelöst; sonst matcht ein Sammler nie gegen Einzelrechnungen.
- **Saldenkette:** Eröffnungs- und Schlusssaldo aufeinanderfolgender
  CAMT.053-Auszüge müssen aneinander anschließen; Lücken (fehlende
  Auszüge) werden als Warnung ausgewiesen.
- **Fremdwährung:** `Invoice` besitzt eine Währungsspalte. Nicht-EUR-Umsätze
  werden im ersten Inkrement nur erkannt und als „manuell zu klären"
  markiert; Kursdifferenzbehandlung ist ein eigenes Inkrement.

Erst die Bestätigung darf `Invoice.status`, `Invoice.paid_on`,
`Expense.reimbursed_at` oder `Expense.reimbursement_reference` verändern. Der
Bankumsatz und die Zuordnungsentscheidung bleiben als Nachweis erhalten.

#### Bewusste Entscheidung: dateibasiert vor Bankanbindung

Der Einstieg erfolgt bewusst dateibasiert (CAMT-/MT940-Upload). Ein direkter
Bankzugang über EBICS, FinTS oder Open-Banking-APIs entspricht der
Markterwartung („Bank anbinden") und bleibt als spätere Ausbaustufe explizit
vorgesehen — er ändert nur den Transportweg, nicht den Prüf- und
Bestätigungsprozess. Die Entscheidung ist hier dokumentiert, damit sie nicht
als Lücke fehlinterpretiert wird.

### Priorität 4: Lohnexport

Der vorhandene Zeitexport ist fachlich bereits besser vorbereitet als die
Buchhaltungsübergabe: Monatsfreigabe, Snapshot, Hash, Lohnart und Kostenstelle
sind vorhanden. Offen ist vor allem die verbindliche DATEV-Lohnformatspezifikation.

Die Lohnschnittstelle bleibt daher ein eigener Ablauf mit eigenen Profilen,
Prüfregeln und Berechtigungen. Bank- und Buchhaltungsdaten dürfen nicht in
`TimeExport` oder `TimeExportLine` untergebracht werden.

### Priorität 5: SEPA-Zahlungsdateien

PAIN.001 kann später für freigegebene Lieferantenzahlungen,
Spesenerstattungen oder Reisekosten genutzt werden. Das ist erst sinnvoll, wenn
WorkDiary einen belastbaren Zahlungsfreigabeprozess mit Fälligkeit,
Zahlungsempfänger, Bankverbindung, Vier-Augen-Freigabe und Ausführungsstatus
besitzt.

PAIN.008 ist für WorkDiary derzeit nachrangig, weil noch kein fachlicher
Lastschrift- und Mandatsprozess vorhanden ist.

### Priorität 6: Migration und Spezialformate

OFX, QIF/QXF, Addison, Sage und dateibasierte Lexoffice-Formate dienen
hauptsächlich:

- der Migration bestehender Bank- und Buchhaltungsdaten,
- Installationen ohne CAMT-/MT940-Zugang,
- kundenspezifischen Kanzlei- und Buchhaltungsabläufen.

Die bestehende Lexoffice-API-Integration bleibt der bevorzugte Weg für
Lexoffice. Ein zusätzlicher Dateiaustausch darf keine parallele,
widersprüchliche Synchronisation erzeugen.

## Fachlicher Umfang

### 1. Lohn und Zeit

- Bestehende `TimeExport`- und `TimeExportLine`-Daten als Quelle verwenden.
- Normalstunden, Zuschläge, Lohnarten, Kostenstellen und Personalnummern
  organisationsbezogen zuordnen.
- Nur freigegebene und unveränderte Monatsdaten exportieren.
- Bestehenden LODAS-nahen CSV-Modus klar als Kompatibilitätsformat kennzeichnen.
- Echtes DATEV-Lohnformat erst nach Prüfung einer belastbaren Formatspezifikation
  als eigenes Exportprofil anbieten.

### 2. Rechnungswesen

- DATEV-Buchungsstapel ab Formatversion V700 erzeugen.
- Festschreibekennzeichen (GoBD) je Stapel bewusst setzen; die Entscheidung
  „festgeschrieben übergeben oder nicht" ist Teil der Phase-0-Festlegungen
  und wird im Exportnachweis dokumentiert.
- Perspektivisch Debitoren-/Kreditorenstammdaten,
  Sachkontobeschriftungen, Zahlungsbedingungen und wiederkehrende Buchungen
  unterstützen.
- SKR03/SKR04 und Kontenlänge organisationsbezogen konfigurieren.
- Buchungen über ein neutrales WorkDiary-Transfermodell vom DATEV-Format
  entkoppeln.

### 3. Faktura- und Leistungsübergabe

- Produkt-/Artikelstammdaten und konkrete Materialverwendungen als
  unterschiedliche Datenarten behandeln.
- Artikelstammdaten für das installierte DATEV Auftragswesen über ein
  versioniertes, konfigurierbares CSV-Profil austauschen.
- DATEV-Aufträge, Teilaufträge, Kostenpositionen, Mitarbeiter und
  Abrechnungsstände organisationsbezogen synchronisieren.
- WorkDiary-Projekte explizit einem DATEV-Auftrag/Teilauftrag zuordnen.
- Freigegebene Zeiten als auftragsbezogene Leistungsbuchungen vorbereiten.
- Verwendete Produkte/Materialien getrennt als auftragsbezogene
  Produkt-/Kostenbuchungen vorbereiten.
- Auslagen und Reiseleistungen als weiteren, optionalen Kanal behandeln.
- Taktung und Aggregation aus dem WorkDiary-Abrechnungsmodell reproduzierbar
  anwenden.
- Vor Übertragung Stunden, Einheiten, Beträge, Mitarbeiter und Positionen in
  einer Prüfansicht darstellen.
- Nach Übertragung DATEV-Posting-IDs und Quellreferenzen revisionsfest
  verknüpfen.
- Zeit- und Materialübergaben unabhängig abschließen, wiederholen und
  korrigieren können.
- DATEV-Rechnungen und Billing-Status lesend synchronisieren.
- Lokale und DATEV-geführte Fakturierung gegenseitig ausschließen.

### 4. Finanzformat-Import

- Kontoauszüge und Banktransaktionen aus CAMT.052, CAMT.053, CAMT.054,
  MT940/MT942, OFX und QIF importieren.
- Zahlungs- und Lastschriftdateien aus PAIN-Formaten zunächst lesen und
  validieren; eine fachliche Verbuchung bleibt ein eigenes Inkrement.
- DATEV-Buchungsstapel und unterstützte Stammdatenformate einlesen.
- Formate von Lexoffice, Addison und Sage einbeziehen, soweit die Bibliothek
  dafür stabile Parser oder verlustarme Konverter bereitstellt.
- Format und Version automatisch erkennen, aber vor der Übernahme sichtbar
  bestätigen lassen.
- Importierte Daten in ein neutrales WorkDiary-Transfermodell überführen.
- Dubletten anhand externer IDs, Kontoverbindung, Betrag, Buchungsdatum,
  Referenzen und Datei-Hash erkennen.
- Originaldatei, Importnachweis und normalisierte Datensätze getrennt
  aufbewahren.
- Fehlerhafte Datensätze nicht stillschweigend verwerfen, sondern mit
  Zeilen-/Feldbezug in einem Prüfbericht ausweisen.

Der Import begründet noch keine automatische Buchung. Die erste Ausbaustufe
liefert eine geprüfte Vorschau und eine explizite Zuordnung zu fachlichen
WorkDiary-Objekten.

### 5. Finanzformat-Export und Konvertierung

- Normalisierte Banktransaktionen als CAMT.053/054, MT940, OFX oder QIF
  exportieren, soweit das Zielformat die erforderlichen Informationen abbildet.
- Zahlungsdaten perspektivisch als PAIN.001 und Lastschriften als PAIN.008
  erzeugen.
- DATEV-Buchungs- und Stammdatenformate versioniert bereitstellen.
- Formatkonvertierungen wie CAMT zu MT940 oder Banktransaktion zu
  DATEV/Lexoffice/Addison/Sage nur nach einer sichtbaren
  Verlust-/Kompatibilitätsprüfung anbieten.
- Exportprofile pro Organisation speichern, ohne fachliche Defaults global
  fest zu verdrahten.

### 6. Prüfung und Nachweis

- Vor Import und Export eine fachliche Prüfansicht mit Summen, Warnungen,
  Dubletten und Mapping-Lücken anzeigen.
- Erzeugte Datei erneut einlesen und gegen Header, Formatversion und
  Pflichtfelder validieren.
- SHA-256, Zeitpunkt, ausführende Person, Quellfreigabe, Formatversion,
  Konvertierungspfad und Konfigurationsstand speichern.
- Optional einen PDF-Begleitbericht mit Summen, Warnungen und Prüfergebnis
  erzeugen.

### 7. Übertragung

- Dateibasierte Abläufe: Datei erzeugen und manuell herunterladen/als
  übermittelt markieren.
- Faktura-Ablauf: optional aktivierbarer Adapter für die DATEV Desktop API.
- Vor einer Übertragung einen Diagnose-/Echo-Test durchführen.
- Zugangsdaten niemals im Export oder Audit-Log speichern.
- Übertragungen idempotent gestalten und technische Antworten getrennt von
  fachlichen Exportdaten protokollieren.

### 8. E-Rechnung (EN 16931)

- Lokal erzeugte Ausgangsrechnungen als XRechnung (UBL/CII) und ZUGFeRD
  ausgeben können; Pflichtangaben (Leitweg-ID, Steuerangaben, Zahlungsdaten)
  je Organisation konfigurieren.
- Gesetzlicher Rahmen: Die B2B-Empfangspflicht besteht seit 2025, die
  Ausstellungspflicht greift gestaffelt 2027/2028 — der Pfad „WorkDiary
  führt" braucht dieses Format verbindlich und unabhängig von allen
  DATEV-Prioritäten.
- Validierung gegen das EN-16931-Regelwerk (z. B. KoSIT-Validator) als Teil
  der Exportprüfung.
- Einordnung: eigenes Inkrement mit hoher Priorität — fachlich näher an der
  bestehenden Rechnungserstellung als am Buchungsstapel; die Priorisierung
  gegenüber Phase 3 wird in Phase 0 festgelegt.
- Eingehende E-Rechnungen (Empfang/Visualisierung) sind nachrangig, da
  WorkDiary keine Kreditorenbuchhaltung anstrebt.
- Gilt ausschließlich für den Pfad „WorkDiary führt": Führt DATEV oder
  Lexoffice die Fakturierung, liegt die E-Rechnungs-Ausstellung beim
  führenden Programm — WorkDiary baut dann keine parallele
  E-Rechnungs-Erzeugung.

## Vorgesehene Formatgruppen

| Formatgruppe           | Richtung                | Priorität                     | Nutzung in WorkDiary                                         |
| ---------------------- | ----------------------- | ----------------------------- | ------------------------------------------------------------ |
| DATEV Order Management | API bidirektional       | hoch                          | Zeiten und Produkte getrennt zur Fakturierung, Status zurück |
| DATEV Artikel-CSV      | Import/Export           | hoch                          | Artikelstammdaten, nicht abrechenbare Einzelpositionen       |
| DATEV V700+            | Export, später Import   | hoch                          | Buchungsstapel und Stammdaten                                |
| XRechnung/ZUGFeRD      | Export, später Import   | hoch, gesetzlich getrieben    | Ausgangsrechnungen im Pfad „WorkDiary führt"                 |
| Rechnungsdatenservice  | Export/Übertragung      | mittel                        | in WorkDiary fertiggestellte Rechnungsdaten                  |
| CAMT.053               | Import                  | hoch                          | Zahlungsabgleich                                             |
| MT940                  | Import                  | hoch                          | Zahlungsabgleich als Fallback                                |
| DATEV Lohn             | Export                  | hoch, nach Spezifikation      | Zeit, Zuschläge und Lohnarten                                |
| CAMT.052/054           | Import, später Export   | mittel                        | Tagesauszüge und Umsatzmeldungen                             |
| PAIN.001               | Export                  | mittel, nach Zahlungsfreigabe | Überweisungen und Erstattungen                               |
| OFX/QIF/QXF            | Import, optional Export | niedrig                       | Migration und Spezialfälle                                   |
| Addison/Sage           | Import/Export           | niedrig                       | kundenspezifische Kanzleiabläufe                             |
| Lexoffice-Dateiformate | Import/Export           | niedrig                       | nur ergänzend zur vorhandenen API                            |
| EBICS/FinTS-Bankzugang | API-Import              | später, nach Entscheidung     | direkter Umsatzabruf statt Datei-Upload                      |
| BMD/RZL/Abacus         | Import/Export           | später (AT/CH-Rechtsräume)    | Kanzleiformate außerhalb Deutschlands (Feature 034)          |
| PAIN.008               | Export                  | später                        | Lastschriften nach Mandatsprozess                            |

Die Matrix beschreibt die Zielrichtung, nicht den Umfang des ersten
Inkrements. Jedes Format benötigt eigene Referenzdateien, Qualitätskriterien
und eine dokumentierte Abbildung auf das Transfermodell.

## Technischer Zuschnitt

### `php-financial-formats`

Primäre Bibliothek für DATEV- und Finanzformate:

- `BookingDocumentBuilder` und weitere DATEV-V700-Builder,
- `DatevDocumentGenerator`,
- `DatevDocumentParser`,
- Parser und Builder für CAMT, PAIN, MT, OFX, QIF und QXF,
- `VersionDiscovery`, `VersionManager` und `HeaderRegistry`,
- SKR-Registries,
- Konverter zwischen DATEV-Banktransaktionen, CAMT, MT940, OFX, QIF,
  Lexoffice, Addison und Sage.

Die Laravel-Anwendung soll diese Bibliothek über einen eigenen Adapter
ansprechen. Klassen aus dem Toolkit werden nicht direkt in Controller oder
Eloquent-Modelle eingebaut.

### `php-common-toolkit`

Bereits vorhandene Basis für:

- CSV-, XML- und Dateiverarbeitung,
- Datums-, Zahlen-, Währungs- und Identifikator-Helfer,
- speichereffizientes Lesen größerer Dateien.

Die Anwendung verwendet weiterhin lokale Facades/Adapter, damit ein
Toolkit-Wechsel die Fachlogik nicht durchdringt.

### `php-pdf-toolkit`

Nicht für das DATEV-Datenformat erforderlich. Ein Einsatz ist nur vorgesehen,
wenn Begleitberichte zusammengeführt, signiert, ausgelesen oder per OCR geprüft
werden müssen.

Für einen einfachen HTML-basierten Prüfbericht genügt zunächst das bereits
vorhandene `barryvdh/laravel-dompdf`. Eine zusätzliche Abhängigkeit vom
PDF Toolkit wird erst bei einem konkreten Bedarf eingeführt.

### `datev-php-sdk`

Optionaler Transportadapter für die DATEV Desktop API:

- Verbindung und Authentifizierung,
- Diagnose,
- Auswahl fachlich passender Endpoints,
- Übertragung und technische Rückmeldungen.

SDK-Entities dürfen nicht das interne WorkDiary-Datenmodell werden. Die
Integration liegt hinter einem Interface, damit Datei-Export und Desktop API
unabhängig voneinander betrieben und getestet werden können.

## Fachliches Transfermodell

Ein einziges universelles Dokumentmodell wäre zu unscharf. Sinnvoll sind
getrennte, aber gemeinsam protokollierte Transferbereiche, die sich nicht an
die Klassen eines Toolkits binden:

| Transferbereich  | Enthält                                                        | Verknüpft mit                     |
| ---------------- | -------------------------------------------------------------- | --------------------------------- |
| Zeitübergabe     | Auftrag, Mitarbeiter, Datum, Zeit, Position und Kommentar      | Zeiteinträge                      |
| Produktübergabe  | Auftrag, Produkt, Menge, Einheit, Position und Betrag          | Materialverwendungen              |
| Auslagenübergabe | Auftrag, Art, Datum, Einheit und Betrag                        | Auslagen oder Reise               |
| Bankauszug       | Konto, Salden, Zeitraum und Bankumsätze                        | Organisation/Bankkonto            |
| Bankumsatz       | Betrag, Währung, Soll/Haben, Daten, Gegenpartei und Referenzen | Rechnung, Spese oder unzugeordnet |
| Buchungsvorgang  | Konto, Gegenkonto, Steuer, Kostenstelle, Beleg und Betrag      | Rechnung, Gutschrift oder Spese   |
| Stammdatensatz   | Debitor/Kreditor, Anschrift, Bank- und Steuermetadaten         | Kunde oder Lieferant              |
| Lohnsatz         | Personalnummer, Lohnart, Menge, Zeitraum und Kostenstelle      | `TimeExportLine`                  |
| Zahlungsauftrag  | Empfänger, IBAN/BIC, Betrag, Fälligkeit und Referenz           | freigegebene Zahlung              |

Nicht jedes Format enthält alle Felder. Fehlende, abgeleitete und beim
Zielformat verlorene Informationen müssen unterscheidbar bleiben.

Die fachlichen WorkDiary-Modelle bleiben führend. Transferdatensätze sind
Snapshots und Nachweise; sie werden nicht zur zweiten Quelle der Wahrheit für
Rechnungen, Spesen, Kunden oder Lieferanten.

## Einordnung in die bestehende Anwendung

### Wiederverwenden

- `ImportRun`-Prinzip: Datei, Hash, Preflight, Vorschau, Fehler und Bestätigung.
- `ExportRun`-Prinzip: organisationsgebundener Lauf, Dateiablage und Status.
- `TimeExport`: ausschließlich für Lohn- und Zeitdaten.
- Plugin-System: externe Transportwege wie Lexoffice und DATEV Desktop API.
- bestehende Fakturierungsaggregation und Verknüpfung zwischen
  `InvoiceItem` und `TimeEntry` als fachliche Referenz für Taktung und
  Quellnachweis.
- `PaymentSyncer`: fachliche Capability für bestätigten Zahlungsabgleich.
- `ExternalReference`: Verbindung lokaler Vorgänge mit externen IDs.
- Audit-, Storage- und Mandantenschutzmuster der vorhandenen Exporte.
- Benachrichtigungsmodul (Feature 018): „Bankimport abgeschlossen",
  „Zuordnungsvorschläge offen", „Übertragung fehlgeschlagen" als Events der
  bestehenden `NotificationEvent`-Registry statt eigener Meldewege.
- Betriebsmetriken (Feature 036): Transferläufe als Feature-Nutzungszähler.
- Diagnose-Seite und `system:health`: der geforderte DATEV-Echo-Test wird
  dort eingehängt, nicht als eigenes Werkzeug gebaut.
- Externe Beteiligte (Feature 033): Kanzlei-Zugang zum Download des
  Exportpakets statt Versand von Buchungsdaten per E-Mail.

### Nicht vermischen

- Finanzimporte werden nicht als weitere CSV-`ImportEntity` in die bestehende
  Stammdaten-Upsert-Pipeline gedrückt.
- Buchhaltungsexporte werden nicht als `TimeExport` modelliert.
- Lexoffice-API-Sync und Dateikonvertierung dürfen nicht gleichzeitig die
  führende Quelle für denselben Datensatz sein.
- Lokale Rechnungserstellung und extern geführte Fakturierung (DATEV oder
  Lexoffice) dürfen dieselben Zeit-, Auslagen-, Material- oder Reisedaten
  nicht parallel verbrauchen.
- Zeit- und Materialübergabe dürfen nicht denselben globalen Status verwenden;
  ein erfolgreicher Zeitlauf darf eine noch offene Materialübergabe nicht
  fälschlich als abgeschlossen markieren.
- Ein erkannter Bankumsatz markiert eine Rechnung nicht ohne Bestätigung als
  bezahlt.
- PDF-Belege sind Anlagen zu Vorgängen, nicht das strukturierte
  Finanztransferformat.

### Neue fachliche Konzepte

Die spätere Entwicklung benötigt voraussichtlich:

- einen Finanztransferlauf für Import, Export und Konvertierung,
- gespeicherte Bankauszüge und Bankumsätze mit Zuordnungsstatus,
- organisationsbezogene Buchhaltungskonfiguration und Konten-Mappings,
- Übergabemarkierungen pro Rechnung, Gutschrift und Spese,
- zielbezogene Leistungsübergaben mit DATEV-Posting-ID und Quellreferenzen,
- getrennte Übergabestatus für Zeit, Produkte/Material und optionale Auslagen,
- Zuordnungen von Kunden, Projekten, Benutzern und Tätigkeiten zu
  DATEV-Mandanten, Aufträgen, Mitarbeitern und Kostenpositionen,
- bestätigte Zahlungszuordnungen mit Historie,
- eine Capability-Matrix pro Format und Version.

Diese Begriffe beschreiben die benötigten Verantwortlichkeiten. Tabellen- und
Klassennamen werden erst in der technischen Konzeption festgelegt.

## Vorgeschlagene Komponenten

- `FinancialExportProfile`: Vertrag für versionierte Finanzexporte.
- `FinancialImportProfile`: Vertrag für Erkennung, Vorprüfung und Normalisierung.
- `FinancialTransferDocument`: formatneutrales Import-/Export-Dokument.
- `FinancialFormatCapability`: dokumentiert unterstützte Leser, Schreiber und
  mögliche Informationsverluste.
- `FinancialImportRun`: Importstatus, Originaldatei, Hash und Prüfergebnis.
- `DatevExportContext`: Berater, Mandant, Zeitraum, Formatversion und
  Kontenrahmen.
- `DatevMappingService`: Lohnarten, Kostenstellen, Konten und Personalnummern.
- `DatevFinancialFormatsAdapter`: Kapselt `php-financial-formats`.
- `DatevDesktopGateway`: optionaler Adapter für `datev-php-sdk`.
- `DatevOrderBillingProfile`: Regeln für Auftrag, Zeiteinheit,
  Zeitaggregation, Materialaggregation und führendes Fakturierungssystem.
- `BillingTransferReceipt`: Nachweis der an DATEV übergebenen Leistungsquellen.
- `FinancialFormatValidator`: technische und fachliche Vorabprüfung.
- `FinancialTransferReceipt`: unveränderlicher Nachweis über Import, Export,
  Konvertierung oder Übertragung.

Für den Lohnablauf werden bestehende `TimeExport`-Datensätze referenziert.
Buchhaltung, Bankumsätze und Zahlungen erhalten einen getrennten
Finanztransferbereich und erweitern `TimeExport` nicht.

## Umsetzung in Phasen

### Phase 0: Fachliche Entscheidungen und Referenzdaten

- Konkretes DATEV-Zielformat für Lohn festlegen.
- Composer-Paketnamen, Versionen und Lizenzverträglichkeit prüfen.
- DATEV-Buchungsformat, Berater-/Mandantennummern und Kontenlogik festlegen.
- Statusregeln für buchungsreife Rechnungen und Spesen definieren.
- Festschreibekennzeichen-Strategie (GoBD) festlegen.
- Skonto- und Toleranzregeln für den Zahlungsabgleich definieren.
- Belegübergabe an die Kanzlei entscheiden (Exportpaket, Beleglink oder
  außerhalb des Umfangs).
- E-Rechnung (EN 16931) gegenüber dem Buchungsstapel priorisieren.
- Bankzugang (EBICS/FinTS) als spätere Ausbaustufe bestätigen.
- Endkunden-Modell abschließen, bevor das Auftrags-Mapping auf Kunden-/
  Projektebene festgelegt wird (kein Mapping gegen die Namenskonvention
  „Endkunde im Projektnamen").
- Transferbereiche und Adaptergrenzen definieren.
- Priorisierte Formatmatrix und Regeln für Informationsverlust festlegen.
- Beispieldateien mit Steuerberatung, Lohnbüro oder Finanzbuchhaltung fachlich
  abnehmen.

**Ergebnis:** Abgenommene Beispieldateien und Mappingregeln. Ohne dieses
Ergebnis beginnt keine produktive DATEV-Implementierung.

### Phase 1: DATEV-Faktura-Pilot

- Reale DATEV-Version und Kundenworkflow gegen Order Management verifizieren.
- DATEV-Aufträge, Teilaufträge, Kostenpositionen und Mitarbeiter lesen.
- Mapping für Kunde, Projekt, Benutzer und Tätigkeit bereitstellen.
- Freigegebene Zeiten als Vorschau in DATEV-Zeiteinheiten darstellen.
- Expense Postings zunächst für einen klar begrenzten Pilotfall übertragen.
- Idempotenz, Wiederholung und Quellnachweis sicherstellen.
- Auftrags-Billing-Status und entstandene Rechnungsmetadaten zurücklesen.

**Pilotumfang:** Zeitbuchungen eines Kunden und eines DATEV-Auftrags, keine
Materialien, keine Auslagen, keine automatische Rechnungserstellung in
WorkDiary.

### Phase 2: DATEV-Faktura-Ausbau

- Teilaufträge und mehrere Leistungs-/Kostenpositionen unterstützen.
- Produkte/Materialien als eigenen Übergabekanal ergänzen.
- Getrennte Vorschau, Bestätigung und Wiederholung für Zeit und Material
  bereitstellen.
- Auslagen und Reise als dritten optionalen Kanal fachlich ergänzen.
- Zeit-, Material- und Auslagen-Aggregationsprofile konfigurierbar machen.
- Korrektur- und Stornoprozess mit DATEV fachlich abnehmen.
- DATEV-geführte und lokale Fakturierung pro Kunde/Projekt eindeutig steuern.

### Phase 3: DATEV-Buchungsstapel

- `php-financial-formats` über einen WorkDiary-Adapter anbinden.
- Gestellte Rechnungen und Gutschriften exportieren.
- Freigegebene Spesen optional in getrenntem Stapel exportieren.
- Organisationsbezogene Konten-, Steuer- und Kostenstellen-Mappings prüfen.
- Doppelte Übergabe verhindern und Exportrevisionen nachvollziehen.
- Write/Read-Roundtrip und fachlichen Summenvergleich durchführen.
- Prüfbericht und DATEV-Datei als gemeinsames Exportpaket bereitstellen.

**Noch nicht enthalten:** Stammdatenexport, Bankimport, Desktop API und
automatische Übertragung.

### Phase 4: CAMT.053-/MT940-Zahlungsabgleich

- CAMT.053 und MT940 als erste Referenzformate importieren.
- Formaterkennung, Preflight, Vorschau und Dublettenprüfung bereitstellen.
- Originaldatei, Bankauszug und normalisierte Umsätze speichern.
- Zuordnungsvorschläge für Rechnungen und Spesenerstattungen erzeugen.
- Statusänderungen nur nach Bestätigung durchführen.
- Teilzahlungen, Überzahlungen, Gutschriften und nicht zuordenbare Umsätze
  abbilden.
- Skonto-/Toleranzregeln, Sammelbuchungs-Auflösung und Saldenkettenprüfung
  umsetzen.

### Phase 5: DATEV-Stammdaten und verbesserte Übergabe

- Debitoren-/Kreditorenstammdaten exportieren.
- Sachkontobeschriftungen, Zahlungsbedingungen und wiederkehrende Buchungen
  nach tatsächlichem Bedarf ergänzen.
- Optional mehrere DATEV-Stapel pro Zeitraum und Vorgangsart unterstützen.
- Exportstatus und Rückmeldungen aus Buchhaltung/Kanzlei dokumentieren.

### Phase 6: DATEV-Lohnexport

- Bestehendes LODAS-nahes Profil als Legacy/Kompatibilität kennzeichnen.
- Fachlich geprüftes Lohnprofil implementieren.
- Zuschläge und Normalstunden gegen Referenzimporte testen.
- Lohnexport getrennt von Buchhaltungs- und Bankrechten absichern.

### Phase 7: Zahlungen und weitere Finanzformate

- PAIN.001 erst nach Einführung einer Zahlungsfreigabe implementieren.
- OFX, QIF/QXF und weitere Formate nach konkreter Kundennachfrage ergänzen.
- Informationsverluste vor Konvertierungen sichtbar machen.
- Golden Files und Roundtrip-Tests je Format etablieren.

### Phase 8: Weitere DATEV-Desktop-Automatisierung

- Den im Faktura-Pilot eingeführten `datev-php-sdk`-Adapter auf weitere
  DATEV-Bereiche ausbauen.
- Diagnose und Authentifizierung produktionsreif absichern.
- Wiederholung, Fehlerstatus und Idempotenz absichern.

Die Desktop API ist kein Ersatz für Format-, Mapping- und Freigabeprüfungen.

## Bedienkonzept

Die Finanzschnittstelle sollte als Bereich **Buchhaltung & Zahlungsabgleich**
erscheinen, nicht im allgemeinen CSV-Stammdatenimport.

Vorgesehene Einstiege:

- **DATEV Faktura:** Kunden/Projekte zuordnen, Zeiten und Produkte getrennt
  prüfen und übertragen sowie den Abrechnungsstatus verfolgen.
- **Buchhaltungsexporte:** Zeitraum und Vorgangsarten wählen, Preflight prüfen,
  Exportpaket erzeugen und Übergabe dokumentieren.
- **Bankauszüge:** Datei hochladen, Format erkennen, Umsätze prüfen und
  Zuordnungsvorschläge bestätigen.
- **Konfiguration:** Konten, Steuerschlüssel, Kostenstellen,
  Debitoren-/Kreditorenregeln und Formatversionen verwalten.
- **Transferverlauf:** Importe, Exporte, Revisionen, Fehler und Übertragungen
  organisationsbezogen nachvollziehen.

Kritische Aktionen benötigen eine Zusammenfassung der Auswirkungen. Für
Massenstatusänderungen durch den Zahlungsabgleich ist eine abschließende
Bestätigung erforderlich.

## Datenschutz, Sicherheit und Aufbewahrung

Die Hausstandards der Anwendung gelten unverändert auch hier:

- **Verschlüsselung at-rest:** Bankumsätze, Stammdatensätze und
  Zahlungsaufträge enthalten IBANs, Namen und Verwendungszwecke — bei
  Spesenerstattungen auch Mitarbeiterbankdaten. Diese Felder werden wie
  andere PII-Felder verschlüsselt abgelegt (encrypted Casts,
  `security:encrypt-existing`). Folge für das Matching: verschlüsselte
  Spalten sind nicht SQL-durchsuchbar; die Zuordnung läuft über
  unverschlüsselte Ableitungen ohne Klartext-Personenbezug (normalisierte
  Beträge, Datumsfelder, Hash-/Blindindex auf Referenz und IBAN). Das ist
  bei der Modellierung von Anfang an einzuplanen, nicht nachzurüsten.
- **VVT und AVV:** „Zahlungsabgleich", „Buchhaltungsübergabe an die
  Kanzlei" und „Lohndatenübermittlung" sind eigene Verarbeitungstätigkeiten
  im Datenschutzmanagement (Feature 043) mit der Steuerberatung als
  Empfänger; Auftragsverarbeitung bzw. gemeinsame Verantwortlichkeit ist zu
  klären und im bestehenden AVV-Register zu führen.
- **Betroffenenrechte vs. Aufbewahrung:** Personenbezogene Bankumsätze
  unterliegen der handels-/steuerrechtlichen Aufbewahrung; Löschersuchen
  werden gemäß Art. 17 Abs. 3 lit. b DSGVO beantwortet (Einschränkung der
  Verarbeitung statt Löschung). Das Löschkonzept dokumentiert diese
  Ausnahme ausdrücklich.
- **Audit-Hash-Kette:** `FinancialTransferReceipt` und
  `BillingTransferReceipt` sind append-only und werden in die bestehende
  Hash-Ketten-Verifikation aufgenommen (`config('audit.chains')`,
  `audit:verify`) — „revisionsnah" heißt hier konkret: derselbe Mechanismus
  wie bei den übrigen Ketten.
- **Aufbewahrung der Exportpakete:** Erzeugte Buchungsstapel, E-Rechnungen
  und Begleitberichte sind selbst aufbewahrungspflichtige Unterlagen
  (10 Jahre). Sie sind vom Downgrade-Purge ausgenommen
  (`purgeable_on_downgrade = false`) und im Datenlebenszyklus (Feature 016)
  sowie in der [GoBD-Gap-Analyse](../gobd-gap-analyse.md) zu verankern.

## Lizenzierung und Modul-Gating

Die Finanzschnittstelle wird als eigenes Modul `module.finance` geführt
(Enterprise bzw. Add-on), strikt getrennt von `module.lohn`:

- Routen-Gating über `EnforcePlanModules` wie bei allen Modulen
  (`config/plans.php`), Menü-Filterung inklusive.
- Der Lohnexport bleibt unter `module.lohn`; eine Organisation kann die
  Lohnübergabe ohne Finanzbuchhaltungs-Funktionen lizenzieren und umgekehrt.
- `purgeable_on_downgrade = false` (siehe Aufbewahrung).
- Der DATEV-Desktop-API-Adapter läuft als Plugin und wird zusätzlich je
  Organisation aktiviert (bestehende Plugin-Mechanik inklusive
  Healthcheck und Auto-Disable).

## Rollen und Berechtigungen

Mindestens folgende Verantwortlichkeiten sind getrennt zu betrachten:

- Finanzkonfiguration verwalten,
- DATEV-Auftragsmapping verwalten,
- Faktura-Zeiten vorbereiten und übertragen,
- Faktura-Produkte/-Materialien vorbereiten und übertragen,
- DATEV-Abrechnungsstatus lesen,
- Buchhaltungsexport vorbereiten,
- Buchhaltungsexport freigeben/herunterladen,
- Bankdatei importieren,
- Zahlungszuordnungen bestätigen,
- Lohnexport vorbereiten und freigeben,
- DATEV-Verbindung verwalten und Übertragung auslösen.

Die Rolle `buchhaltung` ist fachlich passend, darf aber nicht automatisch
Zugriff auf sensible Lohn- oder Mitarbeiterbankdaten erhalten. Lohn und
Finanzbuchhaltung benötigen getrennte Berechtigungen.

## Erfolgskriterien

Das Feature ist für die Anwendung erfolgreich, wenn:

- eine Organisation freigegebene, abrechenbare Zeiten ohne erneute Erfassung
  an einen DATEV-Auftrag übergeben und dort zur Rechnungserstellung verwenden
  kann,
- verwendete Produkte/Materialien unabhängig von den Zeiten an denselben
  DATEV-Auftrag übergeben werden können,
- eine Organisation mit Lexoffice abrechenbare Stunden und Materialien als
  Positionen an Lexoffice übergeben kann, ohne sie lokal zu fakturieren,
- eine Organisation Ausgangsrechnungen und freigegebene Spesen ohne manuelle
  Neuanlage als geprüften DATEV-Stapel an die Buchhaltung geben kann,
- ein CAMT.053-Auszug offene Rechnungen zuverlässig als
  Zuordnungsvorschläge findet,
- bestätigte Zahlungen nachvollziehbar und ohne Dubletten übernommen werden,
- der Lohnexport weiterhin unabhängig vom Buchhaltungsweg funktioniert,
- Installationen ohne DATEV Desktop API alle dateibasierten Kernabläufe nutzen
  können.

## Akzeptanzkriterien

- [ ] Der Nutzer wählt ein konkretes DATEV-Profil mit sichtbarer
      Formatversion.
- [ ] Kunden, Projekte, Benutzer und Tätigkeiten können eindeutig auf
      DATEV-Mandant, Auftrag/Teilauftrag, Mitarbeiter und Kostenposition
      abgebildet werden.
- [ ] Nur freigegebene und abrechenbare Leistungen werden zur DATEV-Fakturierung
      angeboten.
- [ ] Zeit- und Produkt-/Materialquellen werden in getrennten Übergabekanälen
      ausgewählt, geprüft, bestätigt und protokolliert.
- [ ] Eine erfolgreiche Zeitübertragung verändert nicht den Übergabestatus
      offener Materialverwendungen und umgekehrt.
- [ ] Materialpositionen enthalten nachvollziehbare Menge, Einheit, Preis,
      Steuerbehandlung und DATEV-Kostenposition.
- [ ] Die Vorschau zeigt Zeit- und Produktquellen in getrennten Paketen sowie
      die daraus entstehenden DATEV-Buchungen einschließlich Zeiteinheiten,
      Rundung, Mengen und Beträgen.
- [ ] Erfolgreich übertragene Quellen können nicht zusätzlich lokal fakturiert
      oder erneut an dasselbe Ziel übertragen werden.
- [ ] DATEV-Posting-ID, Auftrag, Payload-Hash und Quellreferenzen werden
      nachvollziehbar gespeichert.
- [ ] DATEV-Rechnungsnummer und Billing-Status werden lesend synchronisiert,
      ohne einen konkurrierenden lokalen Nummernkreis zu erzeugen.
- [ ] Nutzer können unterstützte Finanzdateien hochladen und erhalten vor der
      Übernahme eine Vorschau mit erkanntem Format und Formatversion.
- [ ] Ein Import verändert vor der ausdrücklichen Bestätigung keine
      fachlichen WorkDiary-Daten.
- [ ] Originaldatei, normalisierte Daten und Importnachweis bleiben
      nachvollziehbar miteinander verbunden.
- [ ] Wiederholte Importe derselben Datei oder Transaktion erzeugen keine
      unbemerkten Dubletten.
- [ ] Bankumsätze können bestätigt einer Rechnung, Teilzahlung,
      Spesenerstattung oder dem Status „unzugeordnet“ zugewiesen werden.
- [ ] Eine bestätigte Zuordnung ist reversibel, ohne den ursprünglichen
      Bankumsatz zu verändern oder zu löschen.
- [ ] Nur freigegebene Daten können exportiert oder übertragen werden.
- [ ] Rechnungen, Gutschriften und Spesen werden höchstens einmal je
      Exportrevision buchhalterisch übergeben.
- [ ] Fehlende Personalnummern, Lohnarten, Kostenstellen oder Konten verhindern
      den finalen Export oder werden ausdrücklich als Warnung bestätigt.
- [ ] DATEV-Dateien werden über `php-financial-formats` erzeugt und erneut
      eingelesen/validiert.
- [ ] Konvertierungen zeigen nicht abbildbare oder nur abgeleitete Felder vor
      dem Export an.
- [ ] Import- und Exportformate sind als Capability-Matrix dokumentiert und
      werden nicht nur anhand ihrer Dateiendung erkannt.
- [ ] Der Exportnachweis enthält Hash, Formatversion, Zeitraum, Organisation,
      ausführende Person und Quellreferenzen.
- [ ] Ein identischer erneuter Export ist reproduzierbar oder als neue Revision
      nachvollziehbar.
- [ ] Organisationen können keine Konfigurationen oder Exporte anderer
      Organisationen lesen oder verwenden.
- [ ] Berechtigungen für Lohn, Buchhaltung, Bankimport und
      Verbindungskonfiguration sind getrennt.
- [ ] Der bestehende LODAS-nahe Export wird nicht fälschlich als
      DATEV-zertifiziert bezeichnet.
- [ ] Desktop-API-Zugangsdaten erscheinen weder in Logs noch in
      Datenbank-Auditfeldern.
- [ ] Datei-Export funktioniert ohne installierte oder erreichbare DATEV
      Desktop API.
- [ ] Ist für eine Organisation oder einen Kunden ein führendes externes
      Fakturierungssystem konfiguriert, ist die lokale Rechnungserstellung
      für dessen Quellen gesperrt.
- [x] Lokale Ausgangsrechnungen können als XRechnung (UBL 2.1, EN 16931)
      ausgegeben werden; ein Pflichtfeld-Preflight prüft die fachlichen
      Pflichtangaben. (Teilweise: ZUGFeRD und Schematron-/KoSIT-Validierung
      stehen noch aus.)
- [ ] Die Festschreibe-Entscheidung je Buchungsstapel ist sichtbar und Teil
      des Exportnachweises.
- [ ] Bankdaten mit Personenbezug liegen verschlüsselt vor; das Matching
      funktioniert ohne Entschlüsselung ganzer Tabellen.
- [ ] Eine genehmigte Zeitkorrektur an bereits übergebenen Zeiten wird
      blockiert oder erzeugt nachvollziehbar eine Differenzübergabe.
- [ ] Skontozahlungen innerhalb der Toleranz werden als vollständige Zahlung
      mit Skontoabzug vorgeschlagen, nicht als offene Teilzahlung.
- [ ] Lückenhafte Auszugsreihen (Saldenkette) erzeugen eine sichtbare
      Warnung.
- [ ] Transfer-Nachweise sind Teil der Audit-Hash-Kette und werden von
      `audit:verify` geprüft.

## Tests

- Unit-Tests für Mapping, Rundung, Datumslogik und Formatkontext.
- Golden-File-Tests gegen versionierte, anonymisierte DATEV-, CAMT-, MT-,
  OFX- und QIF-Beispiele.
- Parser-Tests für Formaterkennung, fehlerhafte Dateien und große Eingaben.
- Tests für Dubletten, wiederholte Importe und Teilfehler.
- Tests für Rechnungs-Matching, Teilzahlung, Überzahlung, Gutschrift und
  Rücknahme einer Zuordnung.
- Contract-Tests für Order-, Cost-Item-, Employee-, Expense-Posting- und
  Invoice-Lesezugriffe des DATEV-Gateways.
- Tests für Zeiteinheiten, Taktung, Aggregation, doppelte Übertragung und
  gegenseitigen Ausschluss lokaler/DATEV-geführter Fakturierung.
- Tests für getrennte Zeit-/Materialläufe, Teilfehler nur eines Kanals und
  unabhängige Wiederholung.
- Summenabgleich zwischen WorkDiary-Quellen, Transfermodell und erzeugtem
  Buchungsstapel.
- Write/Read-Roundtrip über Generator und Parser.
- Semantische Roundtrip-Tests statt Byte-Gleichheit, wenn das Zielformat
  Reihenfolge oder optionale Darstellung verändert.
- Feature-Tests für Freigabestatus, Berechtigungen und Mandantentrennung.
- Tests für Skonto, Cent-Toleranz, Sammelbuchungs-Auflösung und
  Saldenkettenprüfung.
- Validierungstests für XRechnung/ZUGFeRD gegen das EN-16931-Regelwerk.
- Tests für den gegenseitigen Ausschluss von Zeitkorrektur und bestehender
  Übergabe (Sperre bzw. Differenzlauf).
- Hash-Ketten-Verifikation der Transfer-Nachweise (`audit:verify`).
- Negativtests für fehlende Mappings und ungültige Formatkonfiguration.
- Keine regulären CI-Tests gegen eine reale DATEV-Installation.

## Risiken und offene Entscheidungen

- Das konkrete LODAS-/Lohn-und-Gehalt-Importformat muss fachlich verifiziert
  werden; der vorhandene Vier-Spalten-Export reicht dafür nicht als
  Spezifikation.
- `php-financial-formats` nennt derzeit unterschiedliche Composer-
  Paketbezeichner in `composer.json` und README. Das ist vor der Integration zu
  bereinigen.
- Das DATEV SDK benötigt eine lokal erreichbare DATEV-Installation; SaaS- und
  Container-Betrieb brauchen daher einen getrennten Connector/Agent-Ansatz.
- Der genaue Produktname und Funktionsumfang von „DATEV Faktura“ muss pro
  unterstützter DATEV-Version gegen die verfügbare Order-Management-API
  verifiziert werden.
- Der offizielle DATEV-Schnittstellenüberblick dokumentiert für
  Auftragswesen/Faktura nur Artikel-Stammdaten als Dateiimport. Daraus darf
  keine Unterstützung für Zeit-, Materialverbrauchs- oder
  Rechnungspositionsimporte abgeleitet werden.
- Das CSV-Detaildokument für Geschäftspartner und Artikel gilt für das am PC
  installierte DATEV Auftragswesen. `DATEV Auftragswesen next` benötigt ein
  eigenes, separat geprüftes Profil.
- Das aktuelle SDK kann Expense Postings anlegen, Rechnungen jedoch nur lesen.
  Rechnungserstellung und Finalisierung bleiben deshalb zunächst in DATEV.
- Die DATEV-Zeiteinheit und die Semantik von Kosten-/Gebührenpositionen dürfen
  nicht aus WorkDiary-Stundenwerten geraten, sondern müssen aus DATEV gelesen
  und fachlich gemappt werden.
- Für Produkte/Materialien ist mit der realen DATEV-API zu verifizieren, ob
  Menge und Betrag direkt als Expense Posting abbildbar sind oder eine andere
  Kosten-/Materialfunktion verwendet werden muss.
- Lizenz- und Updatepolitik der eingebundenen AGPL-Bibliotheken muss zur
  Distribution von WorkDiary passen.
- DATEV-Markennamen und Aussagen zur Kompatibilität dürfen keine Zertifizierung
  suggerieren.
- Konvertierungen zwischen Finanzformaten sind nicht grundsätzlich verlustfrei.
  Unterstützte Feldabbildungen und bekannte Verluste müssen je Formatpaar
  dokumentiert werden.
- Der Import externer Finanzdaten benötigt Regeln für fachliche Zuordnung,
  Dubletten und Löschung; ein reiner Parser-Aufruf reicht nicht.
- Das aktuelle Rechnungsmodell kennt keinen eigenständigen Zahlungsdatensatz.
  Teilzahlungen und mehrere Bankumsätze pro Rechnung benötigen vor Phase 4 ein
  fachliches Modell statt einer direkten Erweiterung von `paid_on`.
- Für Spesen ist zu klären, ob Buchhaltungsexport und Erstattung zwei getrennte
  Vorgänge sind. Eine Ausgabe darf nicht versehentlich doppelt als Aufwand
  exportiert werden.
- Bankkonten der eigenen Organisation müssen eindeutig konfigurierbar sein;
  Kunden-, Lieferanten- und Mitarbeiterbankdaten reichen dafür nicht aus.
- Die B2B-E-Rechnungspflicht (Ausstellung gestaffelt 2027/2028) setzt dem
  Pfad „WorkDiary führt" eine gesetzliche Frist, die unabhängig von
  DATEV-Prioritäten gilt.
- Verschlüsselte Bankdatenfelder verhindern SQL-seitiges Matching; ohne früh
  eingeplante Blindindex-/Ableitungsspalten wird der Zahlungsabgleich
  unverhältnismäßig langsam oder unsicher.
- Genehmigte Zeitkorrekturen nach erfolgter Übergabe sind heute technisch
  möglich (`TimeCorrectionRequest` kennt keine Übergabenachweise) — ohne
  Sperre oder Differenzlauf entsteht stille Inkonsistenz zwischen WorkDiary
  und dem führenden Fakturierungssystem.

## Abhängigkeiten

- [Lohn, Zuschläge und DATEV/Lexware](./005-lohn-zuschlaege-datev-lexware.md)
- [Integrationen und offene API](./008-integrationen-api.md)
- [Import, Migration und Onboarding](./020-import-migration-onboarding.md)
- [Compliance, Korrekturen und Audit](./006-compliance-korrekturen-audit.md)
- [Dokumentenmanagement](./031-dokumentenmanagement.md)
- [Zeit-Export](../zeit-export.md)
- Monatsfreigabe und Zuschlagsregeln
- [Datenschutzmanagement: VVT, AVV und Betroffenenrechte](./043-datenschutzmanagement-vvt-avv-betroffenenrechte.md)
- [Benachrichtigungen und Eskalationen](./018-benachrichtigungen-eskalationen.md)
- [Externe Beteiligte, Subunternehmer und Prüfer](./033-externe-beteiligte-subunternehmer-pruefer.md)
- [GoBD-Gap-Analyse](../gobd-gap-analyse.md)
- Endkunden-Modell (geplant; Interimszustand „Endkunde im Projektnamen")

## Nicht Teil des ersten Inkrements

- DATEV-Zertifizierung.
- Vollautomatische Übertragung ohne Prüfschritt.
- Speicherung von DATEV-Passwörtern im Klartext.
- Ersatz einer Finanzbuchhaltung oder Lohnabrechnung.
- Vollständiges Hauptbuch, Offene-Posten-Buchhaltung oder Bankkontoführung.
- Eigene Nachbildung der DATEV-Fakturierungslogik oder des DATEV-Nummernkreises.
- Automatische Kontierung allein anhand von Freitext oder Gegenpartei.
- Allgemeine Konverter-Oberfläche ohne Bezug zu einem WorkDiary-Vorgang.
- Automatische Verbuchung importierter Banktransaktionen ohne Bestätigung.
- Vollständige Unterstützung aller von `php-financial-formats` angebotenen
  Formate im ersten Inkrement.
- OCR oder PDF-Import von DATEV-Auswertungen.

## GitHub Issues

- TBD
