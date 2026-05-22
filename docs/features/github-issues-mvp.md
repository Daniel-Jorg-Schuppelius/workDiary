# GitHub-Issue-Backlog MVP

Dieser Backlog ist aus [000 Umsetzungsplan MVP](./000-umsetzungsplan-mvp.md)
abgeleitet. Er ist als Vorlage für GitHub Issues gedacht, falls die Issues
nicht direkt über den GitHub-Connector angelegt werden können.

## Labels

- `feature`
- `mvp`
- `security`
- `tenant`
- `timekeeping`
- `documentation`
- `reporting`
- `ux`
- `ops`

## Milestones

- `MVP Phase 0 - Fundament`
- `MVP Phase 1 - Aufzeichnung und Zeit`
- `MVP Phase 2 - Dokumentation und Prozeduren`
- `MVP Phase 3 - Auswertungen und Stammdaten`
- `MVP Phase 4 - Betrieb und Onboarding`

## Phase 0 - Fundament

| ID | Titel | Labels | Quellen |
| --- | --- | --- | --- |
| MVP-001 | Mandantensicherheits-Audit aller Kernmodelle durchführen | `mvp`, `security`, `tenant` | [015](./015-mandantenfaehigkeit-betriebsmodelle.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md) |
| MVP-002 | Exporte, Suche, Anhänge, Kalenderfeeds und API auf Mandantengrenzen prüfen | `mvp`, `security`, `tenant` | [015](./015-mandantenfaehigkeit-betriebsmodelle.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md) |
| MVP-003 | Rollenprofile für Systemadmin, Kundenadmin, Teamleitung, Außendienst und Buchhaltung dokumentieren und seedbar machen | `mvp`, `security` | [019](./019-rollen-rechte-produktprofile.md) |
| MVP-004 | Supportzugriff-Grundsätze dokumentieren und Auditpunkte festlegen | `mvp`, `security`, `ops` | [016](./016-datenschutz-dsgvo-datenlebenszyklus.md), [041](./041-support-fehlerdiagnose-kundeninstallationen.md) |
| MVP-005 | Datenschutzseite für Admins konzipieren | `mvp`, `security`, `tenant` | [016](./016-datenschutz-dsgvo-datenlebenszyklus.md) |
| MVP-006 | UX-Pattern-Katalog für Listen, Filter, Detailseiten, Modale, Status, Anhänge und Kommentare erstellen | `mvp`, `ux` | [037](./037-einheitliche-bedienung-ux-konventionen.md) |
| MVP-007 | Bestehendes UI-Audit um neue Roadmap-Module erweitern | `mvp`, `ux` | [037](./037-einheitliche-bedienung-ux-konventionen.md), [ui-audit](../ui-unification-audit.md) |
| MVP-008 | Accessibility-Checkliste für neue Seiten definieren | `mvp`, `ux` | [038](./038-barrierefreiheit-zugaenglichkeit.md) |
| MVP-009 | Einheitliche Status- und Aktionsnamen festlegen | `mvp`, `ux` | [037](./037-einheitliche-bedienung-ux-konventionen.md) |

Definition of Done:

- Kernobjekte respektieren Mandantengrenzen.
- Sensible Datenbereiche sind durch Rechte geschützt.
- Neue Features haben UI- und Accessibility-Checklisten.

## Phase 1 - Aufzeichnung und Zeit

