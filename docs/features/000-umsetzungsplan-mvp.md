# Umsetzungsplan MVP

## Ziel

Dieser Plan verdichtet die Feature-Roadmap in eine erste entwickelbare Version.
Nicht jede Roadmap-Idee wird sofort umgesetzt. Das MVP soll WorkDiary als
sicheres Aufzeichnungs- und Nachweissystem positionieren, das lokal installierbar
und später SaaS-fähig ist.

## Produktthese

WorkDiary ist kein reines Zeiterfassungstool. WorkDiary weist nach, wer wann
welchen Auftrag angenommen, bearbeitet, dokumentiert, abgenommen und mit welchem
Zeit-, Material- und Dienstmitteleinsatz abgeschlossen hat. Datenschutz,
Mandantentrennung und einheitliche Bedienung sind Teil des Kernprodukts.

WorkDiary ist zugleich die operative Integrationsdrehscheibe zwischen den
eingesetzten Fachprogrammen. Es ersetzt weder Faktura, Warenwirtschaft,
Buchhaltung noch Lohnabrechnung, sondern übergibt die bei der Arbeit
entstandenen, geprüften Daten an das jeweils führende System. Pro Datenbereich
ist genau ein schreibend führendes System festgelegt.

## MVP-Scope

### Muss in Version 1

- Mandantensichere Aufzeichnung von Aufträgen, Zeiten, Anhängen, Kommentaren
  und Statuswechseln.
- Tages- und Monatsabschluss für Arbeitszeiten.
- Protokoll-/Dokumentationsgrundlage mit Fotos, Checklisten und Unterschrift.
- Prozeduren mit Pflichtschritten, z. B. Backup vor Update.
- Facility-Management-Grundlage: Gebäude, Bereiche, Räume und raumbezogene
  Anforderungen für unterschiedliche Gewerke.
- Grundauswertungen für Kunde, Auftrag, Zeit, Material und Nacharbeit.
- Datenschutzgrundsätze sichtbar und technisch unterstützt.
- Vollständige Sicherheitsprüfung des finalen Release-Kandidaten einschließlich
  Authentifizierung und 2FA, Behebung der Befunde und unabhängiger Nachtest als
  verbindliches Produktiv-Release-Gate.
- Einheitliche Bedienung für Listen, Filter, Formulare, Detailseiten und
  Protokolle.
- Lokale Installation mit Lizenz, Diagnose und Backup-Hinweisen.

### Bewusst nicht im MVP

- Vollständige KI-Funktionen.
- Vollautomatische Dienstplanoptimierung.
- Vollständiges Kundenportal.
- Komplexe Kartenleitstelle.
- Vollständige internationale Rechtsräume.
- Branchen-Marktplatz.

## Phase 0: Fundament

### Epic 0.1: Mandanten- und Datenschutz-Fundament

Quellen:
[015](./015-mandantenfaehigkeit-betriebsmodelle.md),
[016](./016-datenschutz-dsgvo-datenlebenszyklus.md),
[019](./019-rollen-rechte-produktprofile.md)

Issues:

- `MVP-001`: Mandantensicherheits-Audit aller Kernmodelle durchführen.
- `MVP-002`: Exporte, Suche, Anhänge, Kalenderfeeds und API auf Mandantengrenzen prüfen.
- `MVP-003`: Systemadmin, Kundenadmin, Teamleitung, Außendienst, Buchhaltung als Rollenprofile dokumentieren und seedbar machen.
- `MVP-004`: Supportzugriff-Grundsätze dokumentieren und Auditpunkte festlegen.
- `MVP-005`: Datenschutzseite für Admins konzipieren: Datenexport, Löschung, externe Dienste, Supportzugriffe.

Definition of Done:

- Kein Kernobjekt kann ohne Mandantenprüfung angezeigt, exportiert oder gesucht
  werden.
- Sensible Datenbereiche sind explizit durch Rechte geschützt.
- Datenschutzversprechen ist in Produktdoku und Admin-Konzept sichtbar.

### Epic 0.2: Einheitliche Bedienung

Quellen:
[037](./037-einheitliche-bedienung-ux-konventionen.md),
[038](./038-barrierefreiheit-zugaenglichkeit.md)

Issues:

- `MVP-006`: UX-Pattern-Katalog für Listen, Filter, Detailseiten, Modale, Status, Anhänge und Kommentare erstellen.
- `MVP-007`: Bestehendes UI-Audit um neue Roadmap-Module erweitern.
- `MVP-008`: Accessibility-Checkliste für neue Seiten definieren.
- `MVP-009`: Einheitliche Status- und Aktionsnamen festlegen.

Definition of Done:

- Neue Features haben eine UI-Checkliste.
- Wiederkehrende UI-Elemente verwenden bestehende Komponenten oder definierte
  neue Komponenten.

### Epic 0.3: Sicherheitsprüfung und Release-Gate

Quelle:
[051](./051-sicherheitspruefung-release-gate.md)

Issues:

- `MVP-097`: Angriffsflächen inventarisieren, Bedrohungsmodell erstellen und
  ASVS-5.0-Kontrollmatrix für den Release-Kandidaten festlegen.
- `MVP-098`: Automatisierte Abhängigkeits-, SAST-, Secret-, SBOM- und
  Konfigurationsprüfungen reproduzierbar in CI und Releaseprozess integrieren.
- `MVP-099`: Gesamte Anwendung manuell per Whitebox- und dynamischer Prüfung
  untersuchen, Befunde beheben und Regressionstests ergänzen.
- `MVP-100`: Authentifizierung, Sitzungen, Passwort-/Recovery-Flows und alle
  2FA-Methoden in Hauptanwendung und Kundenportal auf Umgehungen prüfen und
  härten.
- `MVP-101`: Fixes nachtesten, unabhängigen Penetrationstest abschließen und
  das Security-Release-Gate dokumentiert freigeben.

Definition of Done:

- Alle inventarisierten Angriffsflächen sind gegen OWASP ASVS 5.0 Level 2 und
  risikobasiert ausgewählte Level-3-Anforderungen geprüft.
- Kritische, hohe und mittlere Befunde sind behoben und nachgetestet; niedrige
  Befunde sind behoben oder formal befristet behandelt.
- 2FA, Recovery, Sessions, Legacy-Login, Kundenportal, Rollen- und
  Mandantengrenzen besitzen positive sowie negative Umgehungs- und
  Parallelitätstests.
- Ein unabhängiger Nachtest enthält keine offenen freigabesperrenden Befunde.
- Ohne dokumentierte Security-Freigabe wird der MVP nicht produktiv
  ausgerollt.

### Epic 0.4: Nutzung der eigenen Toolkits

Quelle:
[052](./052-toolkit-nutzung-konsolidierung.md)

Issue:

- `MVP-102`: Nutzung der eigenen Toolkits repo-weit prüfen, Funde
  klassifizieren, geeignete lokale Implementierungen durch Toolkit-APIs
  ersetzen und fehlende fachneutrale Funktionen im zuständigen Toolkit
  ergänzen.

Definition of Done:

- Der definierte produktive Codeumfang ist vollständig geprüft und jeder Fund
  als bestehende Nutzung, Duplikat, Toolkit-Erweiterung, app-spezifische
  Geschäftslogik, optionale Fähigkeit oder begründeter Prüfbedarf
  klassifiziert.
- Bestätigte Duplikate sind an allen produktiven Aufrufstellen ersetzt;
  fehlende fachneutrale Funktionen werden zuerst im passenden Toolkit
  getestet und veröffentlicht.
- Geschäftsregeln, Mandantengrenzen und Laravel-Orchestrierung bleiben in
  WorkDiary.
- Das optionale private Finanzformat-Paket bleibt gegatet und außerhalb der
  committeten `composer.lock`.
- Qualitäts-Gates der geänderten Toolkits und von WorkDiary sind grün.

## Phase 1: Aufzeichnung und Zeit

### Epic 1.1: Auftrags- und Arbeitsnachweis

Quellen:
[001](./001-zeiterfassung-kernprodukt.md),
[023](./023-suche-timeline-fallakte.md),
[030](./030-kommunikationsprotokoll.md)

Issues:

- `MVP-010`: Auftragsverlauf als Timeline definieren: Status, Zeit, Kommentar, Anhang, Protokoll, Material, Abnahme.
- `MVP-011`: Auftrag/DiaryEntry um strukturierte Annahme-, Bearbeitungs- und Abschlussereignisse erweitern.
- `MVP-012`: Kommunikationsnotizen an Auftrag, Kunde und Projekt hängen.
- `MVP-013`: Detailansicht für Auftrag als Fallakte konzipieren.
- `MVP-014`: Globale Suche auf Auftrag, Kunde, Projekt, Kommentar und Anhang-Metadaten prüfen.

