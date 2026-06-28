# Lieferantenkataloge, Shopimport und Preisabgleich

## Status

Planned - fachlich geschnitten als MVP-090 bis MVP-096. Das Feature ergänzt den
einheitlichen Artikelstamm und die Beschaffung um Lieferantenkataloge,
Shop-Discovery, externe Warenkörbe, Preisbeobachtung und Margenregeln.

## Ziel

WorkDiary soll Artikel aus Lieferanten-Shops und Großhandelskatalogen
kontrolliert in den eigenen Artikelstamm übernehmen können. Einkaufspreise,
Verfügbarkeiten, Lieferantenartikelnummern und Herstellerdaten sollen dabei
nicht nur einmalig importiert, sondern regelmäßig mit dem Lieferantenstand
abgeglichen werden.

Aus diesen Daten sollen eigene Verkaufspreise, Zielmargen und
Kalkulationswarnungen entstehen. WorkDiary bleibt dabei nicht der Shop des
Lieferanten und ersetzt keine vollständige Warenwirtschaft. Es schafft die
verbindliche Zuordnung zwischen externem Katalogartikel, internem Artikel,
Beschaffungsweg, Auftrag, LV-Position, Bestand und späterer Faktura.

## Warum

Viele Betriebe kalkulieren Material auf Basis von Lieferantenpreisen. Ändert
sich der Einkaufspreis oder die Verfügbarkeit im Shop, kann ein Angebot oder
Auftrag schnell unwirtschaftlich werden. Ohne strukturierten Abgleich bleiben
Preisänderungen, neue Artikelnummern, EAN/GTIN-Wechsel oder abgekündigte
Produkte oft unbemerkt.

Der geschäftliche Nutzen liegt deshalb nicht im reinen Dateiimport, sondern in
diesem Ablauf:

```text
Lieferanten-Shop / Katalog
        ↓
externer Artikel mit Preis, Bestand, Marke, EAN/GTIN, URL
        ↓
Mapping auf internen Artikel / Variante / Bezugsquelle
        ↓
Marge, Verkaufspreis, Kalkulations- und Angebotsprüfung
        ↓
Bestellung, Wareneingang, Verbrauch, Nachkalkulation
```

## Fachlicher Schnitt

Das Feature liegt zwischen Artikelstamm, Beschaffung und Integration:

- Der interne Artikelstamm bleibt kanonisch.
- Ein externer Shopartikel ist zunächst nur eine Bezugsquelle oder ein
  Importkandidat.
- Eine automatische Übernahme darf niemals bestehende Artikel mit
  Lagerbewegungen, Aufträgen oder Fakturaübergaben fachlich umdeuten.
- Preise, Verfügbarkeiten und Lieferzeiten werden als historische Snapshots
  gespeichert.
- Verkaufspreise werden aus Margenregeln vorgeschlagen, aber erst nach
  Freigabe verbindlich übernommen.

## Unterstützte Formatspuren

Erste sinnvolle Spuren:

- `shopinfo.xml`: Shop-Metadaten, Katalogquelle, Aktualisierungsart,
  Spalten-/Feldmapping und grobe Shop-Fähigkeiten erkennen. Beispiel:
  Lieferant veröffentlicht eine Shopinfo mit Katalog-CSV, Sprache, Währung,
  Kategorien, Zahlungsarten und Versandinformationen.
- Lieferanten-CSV: pragmatischer Einstieg für Preislisten und Shops, wenn
  `shopinfo.xml` oder ein manuelles Mapping die Spalten beschreibt.
- FTP-/SFTP-/HTTP-Listen: viele Lieferanten stellen nur wiederkehrende
  Preislisten per Dateiablage bereit. Diese Quellen werden wie Katalogquellen
  mit Abrufintervall, Zugangsdaten, Encoding und Mapping behandelt.
- DATANORM: strukturierte Artikel- und Preisdaten für Handwerk, SHK, Elektro,
  Bau/Ausbau und Großhandel.
