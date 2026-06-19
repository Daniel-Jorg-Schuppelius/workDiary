# Lagerwirtschaft und Bestandsintegration

## Status

Planned — fachlich geschnitten als MVP-066 bis MVP-074. Das Feature ergänzt
die [Fertigungs-, Montage- und Arbeitsaufträge](./047-fertigungs-montage-arbeitsauftraege.md)
um Bestandsprüfung, Reservierung und Lagerbewegungen. Es folgt dem
Integrationsprinzip aus
[Integrationen und offene API](./008-integrationen-api.md): Je Organisation und
Datenbereich gibt es genau ein führendes System.

## Ziel

WorkDiary soll Materialbestände entweder selbst schlank führen oder über ein
Plugin aus einer vorhandenen Warenwirtschaft beziehen und dort verändern
können. Fertigungs-, Montage- und Serviceaufträge erhalten dadurch eine
einheitliche Sicht auf Verfügbarkeit, Reservierungen und tatsächliche
Entnahmen, unabhängig vom eingesetzten Bestandssystem.

## Produktpositionierung

WorkDiary wird keine vollständige Warenwirtschaft und kein ERP. Es verbindet
operative Arbeit mit den zuständigen Fachsystemen:

```text
Arbeitsauftrag / Fertigungsauftrag
               ↓
           WorkDiary
 Bedarf · Reservierung · Verbrauch · Nachweis
        ↙              ↓               ↘
 lokales Lager      JTL-Wawi         Lexoffice
 Bestandsführung    Warenwirtschaft  Artikel/Faktura
```

- Ohne externe Warenwirtschaft kann WorkDiary den Bestand lokal führen.
- Bei externer Warenwirtschaft bleibt diese für den Bestand maßgeblich.
- Lexoffice liefert Produkte, Leistungen, Preise und Fakturierung, aber keine
  Lagerbestände. Bei ausschließlicher Lexoffice-Nutzung führt deshalb
  WorkDiary den Bestand lokal.
- DATEV bleibt Ziel für Buchhaltung und Lohn, nicht für operative Bestände.

## Führendes System

Pro Organisation wird für den Datenbereich `inventory` genau ein Provider
festgelegt:

| Modus | Führendes System | Verhalten in WorkDiary |
| --- | --- | --- |
| `local` | WorkDiary | Bestände und Bewegungen werden lokal geführt |
| `external` | z. B. JTL-Wawi | WorkDiary liest und bucht über das Plugin |
| `read_only` | externes System | Bestand wird angezeigt, Buchungen bleiben gesperrt |

Ein paralleles Schreiben in zwei führende Bestände ist unzulässig. Lokale
Caches externer Bestände dienen nur Anzeige, Zuordnung und Ausfallsicherheit;
sie ersetzen nicht den externen Wahrheitsbestand.

Die Führerschaft wird getrennt von anderen Datenbereichen konfiguriert. Eine
Organisation kann beispielsweise Lexoffice für Artikel und Rechnungen,
WorkDiary für Lagerbestand und DATEV für Buchhaltung verwenden.

## Einheitlicher Artikelstamm

WorkDiary erhält einen kanonischen, organisationsbezogenen Artikelstamm.
Materialien, Handelswaren, Halbfabrikate, Fertigerzeugnisse und Leistungen
werden nicht als voneinander unabhängige Stammdatenmodelle geführt.

Artikelarten:

- Rohstoff
- Verbrauchsmaterial
- Handelsware
- Halbfabrikat
- Fertigerzeugnis
- Leistung

Fachliche Eigenschaften steuern das Verhalten:

- lagerfähig
- einkaufbar
- verkaufbar
- herstellbar
- chargenpflichtig
- seriennummernpflichtig

Varianten, z. B. Größe, Farbe oder Ausführung, gehören zum Artikelstamm.
`Asset` bleibt davon getrennt: Ein Artikel beschreibt eine Art von Produkt
oder Material, ein Asset ein konkretes individuelles Betriebsmittel oder
Objekt.

### Artikelvarianten

Der Hauptartikel beschreibt die gemeinsame Produktfamilie. Eine Variante
beschreibt eine konkrete Kombination von Optionswerten:

```text
Artikel: T-Shirt
├── Variante: Farbe=Rot, Größe=S
├── Variante: Farbe=Rot, Größe=M
└── Variante: Farbe=Blau, Größe=M
```