Definition of Done:

- Ein Auftrag kann als nachvollziehbarer Verlauf gelesen werden.
- Kommunikation und Entscheidungen verschwinden nicht in losem Freitext.
- Jede wichtige Änderung hat Zeit, Person und Kontext.

### Epic 1.2: Zeiterfassung, Tagesabschluss und Monatsfreigabe

Quellen:
[001](./001-zeiterfassung-kernprodukt.md),
[005](./005-lohn-zuschlaege-datev-lexware.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md)

Issues:

- `MVP-015`: Tagesabschluss-Ansicht definieren: Anwesenheit, Pausen, gebuchte Zeit, Restzeit, Warnungen.
- `MVP-016`: Monatsfreigabe-Datenmodell skizzieren: eingereicht, geprüft, zurückgegeben, genehmigt.
- `MVP-017`: Korrekturanträge für Zeitdaten fachlich und technisch schneiden.
- `MVP-018`: Plan/Ist-Abgleich für Anwesenheit, Projektzeit und Schicht vorbereiten.
- `MVP-019`: Exportgrundlage für geprüfte Zeiten definieren.

Definition of Done:

- Mitarbeitende erkennen offene oder unstimmige Tage.
- Führungskräfte können Monatszeiten prüfen.
- Nachträgliche Änderungen sind begründet und nachvollziehbar.

## Phase 2: Dokumentation und Prozeduren

### Epic 2.1: Protokolle und Abnahmen

Quellen:
[003](./003-dokumentation-abnahmeprotokolle.md),
[031](./031-dokumentenmanagement.md),
[032](./032-vorlagen-formularsystem.md)

Issues:

- `MVP-020`: Protokoll-Datenmodell entwerfen: Typ, Auftrag, Punkte, Anhänge, Unterschriften, Status.
- `MVP-021`: Protokollpunkt-Typen definieren: Text, Auswahl, Foto, Datei, Messwert, Signatur.
- `MVP-022`: Abnahme mit Unterschrift, Zeitstempel und PDF-Ausgabe umsetzen.
- `MVP-023`: Vorher-/Nachher-Fotos strukturiert am Protokollpunkt speichern.
- `MVP-024`: Offene Punkte mit Verantwortlichkeit, Frist und Status ergänzen.

Definition of Done:

- Ein abgeschlossener Auftrag kann als Protokoll exportiert werden.
- Fotos, Unterschriften und offene Punkte hängen am richtigen Kontext.
- Abgenommene Protokolle werden nicht still überschrieben.

### Epic 2.2: Prozeduren und Pflichtnachweise

Quellen:
[026](./026-prozeduren-arbeitsanweisungen-checklisten.md),
[013](./013-qualitaet-sicherheit-arbeitsschutz.md)

Issues:

- `MVP-025`: Prozedurvorlagen mit Version, Anwendungsbereich und Schritten modellieren.
- `MVP-026`: Pflichtschritte und Reihenfolge technisch erzwingen.
- `MVP-027`: Nachweistyp `Backup` für Update-/Change-Prozeduren definieren.
- `MVP-028`: Zweite Person/Freigeber pro Schritt sichtbar machen.
- `MVP-029`: Abweichungen mit Begründung und Folgeaktion speichern.

Definition of Done:

- Ein Update kann nur sauber abgeschlossen werden, wenn Backup, Durchführung,
  Test und Freigabe dokumentiert sind.
- Vier-Augen-Schritte sind sichtbar und nachvollziehbar.
- Alte Aufträge behalten die damalige Prozedurversion.

## Phase 3: Stammdaten und Auswertbarkeit

### Epic 3.1: Klassifikationen und Datenqualität

Quellen:
[024](./024-klassifikationen-tags-datenqualitaet.md),
[042](./042-gewerke-branchenprofile.md)

Issues:

- `MVP-030`: Kernklassifikationen definieren: Auftragstyp, Tätigkeit, Fehlerart, Ursache, Ergebnis, Nacharbeit, Kulanzgrund.
- `MVP-031`: Kategorien pro Organisation pflegbar machen.
- `MVP-032`: Pflichtklassifikationen pro Auftragstyp, Gewerk und Objekt-/Raumtyp ermöglichen.
- `MVP-033`: Branchenprofil `IT-Service` als erstes Referenzprofil anlegen, inkl. Erfassung von Kundenrechnern und Netzwerkkomponenten pro Raum.
- `MVP-034`: Branchenprofile `Facility Management` und `Gebäudereinigung` als Referenzprofile anlegen; `Handwerk/Service allgemein` bleibt Basisprofil für gewerkeübergreifende Servicefälle.

Definition of Done:

- Auswertungen basieren nicht nur auf Freitext.
- Profile liefern Startwerte für Auftragstypen, Raum-/Objekttypen,
  Pflichtfelder und Protokolle, sind aber kundenspezifisch anpassbar.
- Unterschiedliche Gewerke können für denselben Raum unterschiedliche
  Anforderungen definieren, z. B. Sonderreinigung, technische Prüfung oder
  IT-Inventarisierung.

### Epic 3.2: Objektakte, Assets und Dienstmittel

Quellen:
[009](./009-inventar-dienstmittel-assets.md),
[027](./027-produkt-objektakte-lebenszyklus.md)

Issues:

- `MVP-035`: Asset-/Objekt-Stammdaten minimal modellieren: Typ, Name, Seriennummer, Gebäude/Bereich/Raum, Kunde, Status.
- `MVP-036`: Asset/Objekt mit Auftrag, Protokoll, Material und Anhang verknüpfen.
- `MVP-037`: Objekt-Timeline anzeigen.
- `MVP-038`: Defekt-/gesperrt-Status sichtbar machen.

Definition of Done:

- Ein Objekt oder Dienstmittel hat eine Historie.
- Gebäude, Bereiche und Räume können als Objekte geführt oder referenziert
  werden.
- Kundenrechner, Reinigungsbereiche, technische Anlagen und Dienstmittel lassen
  sich einem Raum oder Gebäudebereich zuordnen.
- Wiederkehrende Probleme können später objektbezogen ausgewertet werden.

### Epic 3.3: Management-Auswertungen MVP

Quellen:
[002](./002-auswertungen-entscheidungsgrundlagen.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md)

Issues:

- `MVP-039`: Kundenanalyse: Zeit, Anzahl Aufträge, Nacharbeit, offene Punkte, nicht abrechenbare Zeit.
- `MVP-040`: Auftragstyp- und Gewerkeanalyse: Plan/Ist, Durchschnittsdauer, Nacharbeit.
- `MVP-041`: Produkt-/Objekt-/Raumanalyse: wiederkehrende Fehlerarten, Sonderreinigungen, offene Punkte und betroffene Assets.
- `MVP-042`: Drill-down von Kennzahl zu Aufträgen sicherstellen.
- `MVP-043`: CSV/PDF-Export für MVP-Reports definieren.

Definition of Done:

- Jede Kennzahl ist bis zum Auftrag nachvollziehbar.
- Auswertungen unterscheiden abrechenbare, interne, Reise- und Nacharbeitszeit.
- Zeitraum-, Kunde-, Objekt-/Raum-, Gewerk-, Auftragstyp- und Statusfilter sind
  vorhanden.

## Phase 4: Betrieb und Einführung

### Epic 4.1: Lokale Installation und Supportfähigkeit

Quellen:
[017](./017-backup-restore-disaster-recovery.md),
[021](./021-tarife-lizenzportal-abrechnung.md),
[041](./041-support-fehlerdiagnose-kundeninstallationen.md)

Issues:

- `MVP-044`: Diagnose-Seite für Version, Lizenz, Queue, Scheduler, Mail, Storage, Backupstatus konzipieren.
- `MVP-045`: Supportbericht ohne fachliche Kundendaten exportieren.
- `MVP-046`: Backup-Hinweise und Restore-Anleitung für lokale Installation dokumentieren.
- `MVP-047`: Lizenzstatus und Feature-Flags in Admin-Oberfläche anzeigen.

Definition of Done:

- Kundenadmins erkennen häufige Betriebsprobleme.
- Support kann ohne fachliche Kundendaten erste Diagnose durchführen.
- Lokale Installation hat klare Betriebsdokumentation.

### Epic 4.2: Onboarding und Demo

