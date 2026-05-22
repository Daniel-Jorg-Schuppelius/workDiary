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

## MVP-Scope

### Muss in Version 1

- Mandantensichere Aufzeichnung von Aufträgen, Zeiten, Anhängen, Kommentaren
  und Statuswechseln.
- Tages- und Monatsabschluss für Arbeitszeiten.
- Protokoll-/Dokumentationsgrundlage mit Fotos, Checklisten und Unterschrift.
- Prozeduren mit Pflichtschritten, z. B. Backup vor Update.
- Grundauswertungen für Kunde, Auftrag, Zeit, Material und Nacharbeit.
- Datenschutzgrundsätze sichtbar und technisch unterstützt.
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
- `MVP-032`: Pflichtklassifikationen pro Auftragstyp ermöglichen.
- `MVP-033`: Branchenprofil `IT-Service` als erstes Referenzprofil anlegen.
- `MVP-034`: Branchenprofil `Handwerk/Service allgemein` als zweites Referenzprofil anlegen.

Definition of Done:

- Auswertungen basieren nicht nur auf Freitext.
- Profile liefern Startwerte, sind aber kundenspezifisch anpassbar.

### Epic 3.2: Objektakte, Assets und Dienstmittel

Quellen:
[009](./009-inventar-dienstmittel-assets.md),
[027](./027-produkt-objektakte-lebenszyklus.md)

Issues:

- `MVP-035`: Asset-/Objekt-Stammdaten minimal modellieren: Typ, Name, Seriennummer, Standort, Kunde, Status.
- `MVP-036`: Asset/Objekt mit Auftrag, Protokoll, Material und Anhang verknüpfen.
- `MVP-037`: Objekt-Timeline anzeigen.
- `MVP-038`: Defekt-/gesperrt-Status sichtbar machen.

Definition of Done:

- Ein Objekt oder Dienstmittel hat eine Historie.
- Wiederkehrende Probleme können später objektbezogen ausgewertet werden.

### Epic 3.3: Management-Auswertungen MVP

Quellen:
[002](./002-auswertungen-entscheidungsgrundlagen.md),
[014](./014-nachkalkulation-wirtschaftlichkeit.md)

Issues:

- `MVP-039`: Kundenanalyse: Zeit, Anzahl Aufträge, Nacharbeit, offene Punkte, nicht abrechenbare Zeit.
- `MVP-040`: Auftragstypanalyse: Plan/Ist, Durchschnittsdauer, Nacharbeit.
- `MVP-041`: Produkt-/Objektanalyse: wiederkehrende Fehlerarten und offene Punkte.
- `MVP-042`: Drill-down von Kennzahl zu Aufträgen sicherstellen.
- `MVP-043`: CSV/PDF-Export für MVP-Reports definieren.

Definition of Done:

- Jede Kennzahl ist bis zum Auftrag nachvollziehbar.
- Auswertungen unterscheiden abrechenbare, interne, Reise- und Nacharbeitszeit.
- Zeitraum-, Kunde-, Auftragstyp- und Statusfilter sind vorhanden.

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

Definition of Done:

- Neuer Mandant kann ohne Entwicklerhilfe initial befüllt werden.
- Demo zeigt den kompletten Nachweisfluss von Auftrag bis Auswertung.

## Querschnitt: Tests und Qualität

Jeder MVP-Issue sollte mindestens eine dieser Prüfungen enthalten:

- Feature-Test für Rechte und Mandantengrenzen.
- Unit-Test für Berechnungs- oder Statuslogik.
- Browser-/View-Test für kritische Formulare, falls sinnvoll.
- Export-/PDF-Test für Nachweisdokumente.
- Regressionstest für bestehende Zeiterfassung, Anhänge und Reports.

## Reihenfolge

1. Phase 0 zuerst: Mandant, Datenschutz, UX-Konventionen.
2. Phase 1 danach: Auftragsverlauf und Zeiterfassung.
3. Phase 2: Protokolle und Prozeduren.
4. Phase 3: Klassifikationen, Assets, Auswertungen.
5. Phase 4: Betrieb, Onboarding, Demo.

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

Empfohlene Meilensteine:

- `MVP Phase 0 - Fundament`
- `MVP Phase 1 - Aufzeichnung und Zeit`
- `MVP Phase 2 - Dokumentation und Prozeduren`
- `MVP Phase 3 - Auswertungen und Stammdaten`
- `MVP Phase 4 - Betrieb und Onboarding`