- Varianten erhalten eine eigene interne ID, Artikelnummer/SKU und optional
  GTIN.
- Lagerbestand, Reservierungen, Kosten, Stücklisten und Fertigungsaufträge
  beziehen sich grundsätzlich auf die konkrete Variante.
- Ein Hauptartikel mit Varianten führt standardmäßig keinen eigenen Bestand.
- Ein Artikel ohne Varianten ist selbst die bestandsführende Einheit.
- Nicht jede theoretische Optionskombination muss als Variante existieren.
- Eine Kombination von Optionswerten ist je Hauptartikel eindeutig.
- Optionen und Werte, z. B. `Farbe=Rot` und `Größe=M`, werden strukturiert
  gespeichert und nicht nur in den Artikelnamen geschrieben.

Gemeinsame Eigenschaften können vom Hauptartikel geerbt werden:

- Bezeichnung und Beschreibung
- Steuerklassifikation
- Basiseinheit
- Bilder und Dokumente
- Standard-Arbeitsplan
- Standard-Stückliste oder Rezeptur

Varianten dürfen dafür explizite Überschreibungen besitzen. Änderungen am
Hauptartikel verändern keine bereits in Aufträge, Bewegungen oder externe
Übergaben eingefrorenen Snapshots.

Vorgesehene zusätzliche Entitäten:

- `article_option_definitions`
- `article_option_values`
- `article_variant_option_values`

### Abbildung auf externe Systeme

**JTL-Wawi:** Der WorkDiary-Hauptartikel wird auf den JTL-Vaterartikel
abgebildet, die konkrete Variante auf den eigenständig verwaltbaren
JTL-Kindartikel. Externe Zuordnungen speichern deshalb Vater- und Kindreferenz.
Bestände und Buchungen erfolgen gegen den Kindartikel. Vererbung wird beim
JTL-Plugin explizit berücksichtigt; die konkret verfügbare API-Abbildung wird
im Plugin-Pilot geprüft.

**Lexoffice/Lexware:** Da das Artikelmodell keine Vater-/Kindbeziehung für
Varianten bereitstellt, wird jede zu übertragende WorkDiary-Variante als
eigenständiger Artikel mit eindeutiger Artikelnummer und ausgeschriebener
Variantenbezeichnung abgebildet, zum Beispiel:

```text
T-Shirt – Rot – S   / TS-ROT-S
T-Shirt – Rot – M   / TS-ROT-M
```

Der Hauptartikel muss nicht zusätzlich nach Lexoffice übertragen werden. Eine
Lexoffice-Artikel-ID wird direkt der WorkDiary-Variante zugeordnet. Artikel
ohne Varianten werden wie bisher eins zu eins abgebildet.

**DATEV/Faktura:** Übergaben enthalten die konkrete Varianten-SKU,
Variantenbezeichnung und die zum Vorgang eingefrorenen Positionsdaten.

### Artikelnummern- und GTIN-Hoheit

Artikelnummer/SKU und GTIN sind getrennte Nummernbereiche. Pro Organisation
und Nummernart gibt es genau eine führende Stelle:

- WorkDiary
- angebundene Warenwirtschaft, z. B. JTL-Wawi
- angebundenes Artikel-/Fakturasystem, soweit dessen Schnittstelle die
  Nummernvergabe tatsächlich unterstützt

Die bestehende zentrale Nummernhoheit (`NumberAuthority` und
`NumberSequenceService`) wird dafür erweitert, nicht parallel neu gebaut.

- Führt WorkDiary den Artikelstamm, vergibt WorkDiary SKU und bei Bedarf GTIN.
- Führt JTL-Wawi den Artikelstamm, werden Vater-/Kind-SKU und GTIN aus JTL
  übernommen und lokal gegen Änderung geschützt.
- Lexoffice-Zuordnungen übernehmen die bereits führende WorkDiary- oder
  Wawi-SKU; Lexoffice erzeugt keine konkurrierende Varianten-SKU.
- Eine externe Nummer wird erst nach bestätigter Übernahme verbindlich.
- Bereits verwendete SKU/GTIN werden nicht recycelt.

### Lebenszyklus und Änderungen

Artikel, Varianten, Optionen und Optionswerte mit historischen Bewegungen,
Aufträgen oder externen Zuordnungen werden nicht gelöscht:

- nicht mehr nutzbare Artikel und Varianten werden stillgelegt
- nicht mehr zulässige Optionswerte werden deaktiviert
- historische Bezeichnungen, SKU, GTIN und Optionswerte bleiben als Snapshot
  am jeweiligen Vorgang erhalten
- eine geänderte Optionskombination erzeugt eine neue Variante; sie mutiert
  keine bereits verwendete Variante in eine andere Ausprägung

Nur Entwürfe ohne Referenzen dürfen gelöscht werden.

### Preise

Der Hauptartikel kann Standard-Einkaufs- und Verkaufspreise liefern. Varianten
dürfen diese überschreiben. Preislisten eines führenden externen Systems
werden als externe Preisquelle synchronisiert. WorkDiary speichert für
Aufträge, Lagerbewegungen und Übergaben immer den tatsächlich verwendeten
Preissnapshot.

Spätere Preisänderungen verändern keine historische Kalkulation.

Der bestehende `Material`-Stamm wird nicht parallel weiterentwickelt, sondern
bei der Umsetzung kompatibel in den Artikelstamm überführt. Bestehende
`MaterialUsage`-Datensätze behalten ihre historischen Bezeichnungs-, Mengen-,
Einheiten- und Preissnapshots. `LexofficeArticle` und spätere JTL-Artikel
bleiben externe Caches beziehungsweise Referenzen, nicht zusätzliche
kanonische Artikelstämme.

Diese gemeinsame Grundlage wird in `MVP-060` umgesetzt und sowohl von Feature
047 als auch von diesem Lagerfeature verwendet.

Vorgesehene Kernentitäten:

- `articles`
- `article_variants`
- `article_option_definitions`
- `article_option_values`
- `article_variant_option_values`
- `article_units`
- `external_article_mappings`

## Varianten und Auftragsparameter

Eine Variante beschreibt eine wiederverwendbare, artikel- und
bestandsrelevante Ausprägung. Ein Auftragsparameter gilt dagegen nur für den
konkreten Vorgang:

| Variante | Auftragsparameter |
| --- | --- |
| feste SKU/GTIN | keine eigene SKU |
| eigener Bestand möglich | kein eigener Bestand |
| wiederverwendbare Optionskombination | gilt nur für den Auftrag |
| z. B. Farbe, Größe, Standardausführung | z. B. Kabellänge, Gravurtext, Wunschmaß |

Freie Maße, Texte und kundenspezifische Werte dürfen nicht automatisch neue
Varianten erzeugen. Sie werden über versionierte, typisierte
Auftragsparameter-Definitionen des Arbeitsplans erfasst und beim Auftrag als
Snapshot gespeichert.

## Einheiten und Verpackungen

Jeder Artikel besitzt genau eine Basiseinheit, beispielsweise Stück, Meter,
Kilogramm oder Liter. Weitere Einkaufs-, Verkaufs- und Verpackungseinheiten
werden artikelbezogen mit einem exakten Faktor zur Basiseinheit gepflegt:

```text
Kabel:    1 Rolle  = 100 Meter
Schraube: 1 Karton = 500 Stück
```

- Bestände und Bewegungen werden intern in der Basiseinheit geführt.
- Der ursprüngliche Eingabewert und die verwendete Einheit bleiben als
  Snapshot erhalten.
- Umrechnungen verwenden Dezimalwerte und definierte Rundungsregeln.
- Dimensionswechsel, z. B. Liter zu Kilogramm, sind nur mit einem ausdrücklich
  gepflegten artikelbezogenen Umrechnungsfaktor zulässig.
- Eine Änderung des Faktors verändert keine historischen Bewegungen oder
  Fertigungsaufträge.

## Beschaffungsart und Bezugsquellen

Jeder lager- oder fertigungsrelevante Artikel beziehungsweise jede Variante
besitzt eine Beschaffungsart:

- Einkauf
- Eigenfertigung
- Fremdfertigung
- Einkauf oder Eigenfertigung

Bei mehreren zulässigen Wegen wird der gewählte Beschaffungsweg im konkreten
Bedarfs- oder Fertigungsauftrag eingefroren.

Für einkaufbare Artikel werden Bezugsquellen strukturiert vorgesehen:

- Lieferant
- Lieferantenartikelnummer
- Einkaufseinheit und Umrechnung
- Mindestbestellmenge
- Verpackungseinheit
- Standard- und Sicherheitslieferzeit
- letzter beziehungsweise vereinbarter Einkaufspreis
- bevorzugte Bezugsquelle und Aktivstatus