Quellen:
[020](./020-import-migration-onboarding.md),
[040](./040-demo-testdaten-musterbranchen.md),
[039](./039-hilfe-dokumentation-in-app.md)

Issues:

- `MVP-048`: Onboarding-Checkliste für neue Organisationen erstellen.
- `MVP-049`: CSV-Import für Kunden, Projekte, Nutzer, Materialien minimalisieren.
- `MVP-050`: Demo-Mandant mit vollständigem Beispielauftrag erstellen.
- `MVP-051`: In-App-Hilfe für Zeiterfassung, Auftrag, Protokoll, Prozedur und Auswertung ergänzen.
- `MVP-052`: Lizenzierte Module organisationsbezogen aktivieren/deaktivieren und
  die Oberfläche auf tatsächlich genutzte Module reduzieren.

Definition of Done:

- Neuer Mandant kann ohne Entwicklerhilfe initial befüllt werden.
- Demo zeigt den kompletten Nachweisfluss von Auftrag bis Auswertung.
- Kundenadmins können den lizenzierten Funktionsumfang ohne Datenverlust auf die
  tatsächlich benötigten Module reduzieren.

## Phase 5: Fertigung und Montage

### Epic 5.1: Dynamische Arbeitspläne und Fertigungsaufträge

Quellen:
[047](./047-fertigungs-montage-arbeitsauftraege.md),
[026](./026-prozeduren-arbeitsanweisungen-checklisten.md),
[009](./009-inventar-dienstmittel-assets.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md)

Issues:

- `MVP-060`: Einheitlichen Artikelstamm mit bestandsführenden Varianten, strukturierten Optionswerten, Nummernhoheit, Lebenszyklus, Preisen, Beschaffungsarten, Basiseinheiten und externen Zuordnungen organisationsbezogen modellieren.
- `MVP-061`: Versionierte, vererbbare Stücklisten, Rezepturen, Auftragsparameter und Anleitungsmedien an Arbeitsplan-Versionen ergänzen.
- `MVP-062`: Fertigungs-/Montageauftrag mit Statusmaschine, Varianten-/Parameter-Snapshot und Materialbedarfsberechnung anlegen.
- `MVP-063`: Ausführbare mobile Prozedurlauf-Ansicht einschließlich bedingter Schritte und Medien umsetzen.
- `MVP-064`: Serverseitige Warte-/Trockenschritte mit blockierter Fortsetzung implementieren.
- `MVP-065`: Teilrückmeldungen, Ist-Material, Gutmenge, Ausschuss, Nacharbeit und Fertigungsnachweis erfassen.

Definition of Done:

- Ein freigegebener Auftrag friert Arbeitsplan-Version und Materialbedarf ein.
- Rezepturen und stückbezogene Bedarfe werden reproduzierbar berechnet.
- Mitarbeitende können den Auftrag mobil Schritt für Schritt ausführen.
- Wartezeiten, Abweichungen, Materialverbrauch und Fertigungsergebnis sind
  serverseitig nachvollziehbar.
- Vollständige Lagerwirtschaft, MRP und Chargenrückverfolgung bleiben außerhalb
  dieses ersten Blocks.

### Epic 5.2: Lagerwirtschaft und Bestandsprovider

Quellen:
[048](./048-lagerwirtschaft-bestandsintegration.md),
[008](./008-integrationen-api.md),
[047](./047-fertigungs-montage-arbeitsauftraege.md),
[009](./009-inventar-dienstmittel-assets.md)

Issues:

- `MVP-066`: Bestandsführerschaft, Provider-Vertrag und Capability-Matrix organisationsbezogen definieren.
- `MVP-067`: Lokale Lagerorte, Bestandszustände, Eigentumsarten und append-only Lagerbewegungsjournal umsetzen.
- `MVP-068`: Verfügbarkeit, Reservierungen, Mindestbestände und Fehlmaterialprozess ergänzen.
- `MVP-069`: Wareneingang, Entnahme, Rückgabe, Umlagerung und stichtagsbezogene Inventur umsetzen.
- `MVP-070`: Kostensnapshots und gleitende Durchschnittsbewertung ergänzen.
- `MVP-071`: Fertigungs-/Montageaufträge mit Teilrückmeldungen, Reservierung, Verbrauch, Ausschuss und Einlagerung verbinden.
- `MVP-072`: Persistierte Outbox, Idempotenz, Retry, Konflikte und Kompensationsbuchungen für externe Provider umsetzen.
- `MVP-073`: Optionales JTL-Wawi-Plugin gegen den Provider-Vertrag pilotieren.
- `MVP-074`: Fertigerzeugnisse ausliefern, Bestand abbuchen und als konkrete Variante an das führende Fakturasystem übergeben.

