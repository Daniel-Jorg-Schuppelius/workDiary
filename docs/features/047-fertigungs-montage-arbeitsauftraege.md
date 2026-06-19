# Fertigungs-, Montage- und Arbeitsaufträge

## Status

Planned — fachlich geschnitten als MVP-060 bis MVP-065 sowie MVP-074. Das
Feature baut auf den versionierten
[Prozeduren, Arbeitsanweisungen und Checklisten](./026-prozeduren-arbeitsanweisungen-checklisten.md)
auf und ergänzt den operativen Auftragsrahmen für Menge, Termin, Materialbedarf
und Fertigungsergebnis. Bestände, Reservierungen und Lagerbewegungen werden im
Folgefeature
[Lagerwirtschaft und Bestandsintegration](./048-lagerwirtschaft-bestandsintegration.md)
bereitgestellt.

## Ziel

WorkDiary soll wiederkehrende Fertigungs- und Montagearbeiten nicht nur als
allgemeinen Auftrag dokumentieren, sondern anhand eines freigegebenen
Arbeitsplans konkret planen und ausführen können.

Der **Arbeitsplan** beschreibt, wie gearbeitet wird. Der
**Fertigungs-/Montageauftrag** beschreibt, was, in welcher Variante und Menge,
bis wann und nach welcher eingefrorenen Arbeitsplan-Version hergestellt oder
montiert werden soll.

## Warum

Bei kleinen und mittleren Fertigungs-, Werkstatt- und Montagebetrieben liegen
Rezepturen, Stücklisten, Bilder und Arbeitsschritte häufig in getrennten
Dokumenten oder nur als Erfahrungswissen vor. Dadurch bleiben Materialbedarf,
Wartezeiten, Ausschuss, Nacharbeit und die tatsächlich verwendete Anleitung
schlecht nachvollziehbar.

WorkDiary besitzt bereits versionierte Prozeduren, Materialstammdaten,
Materialverbrauch, Anhänge, Zeitnachweise und Audit-Ereignisse. Das neue Feature
soll diese Bausteine verbinden, statt einen zweiten Workflow-Kern zu schaffen.
Der bestehende Materialstamm wird dabei gemäß Feature 048 in einen
einheitlichen Artikelstamm überführt, statt neben Fertigungsprodukten einen
weiteren parallelen Stamm aufzubauen.

## Anwendungsfälle

- Figuren aus einer Mischung mit festem Verhältnis von Wasser und Pulver
  gießen, trocknen, entformen, bemalen und prüfen.
- Kleinserien oder Einzelstücke nach versionierter Arbeitsanweisung fertigen.
- Netzwerkstrecken montieren: Kabel verlegen, Jacks nach T568A/T568B auflegen,
  beschriften, messen und dokumentieren.
- Baugruppen montieren und die verwendeten Teile, Werkzeuge, Prüfwerte und
  Abschlussfotos nachweisen.
- Varianten mit unterschiedlicher Stückliste oder unterschiedlichen
  Arbeitsschritten fertigen.
- Gutmenge, Ausschuss und Nacharbeit am Abschluss erfassen.

## Fachliche Abgrenzung

```text
Produkt / Variante
    ↓
Arbeitsplan-Version + Stückliste / Rezeptur
    ↓
Fertigungs- oder Montageauftrag
    ↓
Prozedurlauf mit konkreten Schritten und Wartezeiten
    ↓
Ist-Material, Arbeitszeit, Gutmenge, Ausschuss und Nachweis
```

- `ProcedureTemplateVersion` bleibt die maßgebliche, versionierte
  Arbeitsplan-Definition.
- Ein Auftrag friert die verwendete Arbeitsplan-Version ein. Spätere Änderungen
  verändern laufende oder abgeschlossene Aufträge nicht.
- Der einheitliche Artikelstamm bleibt die Quelle für Rohstoffe,
  Verbrauchsmittel, Handelswaren, Halb- und Fertigerzeugnisse.
- Der tatsächliche Verbrauch wird an die vorhandene Materialerfassung
  angebunden, damit Abrechnung und Nachkalkulation ihn weiterverwenden können.
