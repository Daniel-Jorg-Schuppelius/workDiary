# GAEB-Leistungsverzeichnisse und AVA-Austausch

## Status

Planned - fachlich geschnitten als MVP-080 bis MVP-086. Das Feature erweitert
das Bau-/Ausbauprofil um importierbare und exportierbare
Leistungsverzeichnisse, Angebotspositionen, Aufmaßbezug und
Nachtragsstruktur. Grundlage ist GAEB DA XML; die aktuelle eingeführte
Ziellinie ist GAEB DA XML 3.3, nicht die Beta-Version 3.4.

## Ziel

WorkDiary soll Leistungsverzeichnisse aus Bau-, Ausbau- und
Instandhaltungsprojekten strukturiert aufnehmen, mit Auftrag, Projekt,
Protokollen, Aufmaß, Nachträgen, Material und Nachkalkulation verbinden und
kontrolliert wieder als GAEB-Datei ausgeben können.

WorkDiary wird dabei kein vollständiges AVA-System. Der Kern bleibt die
operative Ausführung und Nachweisführung. GAEB bildet die Brücke zu
Ausschreibung, Angebotsabgabe, Auftragserteilung, Abrechnung und vorhandenen
AVA-/ERP-Systemen.

## Warum

Im Bau- und Ausbauumfeld entstehen viele abrechenbare Leistungen aus
Leistungsverzeichnissen statt aus freien Artikel- oder Materiallisten. Ohne
GAEB-Bezug gehen Ordnungszahlen, Positionsstruktur, Mengen, Einheitspreise,
Alternativ- und Bedarfspositionen sowie Nachträge im operativen Auftrag
verloren. Dann sind Bautagesbericht, Aufmaß, Materialverbrauch,
Nachkalkulation und spätere Rechnungsprüfung nur schwer gegen das
ursprüngliche LV nachvollziehbar.

## Fachlicher Schnitt

```text
GAEB-Datei / AVA-System
        ↓
Leistungsverzeichnis mit OZ, Positionen, Mengen und Texten
        ↓
Projekt / Auftrag / Bauabschnitt in WorkDiary
        ↓
Ausführung: Tagesbericht, Aufmaß, Material, Fotos, Nachtrag
        ↓
Nachkalkulation, Abrechnungsvorbereitung, GAEB-Export
```

Der GAEB-Import erzeugt keinen parallelen Artikelstamm. Positionen werden als
LV-Positionen geführt und können optional mit Artikeln, Leistungen,
Materialien oder Arbeitsplanpositionen verknüpft werden. Die führende Stelle
für Artikel, Preise, Lager und Rechnungen bleibt über die bestehenden
Integrations- und Provider-Regeln definiert.

## MVP

- Import von GAEB DA XML für Leistungsverzeichnisse mit Hierarchie,
  Ordnungszahlen, Kurz-/Langtexten, Mengen, Einheiten und Positionsarten.
- Validierung mit Preflight: Version, Austauschphase, Pflichtfelder,
  Ordnungszahl-Eindeutigkeit, Mengen- und Einheitenplausibilität.
- Projekt- oder Auftragszuordnung inklusive Bauabschnitt, Gewerk und
  optionalem Kundenbezug.
- LV-Positionsübersicht mit Suche, Status, Nachtragskennzeichnung und
  Drill-down zu Auftrag, Aufmaß, Protokollen und Materialverbrauch.
- Angebots- und Kostenfelder als Snapshots, ohne das externe AVA- oder
  Faktura-System zu ersetzen.
- Aufmaß- und Mengenfortschritt je LV-Position aus Protokollen,
  Bautagesberichten oder manueller Erfassung ableiten.
- Nachträge strukturiert an bestehende LV-Positionen, Bauabschnitte oder neue
  Positionen koppeln.
- Export eines bearbeiteten LV-Standes für Angebot, Auftrag, Nachtrag oder
  Rechnungs-/Aufmaßübergabe, soweit die Zielformate und Rechte es zulassen.

## Nicht im ersten GAEB-MVP

- Vollständige AVA-Funktion mit Ausschreibungsversand, Vergabeportal,
  Bieterverwaltung und Wertungsmatrix.
- Juristisch verbindliche elektronische Vergabe.
- Vollständige REB-/X31-Mengenermittlung mit allen Rechenansätzen.
- BIM-Modellkopplung.
- Preisspiegel als Kernfunktion; er bleibt ein späterer Ausbau oder ein
  Adapterthema.
- Automatische Rechnungsstellung allein aus LV-Mengen ohne Freigabe- und
  Fakturaübergabeprozess.

## Datenstruktur

Vorgesehene fachliche Entitäten:

- `gaeb_imports`
- `bill_of_quantities`
- `boq_sections`
- `boq_items`
- `boq_item_price_snapshots`
- `boq_item_progress`
- `boq_item_mappings`
- `boq_exports`