Definition of Done:

- Eine Organisation kann Lexoffice für Artikel/Faktura und WorkDiary für
  Lagerbestände verwenden.
- Pro Organisation ist genau ein Bestandsprovider aktiv.
- Bestände ändern sich ausschließlich über nachvollziehbare Bewegungen.
- Artikel, Varianten, Einheiten und externe Referenzen bilden keinen
  konkurrierenden Parallelstamm.
- Bestandszustände und Eigentumsarten werden bei Verfügbarkeit und Verbrauch
  berücksichtigt.
- Fehlmaterial und Ersatzmaterial folgen einem geregelten Freigabeprozess.
- Reservierungszeitpunkt und Beschaffungsweg sind pro Auftrag eindeutig.
- Reservierung, Ist-Verbrauch und Restfreigabe sind getrennt und
  reproduzierbar.
- Bestätigte Lagerbewegungen werden durch Gegenbuchung statt Änderung
  korrigiert.
- Teilfertigungen und historische Kosten bleiben je Rückmeldung
  nachvollziehbar.
- Inventurdifferenzen beziehen sich auf einen eindeutigen Zählzeitpunkt.
- Auslieferung und Fakturaübergabe bleiben getrennt nachvollziehbar.
- Externe Provider-Buchungen sind per Outbox idempotent und zeigen Fehler offen
  an.

## Phase 6: GAEB, Leistungsverzeichnisse und Baukalkulation

### Epic 6.1: Leistungsverzeichnis und GAEB-Austausch

Quellen:
[049](./049-gaeb-leistungsverzeichnisse.md),
[042](./042-gewerke-branchenprofile.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md),
[020](./020-import-migration-onboarding.md),
[008](./008-integrationen-api.md)

Issues:

- `MVP-080`: GAEB-Modul fachlich lizenzierbar schneiden und Datenführerschaft
  für Leistungsverzeichnis, Preise, Aufmaß und Rechnung organisationsbezogen
  definieren.
- `MVP-081`: GAEB DA XML Import-Preflight für Leistungsverzeichnisse mit
  Version, Austauschphase, Strukturprüfung und Fehlerprotokoll konzipieren.
- `MVP-082`: Leistungsverzeichnis-Datenmodell mit Abschnitten,
  Ordnungszahlen, Positionen, Texten, Mengen, Einheiten, Preis-Snapshots und
  Nachtragskennzeichen umsetzen.
- `MVP-083`: LV-Positionen mit Projekt, Auftrag, Protokoll, Aufmaß,
  Materialverbrauch und Nachkalkulation verknüpfen.
- `MVP-084`: Bau-/Ausbauprofil um LV-Workflows für Ausschreibung,
  Angebotsbearbeitung, Aufmaß, Nachtrag und Restleistung erweitern.
- `MVP-085`: GAEB-Export für freigegebene LV-Stände, Angebote,
  Auftrag/Nachtrag oder Abrechnungsübergabe mit Audit und Wiederholungsschutz
  ergänzen.
- `MVP-086`: GAEB-Beispieldaten und Demo-Ablauf für Bau/Ausbau bereitstellen:
  Import, Ausführung, Aufmaß, Nachtrag, Nachkalkulation und Export.

Definition of Done:

- GAEB-Dateien werden vor dem Import validiert und mit Fehlerprotokoll
  abgelehnt oder kontrolliert übernommen.
- Ordnungszahlen, LV-Hierarchie, Texte, Mengen, Einheiten und Preisdaten
  bleiben als Snapshots nachvollziehbar.
- Aufträge, Protokolle, Aufmaß und Materialverbrauch können auf konkrete
  LV-Positionen verweisen.
- Nachträge sind eigene nachvollziehbare Vorgänge und keine stillen
  Freitextänderungen.
- Reimporte überschreiben keine Positionen mit Ausführungs- oder
  Abrechnungsbezug ohne sichtbaren Konfliktstand.
- GAEB-Exporte sind versioniert, auditierbar und wiederholbar.