Der erste Lager-MVP erzeugt aus Fehlmengen einen Beschaffungsbedarf oder
offenen Punkt. Vollständige Bestellungen, Wareneingang gegen Bestellung und
automatische Bestellvorschläge bleiben Folgeausbau.

## Provider-Vertrag

Der Kern arbeitet gegen einen austauschbaren Bestandsprovider. Ein Provider
deklariert seine Fähigkeiten, damit die Oberfläche nur unterstützte Aktionen
anbietet:

- Bestände lesen
- Lagerorte lesen
- Verfügbarkeit prüfen
- reservieren
- Reservierung freigeben
- Entnahme oder Verbrauch buchen
- Wareneingang buchen
- Rückgabe buchen
- Umlagerung buchen
- Korrektur oder Inventurdifferenz buchen
- Fertigerzeugnis einlagern
- Ereignisse/Webhooks empfangen

Vorgesehene Provider:

- `LocalInventoryProvider`
- optionales `JtlWawiInventoryProvider`-Plugin
- generischer read-only Provider für Systeme ohne Schreibschnittstelle

Lexoffice ist bewusst kein `InventoryProvider`. Die bestehende
Lexoffice-Artikelintegration kann Artikelstammdaten liefern, während der lokale
Provider die Bestände führt.

## Lokaler Lagerkern

### Lagerorte

- mindestens ein Lager je Organisation
- optionale Bereiche oder Lagerplätze
- Aktivstatus und Sperrstatus
- optionaler Bezug zu Standort, Fahrzeug oder Team

### Bestandsgrößen

```text
Verfügbar =
    physischer Bestand
    - aktive Reservierungen
    - gesperrter Bestand
    - Bestand in Qualitätsprüfung
```

- physischer Bestand
- reservierte Menge
- gesperrte Menge
- Menge in Qualitätsprüfung
- beschädigte Menge
- Ausschussmenge
- verfügbare Menge
- optional erwarteter Zugang
- Mindestbestand und Meldebestand

Beschädigt, gesperrt und in Qualitätsprüfung bleiben physisch vorhanden, sind
aber nicht frei verwendbar. Ausschuss ist kein verwendbarer Bestand; er bleibt
als Ergebnis und Lagerbewegung nachvollziehbar.

Bestände werden nicht direkt überschrieben. Sie werden aus einem
unveränderlichen Bewegungsjournal abgeleitet oder durch einen kontrollierten
Snapshot mit Journalabgleich beschleunigt.

### Eigentumsarten

Bestand wird zusätzlich nach Eigentum beziehungsweise Verwendungsbindung
getrennt:

- Eigenbestand
- Kundenmaterial
- Konsignationsbestand
- Lieferantenmaterial
- projektgebundener Bestand

Bestände unterschiedlicher Eigentumsarten oder Eigentümer dürfen nicht still
zusammengefasst oder gegeneinander verbraucht werden. Reservierungen übernehmen
die Eigentumsdimension des vorgesehenen Bestands.

### Lagerbewegungen

- Wareneingang
- Entnahme/Verbrauch
- Rückgabe
- Umlagerung
- Reservierung und Freigabe
- Ausschuss
- Inventurdifferenz oder begründete Korrektur
- Zugang eines gefertigten Produkts

Jede Bewegung enthält mindestens Organisation, Artikel/Variante, Lagerort,
Bestandszustand, Eigentumsart/-referenz, Menge in Basiseinheit,
Originalmenge/-einheit, Bewegungsart, Zeitpunkt, auslösende Person, fachliche
Quelle und eine idempotente Vorgangs-ID.

Negative Bestände sind standardmäßig gesperrt. Eine optionale Freigabe muss
rollenbasiert, sichtbar und auditiert sein.

Bestätigte Lagerbewegungen sind append-only. Sie werden weder gelöscht noch in
Menge, Artikel, Lagerort oder Wert verändert. Fehler werden durch eine
referenzierte Gegenbuchung und gegebenenfalls eine korrekte Neubuchung
berichtigt. Nur unbestätigte Entwürfe dürfen verworfen werden.

## Zuordnung externer Artikel

Interne Artikel und Varianten werden über stabile externe Referenzen einem
Artikel des Providers zugeordnet. Die Zuordnung speichert:

- Provider-/Plugin-ID
- externe Artikel-ID
- interne Artikel- oder Varianten-ID
- optionale externe Vaterartikel-ID
- externe Artikelnummer
- Einheit und zulässige Umrechnung
- Synchronisationsstatus
- Zeitpunkt des letzten erfolgreichen Abgleichs

Eine automatische Zuordnung allein anhand des Namens ist nicht ausreichend.
Artikelnummer, externe ID oder eine manuell bestätigte Zuordnung sind
erforderlich.

### Externe Variantenkonflikte

Wird ein externer Artikel oder eine Variante gelöscht, umgehängt oder in ihren
Optionswerten verändert, gilt:

- ohne lokale Referenzen darf die Änderung gemäß Datenführerschaft übernommen
  werden
- mit Lagerbewegungen, Aufträgen, Stücklisten oder Übergaben wird die lokale
  Variante nicht fachlich überschrieben
- die bestehende Variante wird als extern abweichend oder fehlend markiert
- eine neue externe Kombination wird als neue Variante beziehungsweise
  Zuordnung vorgeschlagen
- der Konflikt landet in der zentralen Sync-/Konfliktübersicht

Ein externer Löschvorgang löscht niemals historische WorkDiary-Daten.

## Fehlmaterial und Ersatz

Fehlender Bestand erzeugt einen fachlichen Vorgang statt nur eines technischen
Fehlers. Je Berechtigung und Auftrag sind folgende Reaktionen möglich:

- Auftrag oder betroffenen Schritt blockieren
- verfügbare Teilmenge reservieren und Teilfertigung freigeben
- Ersatzmaterial beantragen und genehmigen
- Umlagerung aus einem anderen Lager anstoßen
- Beschaffungsbedarf beziehungsweise offenen Punkt erzeugen
- ausnahmsweise einen negativen Bestand freigeben

Ersatzmaterial verändert nicht rückwirkend die Stückliste. Es wird als
strukturierte `material_substitute`-Abweichung mit Sollartikel, Ersatzartikel,
Menge, Genehmigung und Begründung dokumentiert.

## Reservierungsstrategie

Der Reservierungszeitpunkt ist organisationsbezogen konfigurierbar:

- bei Freigabe des Auftrags
- bei bestätigter Terminierung
- beim Produktions- oder Montagestart

Standard ist die Reservierung bei Freigabe. Der Auftrag speichert den
angewendeten Modus und Zeitpunkt als Snapshot. Eine spätere
Konfigurationsänderung verändert bestehende Reservierungen nicht.

Reservierungen werden transaktional gegen die verfügbare Menge geprüft.
Priorität, manuelle Umverteilung und Teilreservierung sind sichtbar; eine
jüngere Reservierung darf eine bestätigte ältere Reservierung nicht still
verdrängen.

## Fertigungs- und Montageablauf

```text
Auftrag freigeben
→ Materialbedarf als Snapshot berechnen
→ Verfügbarkeit beim aktiven Provider prüfen
→ Material reservieren
→ Auftrag starten
→ Ist-Verbrauch und Ausschuss buchen
→ Restreservierung freigeben
→ Fertigerzeugnisse einlagern
```

- Fehlender Bestand blockiert die Freigabe oder erzeugt eine sichtbare,
  berechtigte Abweichung.
- Reservierung und tatsächlicher Verbrauch bleiben getrennte Vorgänge.
- Teilverbrauch reduziert nur den verbrauchten Anteil der Reservierung.
- Abbruch oder Mengenreduzierung gibt nicht mehr benötigte Reservierungen frei.
- Fertigerzeugnisse werden erst nach dem fachlichen Abschluss eingebucht.

### Teilfertigung

Ein Fertigungsauftrag kann in mehreren Rückmeldungen, Tagen, Schichten oder
Losen bearbeitet werden. Jede Teilrückmeldung speichert:

- produzierte Menge
- Gutmenge
- Ausschussmenge
- Nacharbeitsmenge
- zugehörigen Ist-Verbrauch
- ausführende Person und Zeitpunkt
- optional Los-/Chargenreferenz für späteren Ausbau

Offene Menge, kumulierter Verbrauch und Restreservierung werden daraus
berechnet. Teil-Einlagerungen sind möglich; ein Auftrag gilt erst als
abgeschlossen, wenn die fachliche Restmenge geklärt ist.

## Kosten und Bewertung

Jede bewertungsrelevante Lagerbewegung erhält einen unveränderlichen
Kostensnapshot:

- Menge
- Einzelkosten
- Gesamtkosten
- Währung
- Bewertungsquelle
- Bewertungszeitpunkt

Für den ersten lokalen Lagerkern ist der gleitende Durchschnittspreis das
Standardverfahren. Ein Provider kann seine eigene Bewertung liefern; WorkDiary
speichert dann den bestätigten Wert als Snapshot. Spätere Preisänderungen
verändern weder abgeschlossene Fertigungsaufträge noch historische
Nachkalkulationen. FIFO und chargenbezogene Bewertung bleiben Folgeausbau.

## Rückverfolgbarkeit

Artikel und Varianten deklarieren bereits im MVP:

- chargenpflichtig
- seriennummernpflichtig
- optional Mindesthaltbarkeit erforderlich

Solange der vollständige Chargen-/Seriennummern-Workflow noch nicht umgesetzt
ist, dürfen solche Artikel nicht still wie gewöhnlicher Bestand behandelt
werden. Der lokale Provider blockiert die relevante Buchung oder weist das
Feature als nicht verfügbar aus. Externe Provider dürfen die
Rückverfolgbarkeit führen; WorkDiary übernimmt deren Referenzen in seine
Snapshots.

Damit kann der spätere Ausbau Chargen und Seriennummern ergänzen, ohne die
fachliche Bedeutung bestehender Artikel zu ändern.

## Inventur

Die Inventur ist stichtagsbezogen und erfordert nicht zwingend eine vollständige
Lagersperre:

1. Sollbestand und Bewertungsstand zum Zählzeitpunkt einfrieren.
2. Zählmenge je Artikel, Variante, Lagerort, Zustand und Eigentumsart erfassen.
3. Bewegungen nach dem Zählzeitpunkt separat fortführen.
4. Differenz gegen den eingefrorenen Sollbestand berechnen.
5. Differenz prüfen und berechtigt freigeben.
6. Korrektur als eigene, auditierte Lagerbewegung buchen.

Zählung, Prüfung und Differenzfreigabe können verschiedenen Personen
zugewiesen werden.

## Synchronisation und Ausfallsicherheit

- Schreibvorgänge an externe Provider verwenden eine persistierte Outbox und
  laufen queue-basiert.
- Jede externe Buchung besitzt eine stabile Idempotenz-ID.
- Wiederholungen dürfen keine doppelte Lagerbewegung erzeugen.
- Übergabestatus: `pending`, `processing`, `confirmed`, `failed`,
  `compensationRequired`.
- Technische Fehler führen nicht zu einer vorgetäuschten erfolgreichen Buchung.
- Webhooks werden verwendet, wenn ein Provider sie belastbar anbietet;
  andernfalls erfolgt ein geplanter Abgleich.
- Konflikte werden sichtbar zur manuellen Prüfung gestellt.
- Fachlicher Auftrag und externe Buchungsbestätigung werden getrennt
  protokolliert.
- Ein externer Provider darf bei Nichtverfügbarkeit keine stillen lokalen
  Ersatzbuchungen erzeugen.
- Eine bereits extern bestätigte Buchung wird nicht per Datenbank-Rollback
  „zurückgenommen", sondern durch eine fachliche Gegenbuchung kompensiert.

## Berechtigungen und Freigaben

Mindestens folgende Berechtigungen werden getrennt:

- Lagerbestand und Bewegungen sehen
- Wareneingang, Entnahme, Rückgabe und Umlagerung buchen
- Reservierungen ändern oder freigeben
- Ersatzmaterial genehmigen
- negative Bestände freigeben
- Bestandskorrekturen buchen
- Inventur zählen
- Inventurdifferenzen prüfen und freigeben
- externe Buchungen erneut übertragen oder kompensieren
- Bestandsprovider und Datenführerschaft konfigurieren

Hohe Inventurdifferenzen, negative Bestände und manuelle Korrekturen können
organisationsbezogen eine Vier-Augen-Freigabe verlangen. Grenzwerte und
Genehmigende werden im Audit festgehalten.

## MVP-Zerlegung

- `MVP-066`: Bestandsführerschaft, Provider-Vertrag und Capability-Matrix
  organisationsbezogen definieren.
- `MVP-067`: Lokale Lagerorte, Bestandszustände, Eigentumsarten und
  append-only Lagerbewegungsjournal umsetzen.