- BMEcat: Produktkataloge mit Artikel-, Klassifikations-, Preis- und
  Mediendaten.
- OCI / IDS-Connect: externer Shop-Warenkorb wird in WorkDiary übernommen und
  einer Bestellung oder einem Auftrag zugeordnet.
- UGL / openTRANS / XBestellung: nachgelagerte Bestell-, Bestätigungs-,
  Liefer- und Rechnungsprozesse.

## MVP

- Lieferantenkatalog als Importlauf mit Quelle, Format, Encoding,
  Aktualisierungsrhythmus, Datei-Hash und Fehlerprotokoll speichern.
- `shopinfo.xml` lesen, Shop-Metadaten und Katalog-Downloadhinweise anzeigen,
  aber externe URLs nur nach Admin-Freigabe abrufen.
- Mapping-Hinweise aus `shopinfo.xml` übernehmen: Header, Delimiter,
  Spaltennummer, Spaltenname und semantischer Typ werden als Mapping-Vorschlag
  gespeichert und gegen die tatsächlich geladene CSV validiert.
- Katalogquellen über manuellen Upload, HTTP(S)-Download, FTP oder SFTP
  anbinden; Zugangsdaten werden verschlüsselt gespeichert und Abrufe laufen
  protokolliert.
- CSV-Kataloge mit konfigurierbarem Mapping importieren: externe Artikelnummer,
  Hersteller, Herstellerartikelnummer, EAN/GTIN, Name, Beschreibung, Kategorie,
  Einkaufspreis, Bestand/Verfügbarkeit, Lieferzeit, Bild-/Produkt-URL.
- Importkandidaten manuell oder regelbasiert einem internen Artikel, einer
  Variante oder einer neuen Bezugsquelle zuordnen.
- Preis- und Verfügbarkeitsänderungen gegenüber dem letzten bestätigten
  Lieferantenstand erkennen.
- Margenregeln je Organisation, Warengruppe, Lieferant oder Artikelgruppe
  definieren: Aufschlag, Zielmarge, Mindestmarge, Rundung, Mindestverkaufspreis.
- Verkaufspreisvorschläge erzeugen, aber erst nach Freigabe in den internen
  Artikelstamm übernehmen.
- Kalkulationswarnungen anzeigen, wenn ein bestehendes Angebot, ein Auftrag
  oder eine LV-Position durch neue Einkaufspreise unter die Mindestmarge fällt.
- Warenkorb-Import aus Shop/OCI/IDS als Beschaffungsvorschlag oder Bestellung
  speichern, ohne Preise und Artikel ungeprüft zu überschreiben.

## Nicht im ersten MVP

- Vollautomatisches Crawling beliebiger Shops.
- Umgehung von Shop-AGB, Login-Schutz, Rate-Limits oder Lizenzbedingungen.
- Ungeprüfte Übernahme von Shopinfo-Mappings, wenn Header, Spaltenzahl,
  Encoding oder Pflichtfelder nicht zur gelieferten Datei passen.
- Vollständige Preisoptimierung oder dynamische Endkundenpreise ohne Freigabe.
- Marktplatzbetrieb oder eigener Verkaufsshop.
- Automatische Migration des gesamten Artikelstamms ohne manuelle
  Mapping-Freigabe.

## Datenstruktur

Vorgesehene fachliche Entitäten:

- `supplier_catalog_sources`
- `supplier_catalog_imports`
- `supplier_catalog_items`
- `supplier_catalog_item_prices`
- `supplier_catalog_mappings`
- `supplier_catalog_mapping_fields`
- `supplier_shop_connections`
- `supplier_shop_capabilities`
- `supplier_cart_imports`
- `pricing_margin_rules`
- `pricing_change_alerts`

`supplier_catalog_items` speichern mindestens:

- Organisation, Lieferant und Katalogquelle
- externe Artikelnummer und Herstellerartikelnummer
- Hersteller, Marke, EAN/GTIN, Kategorie und Warengruppe
- Bezeichnung, Beschreibung und optionale Medien-/Produkt-URL
- Einkaufspreis, Währung, Steuerhinweis, Verpackungseinheit und Basismenge
- Verfügbarkeit, Bestandshinweis und Lieferzeit als Snapshot
- Importlauf, Rohdaten-Hash und Normalisierungsstatus
- Mappingstatus: neu, vorgeschlagen, verknüpft, Konflikt, ignoriert,
  abgekündigt

`supplier_catalog_sources` speichern mindestens:

- Lieferant, Quelltyp und Format: Upload, HTTP(S), FTP, SFTP,
  `shopinfo.xml`, CSV, DATANORM oder BMEcat
- Abruf-URL beziehungsweise Server, Port und Pfad ohne Klartext-Passwort
- Abrufintervall, letzter erfolgreicher Abruf und nächster geplanter Abruf
- erwartetes Encoding, Delimiter, Headerstrategie und Dezimalformat
- erlaubte Hosts und optionaler TLS-/Fingerabdruck-Hinweis
- Credential-Referenz auf verschlüsselte Plugin-/Quelleneinstellungen

`supplier_catalog_mappings` speichern mindestens:

- Herkunft des Mappings: `shopinfo.xml`, manuell, Vorlage oder vorheriger
  Import
- Feldzuordnung von Lieferantenspalte zu semantischem Zieltyp
- Roh-Spaltenname, normalisierter Spaltenname, Spaltenindex und Pflichtstatus
- Transformationshinweise wie Trimmen, Dezimalformat, Währung,
  Mengen-/Bestandsinterpretation und HTML-/Textbereinigung
- Validierungsstatus gegen den zuletzt geladenen Header

## Preis- und Margenlogik

Preisänderungen werden nicht direkt in historische Vorgänge geschrieben.

- Einkaufspreise des Lieferanten werden versioniert.
- Der interne Standard-Einkaufspreis wird nur nach Freigabe aktualisiert.
- Verkaufspreisvorschläge entstehen aus Margenregeln und Rundungslogik.
- Angebote, Aufträge, LV-Positionen und Bestellungen behalten ihre
  Preis-Snapshots.
- Eine neue Lieferantenpreisliste erzeugt Warnungen, wenn offene Angebote oder
  noch nicht abgeschlossene Aufträge unter die Mindestmarge fallen.
- Bei mehreren Lieferanten je Artikel werden bevorzugte Bezugsquelle,
  Preisstand, Lieferzeit, Mindestbestellmenge und Verfügbarkeit vergleichbar.

Beispiel:

```text
Einkaufspreis alt: 100,00 EUR
Einkaufspreis neu: 112,00 EUR
Zielmarge: 30 %
empfohlener Verkaufspreis netto = 112,00 / (1 - 0,30) = 160,00 EUR
```

## Integrationsprinzip

- Katalogimporte erzeugen keine zweite Artikelwahrheit.
- Mapping auf interne Artikel ist explizit und revisionsfähig.
- Externe IDs, Artikelnummern und URLs bleiben als Bezugsquellen-Snapshot
  erhalten.
- Änderungen externer Artikel mit lokalen Referenzen erzeugen Konflikte oder
  Vorschläge, keine stille Artikelmutation.
- Zugangsdaten für Shops werden verschlüsselt gespeichert und niemals in Logs,
  Exporten oder Supportberichten ausgegeben.
- Download-URLs aus `shopinfo.xml` oder ähnlichen Quellen werden nicht blind
  verfolgt; Admins müssen Quelle, Intervall und erlaubte Hosts freigeben.
- FTP-/SFTP-Zugangsdaten werden als Secret behandelt. Logs, Importfehler,
  Supportberichte und Exporte dürfen weder Passwörter noch vollständige
  Verbindungs-URLs enthalten.