- `DiaryEntry` kann als übergeordneter Kunden-/Projektauftrag verknüpft werden,
  ersetzt aber nicht die fertigungsspezifischen Mengen- und Statusdaten.

## MVP

### Artikel und Variante

- Herstellbarer Artikel mit Artikelnummer, Name, Basiseinheit und Aktivstatus.
- Optionale Variante, z. B. Größe, Farbe, Ausführung oder
  Verdrahtungsstandard.
- Varianten werden über strukturierte Optionen/Werte gebildet, erhalten eigene
  SKU/GTIN und sind die bestands- sowie fertigungsführende Einheit.
- Der Hauptartikel liefert vererbbare Standarddaten; Variante, Stückliste oder
  Arbeitsplan können diese gezielt überschreiben.
- Zuordnung einer veröffentlichten Arbeitsplan-Version.
- Artikelbezogene Einkaufs-, Verkaufs- und Verpackungseinheiten mit
  historisierter Umrechnung zur Basiseinheit.

### Versionierte Stückliste und Rezeptur

- Materialpositionen je Arbeitsplan-Version.
- Feste Menge, Menge pro Stück oder skalierbarer Anteil.
- Einheit, Rundungsregel und optionaler Verschnittzuschlag.
- Rezepturverhältnisse, z. B. ein Teil Wasser zu drei Teilen Pulver.
- Werkzeuge/Dienstmittel werden separat vom Verbrauchsmaterial ausgewiesen.
- Bilder, PDFs und technische Zeichnungen können einem Arbeitsplan oder
  einzelnen Schritt zugeordnet werden.

Die Stückliste beziehungsweise Rezeptur wird vererbt:

- Hauptartikel oder Standardvariante liefert die Basis.
- Eine Variante darf Positionen hinzufügen, deaktivieren, ersetzen oder Mengen
  überschreiben.
- Überschreibungen referenzieren stabile Positionscodes, nicht nur
  Bezeichnungen.
- Der aufgelöste Gesamtstand wird beim Fertigungsauftrag vollständig als
  Snapshot gespeichert.
- Spätere Änderungen an Basis oder Variante verändern laufende und
  abgeschlossene Aufträge nicht.

### Auftragsparameter

Arbeitspläne können versionierte, typisierte Parameter definieren, z. B.:

- Zahl mit Einheit und Grenzwerten
- Text mit Längenbegrenzung
- Auswahl aus erlaubten Werten
- Maß, Länge oder Fläche
- Datum oder Termin

Parameter wie freie Kabellänge, Wunschmaß oder Gravurtext sind keine
Artikelvarianten und erzeugen keine SKU. Sie werden beim Auftrag validiert,
eingefroren und dürfen die Bedarfsberechnung sowie bedingte Schritte steuern.

### Fertigungs-/Montageauftrag

- Eindeutige Auftragsnummer.
- Artikel beziehungsweise konkrete Variante, Sollmenge und Einheit.
- Status, Priorität, geplanter Start und Fälligkeit.
- Optionaler Kunde, Projekt oder übergeordneter Auftrag.
- Verantwortliche Person oder Team.
- Beschaffungsweg bei Artikeln mit mehreren zulässigen Beschaffungsarten.
- Eingefrorene Arbeitsplan-Version und Materialbedarfs-Snapshot.
- Eingefrorene Variante, Auftragsparameter und aufgelöste Stückliste.

### Ausführung

- Start erzeugt oder verknüpft einen `ProcedureRun`.
- Mobile Schritt-für-Schritt-Ansicht mit Anleitung, Bildern und Nachweisen.
- Material-, Mess-, Foto-, Datei- und Bestätigungsschritte verwenden den
  vorhandenen Prozedur-Kern.
- Bedingungen werden durch den Execution-Kern ausgewertet.
- Abweichungen wie Materialersatz oder alternatives Verfahren nutzen
  `ProcedureDeviation`.

### Warte- und Trocknungszeiten