`boq_items` speichern mindestens:

- Organisation, Projekt und optional Auftrag
- GAEB-Version und Austauschphase als Snapshot
- Ordnungszahl und Positionsnummer
- Kurztext, Langtext und optionale Bietertextergänzungen
- Menge, Einheit und Positionsart
- optionale Einheitspreise, Gesamtpreise und Kostensnapshots
- Nachtrags-, Alternativ-, Bedarfs- oder Zuschlagskennzeichen
- externe Herkunfts-ID und Importlauf
- Status: Entwurf, importiert, angeboten, beauftragt, in Arbeit,
  abgeschlossen, ersetzt oder storniert

## Integrationsprinzip

- GAEB-Dateien werden wie andere Fachimporte versioniert und mit
  Preflight-Protokoll gespeichert.
- Ein Import darf bestehende LV-Positionen mit Ausführungs- oder
  Abrechnungsbezug nicht still überschreiben. Abweichungen erzeugen einen
  Vergleichs- oder Konfliktstand.
- Exportvorgänge sind idempotent und auditierbar.
- Datei-Parser und Generatoren gehören perspektivisch in ein passendes
  Toolkit beziehungsweise ein optionales Formatmodul, nicht dauerhaft als
  Controller-Logik in die App.
- Wenn ein externes AVA-System führt, ist WorkDiary Ausführungs- und
  Nachweissystem mit synchronisiertem LV-Bezug.

## MVP-Zerlegung

- `MVP-080`: GAEB-Modul fachlich lizenzierbar schneiden, Datenführerschaft
  für LV, Preise, Aufmaß und Rechnung organisationsbezogen definieren.
- `MVP-081`: GAEB DA XML Import-Preflight für Leistungsverzeichnisse mit
  Version, Austauschphase, Schema-/Strukturprüfung und Fehlerprotokoll
  konzipieren.
- `MVP-082`: LV-Datenmodell mit Abschnitten, Ordnungszahlen, Positionen,
  Texten, Mengen, Einheiten, Preis-Snapshots und Nachtragskennzeichen
  umsetzen.
- `MVP-083`: LV-Positionen mit Projekt, Auftrag, Protokoll, Aufmaß,
  Materialverbrauch und Nachkalkulation verknüpfen.
- `MVP-084`: Bau-/Ausbauprofil um LV-Workflows für Ausschreibung,
  Angebotsbearbeitung, Aufmaß, Nachtrag und Restleistung erweitern.
- `MVP-085`: GAEB-Export für freigegebene LV-Stände, Angebote,
  Auftrag/Nachtrag oder Abrechnungsübergabe mit Audit und Wiederholungsschutz
  ergänzen.
- `MVP-086`: GAEB-Beispieldaten und Demo-Ablauf für Bau/Ausbau bereitstellen:
  Import, Ausführung, Aufmaß, Nachtrag, Nachkalkulation und Export.

## Akzeptanzkriterien

- Eine GAEB-Datei kann validiert importiert werden, ohne dass ungültige oder
  unvollständige Positionen still in laufende Projekte gelangen.
- Ordnungszahlen, Positionshierarchie, Texte, Mengen, Einheiten und relevante
  Preisinformationen bleiben als Snapshot nachvollziehbar.
- Ein Auftrag oder Protokoll kann auf konkrete LV-Positionen verweisen.
- Aufmaß, Materialverbrauch und Nacharbeit können je LV-Position ausgewertet
  werden.
- Nachträge sind als eigene fachliche Vorgänge sichtbar und nicht nur
  geänderter Freitext.
- Bestehende LV-Positionen mit Ausführungsbezug werden bei Reimport oder
  Aktualisierung nicht still überschrieben.
- Exportierte GAEB-Stände sind versioniert, auditierbar und reproduzierbar.
- Das Bau-/Ausbauprofil liefert sinnvolle Startwerte, ohne die GAEB-Logik fest
  auf ein einzelnes Gewerk zu verdrahten.

## Später

- Vollständige X31-/REB-Mengenermittlung.
- Preisspiegel und Angebotsvergleich.
- Vergabeportal- oder AVA-System-Adapter.
- BIM-/Bauteilreferenzen.
- Zeitvertragsarbeiten.
- Erweiterte Rechnungspakete und Zahlungshistorie.
- Branchenpakete für GaLaBau, Facility Management und technische
  Instandhaltung.

## Abhängigkeiten

- Bau-/Ausbau-Branchenprofil
- Dokumentation und Abnahmeprotokolle
- Nachkalkulation und Wirtschaftlichkeit
- Integrationen und offene API
- Import, Migration und Onboarding
- Lagerwirtschaft und Bestandsintegration
- DATEV- und Finanzschnittstelle
- Dokumentenmanagement
- Audit und Berechtigungen

## GitHub Issues

- TBD