- Mapping-Vorschläge aus Lieferantendateien sind nie autoritativ. Wenn Header,
  Spaltenzahl oder Pflichtfelder abweichen, landet der Import im Preflight
  statt im Artikelstamm.

## MVP-Zerlegung

- `MVP-090`: Lieferantenkatalog- und Shopimport-Modul lizenzierbar schneiden,
  Datenführerschaft für Artikel, Einkaufspreise, Verkaufspreise und Margen
  organisationsbezogen definieren.
- `MVP-091`: Katalogquellen verwalten: Lieferant, Quelltyp, Format, Encoding,
  Aktualisierungsintervall, HTTP(S)-/FTP-/SFTP-Zugangsdaten, erlaubte Hosts
  und Importprotokoll.
- `MVP-092`: `shopinfo.xml` und Lieferanten-CSV als erste Discovery- und
  Katalogimportstrecke mit Mapping-Vorschlägen, Header-Validierung, Preflight
  und Fehlerprotokoll umsetzen.
- `MVP-093`: Externe Katalogartikel mit internem Artikelstamm, Varianten,
  Bezugsquellen und Lieferantenartikelnummern verknüpfen.
- `MVP-094`: Preis-/Verfügbarkeitsabgleich mit Änderungshistorie,
  Konfliktübersicht und Warnungen für Angebote, Aufträge, LV-Positionen und
  Bestellungen ergänzen.
- `MVP-095`: Margenregeln, Verkaufspreisvorschläge, Mindestmargen und
  Freigabeflow für Preisübernahmen umsetzen.
- `MVP-096`: Shop-Warenkorb-Import über OCI/IDS als Beschaffungsvorschlag oder
  Bestellung vorbereiten und an den bestehenden Purchase-Order-Flow anbinden.

## Akzeptanzkriterien

- Eine Organisation kann externe Shop- oder Katalogartikel importieren, ohne
  den internen Artikelstamm ungeprüft zu überschreiben.
- Externe Artikel lassen sich eindeutig auf interne Artikel, Varianten oder
  Bezugsquellen mappen.
- Einkaufspreise, Verfügbarkeiten und Lieferzeiten bleiben historisiert.
- `shopinfo.xml`-Mappings und FTP-/SFTP-Preislisten können als Katalogquellen
  genutzt werden, ohne Mappings oder Zugangsdaten ungeprüft zu übernehmen.
- Preisänderungen erzeugen sichtbare Abgleichs- und Margenwarnungen.
- Verkaufspreisvorschläge folgen nachvollziehbaren Margenregeln und werden
  erst nach Freigabe übernommen.
- Bestehende Angebote, Aufträge, LV-Positionen und Bestellungen behalten ihre
  Preis-Snapshots.
- Zugangsdaten und Shop-URLs werden sicher behandelt und sind auditierbar.

## Später

- DATANORM- und BMEcat-Parser als strukturierte Katalogformate.
- openTRANS-/UGL-Prozesse für Bestellung, Bestätigung, Lieferschein und
  Rechnung.
- Lieferantenvergleich und automatische Bezugsquellenempfehlung.
- Preisstaffeln, kundenindividuelle Konditionen und Rabattgruppen.
- Herstellerdatenblätter, Bilder und Sicherheitsdatenblätter.
- Klassifikationen wie eCl@ss oder UNSPSC.
- Direkte Übergabe freigegebener Artikel an externe Warenwirtschaften.

## Abhängigkeiten

- Lagerwirtschaft und Bestandsintegration
- Integrationen und offene API
- Nachkalkulation und Wirtschaftlichkeit
- DATEV- und Finanzschnittstelle
- GAEB-Leistungsverzeichnisse und AVA-Austausch
- Import, Migration und Onboarding
- Dokumentenmanagement
- Audit und Berechtigungen

## GitHub Issues

- TBD