| ID | Titel | Labels | Quellen |
| --- | --- | --- | --- |
| MVP-010 | Auftragsverlauf als Timeline definieren | `mvp`, `feature` | [001](./001-zeiterfassung-kernprodukt.md), [023](./023-suche-timeline-fallakte.md) |
| MVP-011 | Auftrag/DiaryEntry um strukturierte Annahme-, Bearbeitungs- und Abschlussereignisse erweitern | `mvp`, `feature` | [001](./001-zeiterfassung-kernprodukt.md) |
| MVP-012 | Kommunikationsnotizen an Auftrag, Kunde und Projekt hängen | `mvp`, `feature` | [030](./030-kommunikationsprotokoll.md) |
| MVP-013 | Detailansicht für Auftrag als Fallakte konzipieren | `mvp`, `feature`, `ux` | [023](./023-suche-timeline-fallakte.md) |
| MVP-014 | Globale Suche auf Auftrag, Kunde, Projekt, Kommentar und Anhang-Metadaten prüfen | `mvp`, `feature` | [023](./023-suche-timeline-fallakte.md) |
| MVP-015 | Tagesabschluss-Ansicht definieren | `mvp`, `timekeeping`, `ux` | [001](./001-zeiterfassung-kernprodukt.md) |
| MVP-016 | Monatsfreigabe-Datenmodell skizzieren | `mvp`, `timekeeping` | [001](./001-zeiterfassung-kernprodukt.md), [005](./005-lohn-zuschlaege-datev-lexware.md) |
| MVP-017 | Korrekturanträge für Zeitdaten fachlich und technisch schneiden | `mvp`, `timekeeping`, `security` | [001](./001-zeiterfassung-kernprodukt.md), [006](./006-compliance-korrekturen-audit.md) |
| MVP-018 | Plan/Ist-Abgleich für Anwesenheit, Projektzeit und Schicht vorbereiten | `mvp`, `timekeeping`, `reporting` | [001](./001-zeiterfassung-kernprodukt.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md) |
| MVP-019 | Exportgrundlage für geprüfte Zeiten definieren | `mvp`, `timekeeping`, `reporting` | [005](./005-lohn-zuschlaege-datev-lexware.md) |

Definition of Done:

- Ein Auftrag ist als Verlauf nachvollziehbar.
- Mitarbeitende erkennen offene oder unstimmige Tage.
- Nachträgliche Zeitänderungen sind begründet und nachvollziehbar.

## Phase 2 - Dokumentation und Prozeduren

| ID | Titel | Labels | Quellen |
| --- | --- | --- | --- |
| MVP-020 | Protokoll-Datenmodell entwerfen | `mvp`, `documentation`, `feature` | [003](./003-dokumentation-abnahmeprotokolle.md) |
| MVP-021 | Protokollpunkt-Typen definieren | `mvp`, `documentation` | [003](./003-dokumentation-abnahmeprotokolle.md), [032](./032-vorlagen-formularsystem.md) |
| MVP-022 | Abnahme mit Unterschrift, Zeitstempel und PDF-Ausgabe umsetzen | `mvp`, `documentation` | [003](./003-dokumentation-abnahmeprotokolle.md), [012](./012-kundenportal-freigaben.md) |
| MVP-023 | Vorher-/Nachher-Fotos strukturiert am Protokollpunkt speichern | `mvp`, `documentation` | [003](./003-dokumentation-abnahmeprotokolle.md) |
| MVP-024 | Offene Punkte mit Verantwortlichkeit, Frist und Status ergänzen | `mvp`, `documentation` | [003](./003-dokumentation-abnahmeprotokolle.md) |
| MVP-025 | Prozedurvorlagen mit Version, Anwendungsbereich und Schritten modellieren | `mvp`, `documentation` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-026 | Pflichtschritte und Reihenfolge technisch erzwingen | `mvp`, `documentation` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-027 | Nachweistyp Backup für Update-/Change-Prozeduren definieren | `mvp`, `documentation` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-028 | Zweite Person/Freigeber pro Schritt sichtbar machen | `mvp`, `documentation`, `security` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md), [013](./013-qualitaet-sicherheit-arbeitsschutz.md) |
| MVP-029 | Abweichungen mit Begründung und Folgeaktion speichern | `mvp`, `documentation`, `reporting` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |

Definition of Done:

- Ein abgeschlossener Auftrag kann als Protokoll exportiert werden.
- Kritische Prozedurschritte können nicht unbemerkt übersprungen werden.
- Vier-Augen-Schritte sind sichtbar und nachvollziehbar.

## Phase 3 - Auswertungen und Stammdaten