- Eigener Schritttyp für Warte-, Trocken-, Aushärte- oder Abkühlzeiten.
- Serverseitig gespeicherter Start und frühester Fortsetzungszeitpunkt.
- Nachfolgende Schritte können bis zum Fristablauf blockiert werden.
- Der Ablauf funktioniert unabhängig davon, ob ein Browser geöffnet bleibt.
- Vorzeitige Fortsetzung ist nur als berechtigte, auditierte Abweichung möglich.

### Abschluss

- Ist-Verbrauch je Material.
- Teilrückmeldungen mit produzierter Menge, Gutmenge, Ausschussmenge,
  Nacharbeitsmenge und zugehörigem Verbrauch.
- Abschlussbemerkung und optionale Ergebnisfotos.
- Druck-/PDF-Nachweis mit Auftrag, Arbeitsplan-Version, Schritten, Zeiten,
  Material und Ergebnis.

### Auslieferung und Faktura

Fertigstellung, Einlagerung, Auslieferung und Fakturierung sind getrennte
Vorgänge:

```text
Gutmenge fertigmelden
→ Fertigerzeugnis einlagern
→ Liefer-/Ausgabemenge freigeben
→ Bestand der konkreten Variante abbuchen
→ Lieferschein/Auftragsposition erzeugen oder übergeben
→ Rechnungsposition an das führende Fakturasystem übergeben
```

- Teilmengen dürfen getrennt ausgeliefert werden.
- Lieferung und Rechnungsübergabe verwenden SKU, Variantenbezeichnung, Menge,
  Einheit, Preis- und Steuersnapshot.
- Führt Lexoffice die Faktura, wird die konkrete Variante als flacher
  Lexoffice-Artikel beziehungsweise Positionssnapshot übergeben.
- Führt JTL-Wawi Verkauf und Lager, erfolgt die Bestands- und
  Auftragsbuchung gegen den Kindartikel.
- Führt WorkDiary die Rechnung, gelten die vorhandenen Rechnungs- und
  E-Rechnungsregeln.
- Eine fehlgeschlagene Fakturaübertragung darf eine bereits erfolgte
  Lagerbuchung nicht verbergen; beide Status bleiben getrennt sichtbar.

## Mengen- und Rezepturlogik

Die Bedarfsberechnung muss deterministisch und serverseitig erfolgen.

Beispiel für 20 kg Gesamtmischung bei einem Verhältnis Wasser zu Pulver von
`1:3`:

```text
Gesamtanteile = 1 + 3 = 4
Wasser = 20 kg × 1 / 4 = 5 kg
Pulver = 20 kg × 3 / 4 = 15 kg
```

Einheiten dürfen nur über ausdrücklich gepflegte Umrechnungsfaktoren
konvertiert werden. Liter und Kilogramm sind ohne Dichteangabe nicht
austauschbar. Mengen und Geldwerte werden als Dezimalwerte, nicht als
Fließkommazahlen, berechnet.

## Datenstruktur

Vorgesehene fachliche Entitäten:

- `articles` und `article_variants` aus Feature 048
- `procedure_material_requirements`
- `manufacturing_orders`
- `manufacturing_order_reports`
- `manufacturing_order_materials`
- Verknüpfung von `manufacturing_orders` zu `procedure_runs`

`manufacturing_order_materials` speichert mindestens:

- Materialreferenz und Bezeichnungssnapshot
- berechnete Sollmenge
- Einheitssnapshot
- reservierte Menge über den aktiven Bestandsprovider
- tatsächliche Verbrauchsmenge
- Kostensnapshot zum Buchungszeitpunkt
- Berechnungsgrund und Rundung

## Statusmodell

```text
Entwurf → Freigegeben → In Arbeit → Abgeschlossen
                         ↘ Wartet
                         ↘ Blockiert
Entwurf/Freigegeben/In Arbeit → Abgebrochen
```

- `Wartet`: planmäßige Warte-, Trocken- oder Aushärtezeit.
- `Blockiert`: ungeplantes Hindernis, z. B. fehlendes Material, gesperrtes
  Werkzeug oder Qualitätsproblem.