## Phase 7: Lieferantenkataloge, Shopimport und Margen

### Epic 7.1: Katalogimport, Preisabgleich und Warenkorbübernahme

Quellen:
[050](./050-lieferantenkataloge-shopimport-preisabgleich.md),
[048](./048-lagerwirtschaft-bestandsintegration.md),
[008](./008-integrationen-api.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md),
[020](./020-import-migration-onboarding.md)

Issues:

- `MVP-090`: Lieferantenkatalog- und Shopimport-Modul lizenzierbar schneiden
  und Datenführerschaft für Artikel, Einkaufspreise, Verkaufspreise und Margen
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

Definition of Done:

- Externe Shop- und Katalogartikel werden importiert, ohne den internen
  Artikelstamm ungeprüft zu überschreiben.
- Artikelnummern, GTIN, Herstellerdaten, Einkaufspreise, Verfügbarkeiten und
  Lieferzeiten bleiben als Lieferanten-Snapshots historisiert.
- `shopinfo.xml`-Mappings und FTP-/SFTP-Listen werden als Katalogquellen
  unterstützt, aber vor Übernahme gegen Header, Pflichtfelder und erlaubte
  Hosts validiert.
- Preisänderungen erzeugen sichtbare Abgleichs- und Margenwarnungen für offene
  Angebote, Aufträge, LV-Positionen und Bestellungen.
- Verkaufspreisvorschläge folgen nachvollziehbaren Margenregeln und werden
  erst nach Freigabe übernommen.
- Externe Warenkörbe werden als Beschaffungsvorschlag oder Bestellung
  übernommen und mit internen Artikeln/Bezugsquellen verknüpft.

## Querschnitt: Tests und Qualität

Die Sicherheitsprüfung aus `MVP-097` bis `MVP-101` ist ein
verbindliches Release-Gate. Sie läuft auf dem finalen Release-Kandidaten nach
Abschluss der fachlichen MVP-Features und vor dem produktiven Rollout.

Jeder MVP-Issue sollte mindestens eine dieser Prüfungen enthalten:

- Feature-Test für Rechte und Mandantengrenzen.
- Unit-Test für Berechnungs- oder Statuslogik.
- Browser-/View-Test für kritische Formulare, falls sinnvoll.
- Export-/PDF-Test für Nachweisdokumente.
- Regressionstest für bestehende Zeiterfassung, Anhänge und Reports.

## Reihenfolge

1. Phase 0 zuerst: Mandant, Datenschutz, UX-Konventionen und Vorbereitung der
   Sicherheits-Kontrollmatrix.
2. Phase 1 danach: Auftragsverlauf und Zeiterfassung.
3. Phase 2: Protokolle und Prozeduren.
4. Phase 3: Klassifikationen, Assets, Auswertungen.
5. Phase 4: Betrieb, Onboarding, Demo.
6. Phase 5: dynamische Arbeitspläne, Fertigungs-/Montageaufträge und
   Lagerintegration.
7. Phase 6: GAEB-Leistungsverzeichnisse für Bau-/Ausbauprojekte, sobald
   Branchenprofil, Importbasis und Nachkalkulation stabil genug sind.
8. Phase 7: Lieferantenkataloge, Shopimport und Preisabgleich, sobald
   Artikelstamm, Beschaffung und Preis-Snapshots tragfähig sind.
9. Abschließend `MVP-097` bis `MVP-101` auf dem eingefrorenen
   Release-Kandidaten durchführen; ohne Security-Freigabe kein produktiver
   Rollout.

## GitHub-Umsetzung

Empfohlene Labels:

- `feature`
- `mvp`
- `security`
- `tenant`
- `timekeeping`
- `documentation`
- `reporting`
- `ux`
- `ops`
- `construction`
- `procurement`

Empfohlene Meilensteine:

- `MVP Phase 0 - Fundament`
- `MVP Phase 1 - Aufzeichnung und Zeit`
- `MVP Phase 2 - Dokumentation und Prozeduren`
- `MVP Phase 3 - Auswertungen und Stammdaten`
- `MVP Phase 4 - Betrieb und Onboarding`
- `MVP Phase 5 - Fertigung und Montage`
- `MVP Phase 6 - GAEB und Leistungsverzeichnisse`
- `MVP Phase 7 - Lieferantenkataloge und Preisabgleich`