| ID | Titel | Labels | Quellen |
| --- | --- | --- | --- |
| MVP-030 | Kernklassifikationen definieren | `mvp`, `reporting` | [024](./024-klassifikationen-tags-datenqualitaet.md) |
| MVP-031 | Kategorien pro Organisation pflegbar machen | `mvp`, `tenant`, `reporting` | [024](./024-klassifikationen-tags-datenqualitaet.md) |
| MVP-032 | Pflichtklassifikationen pro Auftragstyp ermöglichen | `mvp`, `reporting` | [024](./024-klassifikationen-tags-datenqualitaet.md), [042](./042-gewerke-branchenprofile.md) |
| MVP-033 | Branchenprofil IT-Service als erstes Referenzprofil anlegen | `mvp`, `feature` | [042](./042-gewerke-branchenprofile.md) |
| MVP-034 | Branchenprofil Handwerk/Service allgemein als zweites Referenzprofil anlegen | `mvp`, `feature` | [042](./042-gewerke-branchenprofile.md) |
| MVP-035 | Asset-/Objekt-Stammdaten minimal modellieren | `mvp`, `feature` | [009](./009-inventar-dienstmittel-assets.md), [027](./027-produkt-objektakte-lebenszyklus.md) |
| MVP-036 | Asset/Objekt mit Auftrag, Protokoll, Material und Anhang verknüpfen | `mvp`, `feature` | [009](./009-inventar-dienstmittel-assets.md), [027](./027-produkt-objektakte-lebenszyklus.md) |
| MVP-037 | Objekt-Timeline anzeigen | `mvp`, `feature`, `ux` | [027](./027-produkt-objektakte-lebenszyklus.md), [023](./023-suche-timeline-fallakte.md) |
| MVP-038 | Defekt-/gesperrt-Status sichtbar machen | `mvp`, `feature` | [009](./009-inventar-dienstmittel-assets.md) |
| MVP-039 | Kundenanalyse für Aufwand, Nacharbeit und offene Punkte erstellen | `mvp`, `reporting` | [002](./002-auswertungen-entscheidungsgrundlagen.md) |
| MVP-040 | Auftragstypanalyse für Plan/Ist, Durchschnittsdauer und Nacharbeit erstellen | `mvp`, `reporting` | [002](./002-auswertungen-entscheidungsgrundlagen.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md) |
| MVP-041 | Produkt-/Objektanalyse für wiederkehrende Fehlerarten und offene Punkte erstellen | `mvp`, `reporting` | [002](./002-auswertungen-entscheidungsgrundlagen.md), [027](./027-produkt-objektakte-lebenszyklus.md) |
| MVP-042 | Drill-down von Kennzahl zu Aufträgen sicherstellen | `mvp`, `reporting`, `ux` | [002](./002-auswertungen-entscheidungsgrundlagen.md) |
| MVP-043 | CSV/PDF-Export für MVP-Reports definieren | `mvp`, `reporting` | [002](./002-auswertungen-entscheidungsgrundlagen.md) |

Definition of Done:

- Auswertungen basieren auf strukturierten Kategorien.
- Ein Objekt oder Dienstmittel hat eine Historie.
- Jede Kennzahl ist bis zum Auftrag nachvollziehbar.

## Phase 4 - Betrieb und Onboarding

| ID | Titel | Labels | Quellen |
| --- | --- | --- | --- |
| MVP-044 | Diagnose-Seite für Version, Lizenz, Queue, Scheduler, Mail, Storage und Backupstatus konzipieren | `mvp`, `ops` | [041](./041-support-fehlerdiagnose-kundeninstallationen.md) |
| MVP-045 | Supportbericht ohne fachliche Kundendaten exportieren | `mvp`, `ops`, `security` | [041](./041-support-fehlerdiagnose-kundeninstallationen.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md) |
| MVP-046 | Backup-Hinweise und Restore-Anleitung für lokale Installation dokumentieren | `mvp`, `ops` | [017](./017-backup-restore-disaster-recovery.md) |
| MVP-047 | Lizenzstatus und Feature-Flags in Admin-Oberfläche anzeigen | `mvp`, `ops` | [021](./021-tarife-lizenzportal-abrechnung.md) |
| MVP-048 | Onboarding-Checkliste für neue Organisationen erstellen | `mvp`, `ux` | [020](./020-import-migration-onboarding.md), [039](./039-hilfe-dokumentation-in-app.md) |
| MVP-049 | CSV-Import für Kunden, Projekte, Nutzer und Materialien minimalisieren | `mvp`, `feature` | [020](./020-import-migration-onboarding.md) |
| MVP-050 | Demo-Mandant mit vollständigem Beispielauftrag erstellen | `mvp`, `feature` | [040](./040-demo-testdaten-musterbranchen.md) |
| MVP-051 | In-App-Hilfe für Zeiterfassung, Auftrag, Protokoll, Prozedur und Auswertung ergänzen | `mvp`, `ux` | [039](./039-hilfe-dokumentation-in-app.md) |

Definition of Done:

- Kundenadmins erkennen häufige Betriebsprobleme.
- Support kann ohne fachliche Kundendaten erste Diagnose durchführen.
- Neuer Mandant kann initial befüllt werden.
- Demo zeigt den kompletten Nachweisfluss von Auftrag bis Auswertung.