- Ein abgeschlossener Auftrag ist fachlich unveränderlich; Korrekturen erfolgen
  nachvollziehbar über Ereignisse oder Folgeaufträge.

## MVP-Zerlegung

- `MVP-060`: Einheitlichen Artikelstamm mit Varianten, Nummernhoheit,
  Lebenszyklus, Preisen, Beschaffungsarten, Basiseinheiten und externen
  Zuordnungen organisationsbezogen modellieren.
- `MVP-061`: Versionierte, vererbbare Stücklisten, Rezepturen,
  Auftragsparameter und Anleitungsmedien ergänzen.
- `MVP-062`: Fertigungs-/Montageauftrag mit Statusmaschine,
  Varianten-/Parameter-Snapshot und Materialbedarfsberechnung anlegen.
- `MVP-063`: Ausführbare mobile Prozedurlauf-Ansicht einschließlich
  bedingter Schritte und Medien umsetzen.
- `MVP-064`: Serverseitige Warte-/Trockenschritte mit blockierter Fortsetzung
  implementieren.
- `MVP-065`: Teilrückmeldungen, Ist-Material, Gutmenge, Ausschuss, Nacharbeit
  und Fertigungsnachweis erfassen.
- `MVP-074`: Fertigerzeugnisse ausliefern, Bestand abbuchen und als konkrete
  Variante an das führende Fakturasystem übergeben.

## Akzeptanzkriterien

- Ein Auftrag hält unveränderlich fest, nach welcher Arbeitsplan-Version er
  ausgeführt wird.
- Bei Varianten hält der Auftrag die konkrete Variante einschließlich SKU und
  Optionswert-Snapshot fest.
- Freie kundenspezifische Eingaben werden als typisierte Auftragsparameter und
  nicht als künstliche Varianten gespeichert.
- Sollmengen werden aus Stückliste oder Rezeptur reproduzierbar berechnet und
  beim Freigeben als Snapshot gespeichert.
- Variantenüberschreibungen werden zu einer vollständigen, eingefrorenen
  Stückliste aufgelöst.
- Mitarbeitende sehen pro Schritt Anleitung, Bilder, benötigtes Material und
  erforderliche Nachweise.
- Eine blockierende Wartezeit kann nicht durch Neuladen oder einen anderen
  Client umgangen werden.
- Soll- und Ist-Material sowie Gut-, Ausschuss- und Nacharbeitsmenge bleiben
  getrennt auswertbar.
- Teilfertigungen über mehrere Tage oder Schichten halten Ergebnis und
  Materialverbrauch je Rückmeldung fest.
- Materialersatz, vorzeitige Fortsetzung und andere Abweichungen werden
  strukturiert und auditierbar dokumentiert.
- Teil-Auslieferungen reduzieren den Bestand der konkreten Variante und
  erzeugen eine getrennt nachvollziehbare Fakturaübergabe.
- Mandantengrenzen, Berechtigungen und Sqid-Routen sind für alle neuen
  Entitäten getestet.

## Später

- Mehrstufige Materialbedarfsplanung (MRP).
- Chargen-, Los- und Seriennummernrückverfolgung.
- Splits und Zusammenführung mehrerer Lose.
- Maschinenbelegung, Kapazitätsplanung und Rüstzeiten.
- Fremdfertigung und Lieferantenaufträge.
- Etiketten, Barcodes und Scanner-Workflow.
- Qualitätskennzahlen und statistische Prozesskontrolle.
- Automatische Nachkalkulation je Produkt, Variante und Arbeitsplan-Version.

## Abhängigkeiten

- Prozeduren, Arbeitsanweisungen und Checklisten
- Inventar, Dienstmittel und Assets
- Materialstamm und Materialverbrauch
- Terminierung, Einsatzplanung und Disposition
- Dokumentation und Abnahmeprotokolle
- Nachkalkulation und Wirtschaftlichkeit
- Anhänge und Storage
- Audit und Berechtigungen
- Lagerwirtschaft und Bestandsintegration

## GitHub Issues

- TBD