- `MVP-068`: Verfügbarkeit, Reservierungen, Mindestbestände und
  Fehlmaterialprozess ergänzen.
- `MVP-069`: Wareneingang, Entnahme, Rückgabe, Umlagerung und
  stichtagsbezogene Inventur umsetzen.
- `MVP-070`: Kostensnapshots und gleitende Durchschnittsbewertung ergänzen.
- `MVP-071`: Fertigungs-/Montageaufträge mit Teilrückmeldungen, Reservierung,
  Verbrauch, Ausschuss und Einlagerung verbinden.
- `MVP-072`: Persistierte Outbox, Idempotenz, Retry, Konflikte und
  Kompensationsbuchungen für externe Provider umsetzen.
- `MVP-073`: Optionales JTL-Wawi-Plugin gegen den Provider-Vertrag
  konzipieren und anhand einer unterstützten Kundenschnittstelle pilotieren.
- `MVP-074`: Fertigerzeugnisse ausliefern, Bestand abbuchen und als konkrete
  Variante an das führende Fakturasystem übergeben.

## Akzeptanzkriterien

- Eine Organisation kann Lexoffice für Artikel/Faktura und WorkDiary für
  Lagerbestände gleichzeitig verwenden, ohne unklare Datenführerschaft.
- Artikel, Material, Fertigerzeugnis und externe Artikelreferenzen laufen über
  einen kanonischen Artikelstamm.
- Varianten sind eigenständige bestandsführende Einheiten mit eindeutiger
  Optionskombination, SKU und externer Zuordnung.
- Varianten und freie Auftragsparameter sind fachlich getrennt.
- SKU- und GTIN-Hoheit sind je Organisation eindeutig festgelegt.
- Referenzierte Artikel, Varianten und Optionswerte werden stillgelegt statt
  gelöscht oder fachlich umgedeutet.
- Einheiten und Verpackungen werden reproduzierbar auf eine Basiseinheit
  umgerechnet.
- Preise werden vererbt beziehungsweise überschrieben und pro Vorgang als
  Snapshot eingefroren.
- Beschaffungsart und bevorzugte Bezugsquelle sind strukturiert hinterlegt.
- Pro Organisation ist genau ein Bestandsprovider aktiv.
- Verfügbare Mengen berücksichtigen Reservierungen, Sperrbestand und
  Qualitätsprüfung.
- Keine Bestandsänderung erfolgt ohne nachvollziehbare Lagerbewegung.
- Eigentumsarten werden bei Reservierung und Verbrauch nicht vermischt.
- Fehlmaterial, Ersatzmaterial und negative Bestände folgen einem
  berechtigten, auditierten Prozess.
- Teilfertigungen halten Menge, Verbrauch und Ergebnis je Rückmeldung fest.
- Historische Kosten bleiben trotz späterer Preisänderungen unverändert.
- Inventurdifferenzen beziehen sich auf einen eindeutigen Zählzeitpunkt.
- Bestätigte Lagerbewegungen werden ausschließlich durch Gegenbuchungen
  korrigiert.
- Wiederholte externe Übertragungen erzeugen keine Doppelbuchung.
- Externe Variantenänderungen mit lokalen Referenzen erzeugen einen Konflikt
  statt einer stillen Überschreibung.
- Nicht unterstützte Provider-Fähigkeiten werden in UI und Service-Schicht
  blockiert.
- Fertigungsaufträge reservieren Sollbedarf, buchen Ist-Verbrauch und geben
  Restmengen korrekt frei.
- Mandantengrenzen, Berechtigungen und externe Zuordnungen sind getestet.

## Später

- Chargen-, Los-, Mindesthaltbarkeits- und Seriennummernverwaltung.
- Lieferantenbestellungen und automatische Bestellvorschläge.
- Mehrstufige Materialbedarfsplanung.
- Scanner-, Barcode- und Etiketten-Workflow.
- Bewertung nach FIFO oder chargenbezogenen Verfahren.
- Mobile Inventurzählung und zyklische Inventur.
- Weitere Wawi-/ERP-Provider.

## Abhängigkeiten

- Integrationen und offene API
- Plugin-System
- Einheitlicher Artikelstamm und bestehender Materialverbrauch
- Fertigungs-, Montage- und Arbeitsaufträge
- Inventar, Dienstmittel und Assets
- Nachkalkulation und Wirtschaftlichkeit
- Audit, Queue und Benachrichtigungen

## GitHub Issues

- TBD
