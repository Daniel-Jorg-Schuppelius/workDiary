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
- `production`

## Milestones

- `MVP Phase 0 - Fundament`
- `MVP Phase 1 - Aufzeichnung und Zeit`
- `MVP Phase 2 - Dokumentation und Prozeduren`
- `MVP Phase 3 - Auswertungen und Stammdaten`
- `MVP Phase 4 - Betrieb und Onboarding`
- `MVP Phase 5 - Fertigung und Montage`

## Phase 0 - Fundament

| ID      | Titel                                                                                                                 | Labels                      | Quellen                                                                                                          |
| ------- | --------------------------------------------------------------------------------------------------------------------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| MVP-001 | Mandantensicherheits-Audit aller Kernmodelle durchführen                                                              | `mvp`, `security`, `tenant` | [015](./015-mandantenfaehigkeit-betriebsmodelle.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md)         |
| MVP-002 | Exporte, Suche, Anhänge, Kalenderfeeds und API auf Mandantengrenzen prüfen                                            | `mvp`, `security`, `tenant` | [015](./015-mandantenfaehigkeit-betriebsmodelle.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md)         |
| MVP-003 | Rollenprofile für Systemadmin, Kundenadmin, Teamleitung, Außendienst und Buchhaltung dokumentieren und seedbar machen | `mvp`, `security`           | [019](./019-rollen-rechte-produktprofile.md)                                                                     |
| MVP-004 | Supportzugriff-Grundsätze dokumentieren und Auditpunkte festlegen                                                     | `mvp`, `security`, `ops`    | [016](./016-datenschutz-dsgvo-datenlebenszyklus.md), [041](./041-support-fehlerdiagnose-kundeninstallationen.md) |
| MVP-005 | Datenschutzseite für Admins konzipieren                                                                               | `mvp`, `security`, `tenant` | [016](./016-datenschutz-dsgvo-datenlebenszyklus.md)                                                              |
| MVP-006 | UX-Pattern-Katalog für Listen, Filter, Detailseiten, Modale, Status, Anhänge und Kommentare erstellen                 | `mvp`, `ux`                 | [037](./037-einheitliche-bedienung-ux-konventionen.md)                                                           |
| MVP-007 | Bestehendes UI-Audit um neue Roadmap-Module erweitern                                                                 | `mvp`, `ux`                 | [037](./037-einheitliche-bedienung-ux-konventionen.md), [ui-audit](../ui-unification-audit.md)                   |
| MVP-008 | Accessibility-Checkliste für neue Seiten definieren                                                                   | `mvp`, `ux`                 | [038](./038-barrierefreiheit-zugaenglichkeit.md)                                                                 |
| MVP-009 | Einheitliche Status- und Aktionsnamen festlegen                                                                       | `mvp`, `ux`                 | [037](./037-einheitliche-bedienung-ux-konventionen.md)                                                           |

Definition of Done:

- Kernobjekte respektieren Mandantengrenzen.
- Sensible Datenbereiche sind durch Rechte geschützt.
- Neue Features haben UI- und Accessibility-Checklisten.

## Phase 1 - Aufzeichnung und Zeit

| ID      | Titel                                                                                         | Labels                            | Quellen                                                                                       |
| ------- | --------------------------------------------------------------------------------------------- | --------------------------------- | --------------------------------------------------------------------------------------------- |
| MVP-010 | Auftragsverlauf als Timeline definieren                                                       | `mvp`, `feature`                  | [001](./001-zeiterfassung-kernprodukt.md), [023](./023-suche-timeline-fallakte.md)            |
| MVP-011 | Auftrag/DiaryEntry um strukturierte Annahme-, Bearbeitungs- und Abschlussereignisse erweitern | `mvp`, `feature`                  | [001](./001-zeiterfassung-kernprodukt.md)                                                     |
| MVP-012 | Kommunikationsnotizen an Auftrag, Kunde und Projekt hängen                                    | `mvp`, `feature`                  | [030](./030-kommunikationsprotokoll.md)                                                       |
| MVP-013 | Detailansicht für Auftrag als Fallakte konzipieren                                            | `mvp`, `feature`, `ux`            | [023](./023-suche-timeline-fallakte.md)                                                       |
| MVP-014 | Globale Suche auf Auftrag, Kunde, Projekt, Kommentar und Anhang-Metadaten prüfen              | `mvp`, `feature`                  | [023](./023-suche-timeline-fallakte.md)                                                       |
| MVP-015 | Tagesabschluss-Ansicht definieren                                                             | `mvp`, `timekeeping`, `ux`        | [001](./001-zeiterfassung-kernprodukt.md)                                                     |
| MVP-016 | Monatsfreigabe-Datenmodell skizzieren                                                         | `mvp`, `timekeeping`              | [001](./001-zeiterfassung-kernprodukt.md), [005](./005-lohn-zuschlaege-datev-lexware.md)      |
| MVP-017 | Korrekturanträge für Zeitdaten fachlich und technisch schneiden                               | `mvp`, `timekeeping`, `security`  | [001](./001-zeiterfassung-kernprodukt.md), [006](./006-compliance-korrekturen-audit.md)       |
| MVP-018 | Plan/Ist-Abgleich für Anwesenheit, Projektzeit und Schicht vorbereiten                        | `mvp`, `timekeeping`, `reporting` | [001](./001-zeiterfassung-kernprodukt.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md) |
| MVP-019 | Exportgrundlage für geprüfte Zeiten definieren                                                | `mvp`, `timekeeping`, `reporting` | [005](./005-lohn-zuschlaege-datev-lexware.md)                                                 |

Definition of Done:

- Ein Auftrag ist als Verlauf nachvollziehbar.
- Mitarbeitende erkennen offene oder unstimmige Tage.
- Nachträgliche Zeitänderungen sind begründet und nachvollziehbar.

## Phase 2 - Dokumentation und Prozeduren

| ID      | Titel                                                                     | Labels                              | Quellen                                                                                                       |
| ------- | ------------------------------------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| MVP-020 | Protokoll-Datenmodell entwerfen                                           | `mvp`, `documentation`, `feature`   | [003](./003-dokumentation-abnahmeprotokolle.md)                                                               |
| MVP-021 | Protokollpunkt-Typen definieren                                           | `mvp`, `documentation`              | [003](./003-dokumentation-abnahmeprotokolle.md), [032](./032-vorlagen-formularsystem.md)                      |
| MVP-022 | Abnahme mit Unterschrift, Zeitstempel und PDF-Ausgabe umsetzen            | `mvp`, `documentation`              | [003](./003-dokumentation-abnahmeprotokolle.md), [012](./012-kundenportal-freigaben.md)                       |
| MVP-023 | Vorher-/Nachher-Fotos strukturiert am Protokollpunkt speichern            | `mvp`, `documentation`              | [003](./003-dokumentation-abnahmeprotokolle.md)                                                               |
| MVP-024 | Offene Punkte mit Verantwortlichkeit, Frist und Status ergänzen           | `mvp`, `documentation`              | [003](./003-dokumentation-abnahmeprotokolle.md)                                                               |
| MVP-025 | Prozedurvorlagen mit Version, Anwendungsbereich und Schritten modellieren | `mvp`, `documentation`              | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md)                                                     |
| MVP-026 | Pflichtschritte und Reihenfolge technisch erzwingen                       | `mvp`, `documentation`              | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md)                                                     |
| MVP-027 | Nachweistyp Backup für Update-/Change-Prozeduren definieren               | `mvp`, `documentation`              | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md)                                                     |
| MVP-028 | Zweite Person/Freigeber pro Schritt sichtbar machen                       | `mvp`, `documentation`, `security`  | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md), [013](./013-qualitaet-sicherheit-arbeitsschutz.md) |
| MVP-029 | Abweichungen mit Begründung und Folgeaktion speichern                     | `mvp`, `documentation`, `reporting` | [026](./026-prozeduren-arbeitsanweisungen-checklisten.md)                                                     |

Definition of Done:

- Ein abgeschlossener Auftrag kann als Protokoll exportiert werden.
- Kritische Prozedurschritte können nicht unbemerkt übersprungen werden.
- Vier-Augen-Schritte sind sichtbar und nachvollziehbar.

## Phase 3 - Auswertungen und Stammdaten

| ID      | Titel                                                                                       | Labels                       | Quellen                                                                                                  |
| ------- | ------------------------------------------------------------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------------------- |
| MVP-030 | Kernklassifikationen definieren                                                             | `mvp`, `reporting`           | [024](./024-klassifikationen-tags-datenqualitaet.md)                                                     |
| MVP-031 | Kategorien pro Organisation pflegbar machen                                                 | `mvp`, `tenant`, `reporting` | [024](./024-klassifikationen-tags-datenqualitaet.md)                                                     |
| MVP-032 | Pflichtklassifikationen pro Auftragstyp, Gewerk und Objekt-/Raumtyp ermöglichen             | `mvp`, `reporting`           | [024](./024-klassifikationen-tags-datenqualitaet.md), [042](./042-gewerke-branchenprofile.md)            |
| MVP-033 | Branchenprofil IT-Service inkl. Raumbezug für Kundenrechner und Netzwerkkomponenten anlegen | `mvp`, `feature`             | [042](./042-gewerke-branchenprofile.md)                                                                  |
| MVP-034 | Branchenprofile Facility Management und Gebäudereinigung als Referenzprofile anlegen        | `mvp`, `feature`             | [042](./042-gewerke-branchenprofile.md)                                                                  |
| MVP-035 | Asset-/Objekt-Stammdaten inkl. Gebäude, Bereich und Raum minimal modellieren                | `mvp`, `feature`             | [009](./009-inventar-dienstmittel-assets.md), [027](./027-produkt-objektakte-lebenszyklus.md)            |
| MVP-036 | Asset/Objekt mit Auftrag, Protokoll, Material und Anhang verknüpfen                         | `mvp`, `feature`             | [009](./009-inventar-dienstmittel-assets.md), [027](./027-produkt-objektakte-lebenszyklus.md)            |
| MVP-037 | Objekt-Timeline anzeigen                                                                    | `mvp`, `feature`, `ux`       | [027](./027-produkt-objektakte-lebenszyklus.md), [023](./023-suche-timeline-fallakte.md)                 |
| MVP-038 | Defekt-/gesperrt-Status sichtbar machen                                                     | `mvp`, `feature`             | [009](./009-inventar-dienstmittel-assets.md)                                                             |
| MVP-039 | Kundenanalyse für Aufwand, Nacharbeit und offene Punkte erstellen                           | `mvp`, `reporting`           | [002](./002-auswertungen-entscheidungsgrundlagen.md)                                                     |
| MVP-040 | Auftragstyp- und Gewerkeanalyse für Plan/Ist, Durchschnittsdauer und Nacharbeit erstellen   | `mvp`, `reporting`           | [002](./002-auswertungen-entscheidungsgrundlagen.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md) |
| MVP-041 | Produkt-/Objekt-/Raumanalyse für Fehlerarten, Sonderreinigungen und offene Punkte erstellen | `mvp`, `reporting`           | [002](./002-auswertungen-entscheidungsgrundlagen.md), [027](./027-produkt-objektakte-lebenszyklus.md)    |
| MVP-042 | Drill-down von Kennzahl zu Aufträgen sicherstellen                                          | `mvp`, `reporting`, `ux`     | [002](./002-auswertungen-entscheidungsgrundlagen.md)                                                     |
| MVP-043 | CSV/PDF-Export für MVP-Reports definieren                                                   | `mvp`, `reporting`           | [002](./002-auswertungen-entscheidungsgrundlagen.md)                                                     |

Definition of Done:

- Auswertungen basieren auf strukturierten Kategorien.
- Ein Objekt oder Dienstmittel hat eine Historie.
- Gebäude, Bereiche und Räume können als fachlicher Kontext für Gewerke,
  Assets, Protokolle und Sonderanforderungen genutzt werden.
- Jede Kennzahl ist bis zum Auftrag nachvollziehbar.

## Phase 4 - Betrieb und Onboarding

| ID      | Titel                                                                                            | Labels                   | Quellen                                                                                                          |
| ------- | ------------------------------------------------------------------------------------------------ | ------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| MVP-044 | Diagnose-Seite für Version, Lizenz, Queue, Scheduler, Mail, Storage und Backupstatus konzipieren | `mvp`, `ops`             | [041](./041-support-fehlerdiagnose-kundeninstallationen.md)                                                      |
| MVP-045 | Supportbericht ohne fachliche Kundendaten exportieren                                            | `mvp`, `ops`, `security` | [041](./041-support-fehlerdiagnose-kundeninstallationen.md), [016](./016-datenschutz-dsgvo-datenlebenszyklus.md) |
| MVP-046 | Backup-Hinweise und Restore-Anleitung für lokale Installation dokumentieren                      | `mvp`, `ops`             | [017](./017-backup-restore-disaster-recovery.md)                                                                 |
| MVP-047 | Lizenzstatus und Feature-Flags in Admin-Oberfläche anzeigen                                      | `mvp`, `ops`             | [021](./021-tarife-lizenzportal-abrechnung.md)                                                                   |
| MVP-048 | Onboarding-Checkliste für neue Organisationen erstellen                                          | `mvp`, `ux`              | [020](./020-import-migration-onboarding.md), [039](./039-hilfe-dokumentation-in-app.md)                          |
| MVP-049 | CSV-Import für Kunden, Projekte, Nutzer und Materialien minimalisieren                           | `mvp`, `feature`         | [020](./020-import-migration-onboarding.md)                                                                      |
| MVP-050 | Demo-Mandant mit vollständigem Beispielauftrag erstellen                                         | `mvp`, `feature`         | [040](./040-demo-testdaten-musterbranchen.md)                                                                    |
| MVP-051 | In-App-Hilfe für Zeiterfassung, Auftrag, Protokoll, Prozedur und Auswertung ergänzen             | `mvp`, `ux`              | [039](./039-hilfe-dokumentation-in-app.md)                                                                       |
| MVP-052 | Lizenzierte Module organisationsbezogen aktivieren/deaktivieren und Oberfläche reduzieren         | `mvp`, `ops`, `ux`       | [021](./021-tarife-lizenzportal-abrechnung.md)                                                                   |

Definition of Done:

- Kundenadmins erkennen häufige Betriebsprobleme.
- Support kann ohne fachliche Kundendaten erste Diagnose durchführen.
- Neuer Mandant kann initial befüllt werden.
- Demo zeigt den kompletten Nachweisfluss von Auftrag bis Auswertung.
- Organisationen sehen nur die lizenzierten Module, die sie tatsächlich
  verwenden möchten.

## Phase 5 - Fertigung und Montage

| ID      | Titel                                                                                                     | Labels                                     | Quellen                                                                                                        |
| ------- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------ | -------------------------------------------------------------------------------------------------------------- |
| MVP-060 | Einheitlichen Artikelstamm mit Varianten, Nummernhoheit, Lebenszyklus, Preisen, Beschaffungsarten, Einheiten und externen Zuordnungen modellieren | `mvp`, `feature`, `production` | [047](./047-fertigungs-montage-arbeitsauftraege.md), [048](./048-lagerwirtschaft-bestandsintegration.md) |
| MVP-061 | Versionierte, vererbbare Stücklisten, Rezepturen, Auftragsparameter und Anleitungsmedien ergänzen          | `mvp`, `documentation`, `production`       | [047](./047-fertigungs-montage-arbeitsauftraege.md), [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-062 | Fertigungs-/Montageauftrag mit Statusmaschine, Varianten-/Parameter-Snapshot und Materialbedarfsberechnung anlegen | `mvp`, `feature`, `production`        | [047](./047-fertigungs-montage-arbeitsauftraege.md)                                                            |
| MVP-063 | Ausführbare mobile Prozedurlauf-Ansicht einschließlich bedingter Schritte und Medien umsetzen             | `mvp`, `ux`, `documentation`, `production` | [047](./047-fertigungs-montage-arbeitsauftraege.md), [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-064 | Serverseitige Warte-/Trockenschritte mit blockierter Fortsetzung implementieren                           | `mvp`, `feature`, `production`             | [047](./047-fertigungs-montage-arbeitsauftraege.md), [026](./026-prozeduren-arbeitsanweisungen-checklisten.md) |
| MVP-065 | Teilrückmeldungen, Ist-Material, Gutmenge, Ausschuss, Nacharbeit und Fertigungsnachweis erfassen           | `mvp`, `reporting`, `production`           | [047](./047-fertigungs-montage-arbeitsauftraege.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md)        |
| MVP-066 | Bestandsführerschaft, Provider-Vertrag und Capability-Matrix organisationsbezogen definieren             | `mvp`, `feature`, `production`             | [048](./048-lagerwirtschaft-bestandsintegration.md), [008](./008-integrationen-api.md)                         |
| MVP-067 | Lokale Lagerorte, Bestandszustände, Eigentumsarten und append-only Lagerbewegungsjournal umsetzen         | `mvp`, `feature`, `production`             | [048](./048-lagerwirtschaft-bestandsintegration.md)                                                           |
| MVP-068 | Verfügbarkeit, Reservierungen, Mindestbestände und Fehlmaterialprozess ergänzen                            | `mvp`, `feature`, `production`             | [048](./048-lagerwirtschaft-bestandsintegration.md)                                                           |
| MVP-069 | Wareneingang, Entnahme, Rückgabe, Umlagerung und stichtagsbezogene Inventur umsetzen                       | `mvp`, `feature`, `production`             | [048](./048-lagerwirtschaft-bestandsintegration.md)                                                           |
| MVP-070 | Kostensnapshots und gleitende Durchschnittsbewertung ergänzen                                              | `mvp`, `reporting`, `production`           | [048](./048-lagerwirtschaft-bestandsintegration.md), [014](./014-nachkalkulation-wirtschaftlichkeit.md)        |
| MVP-071 | Fertigungs-/Montageaufträge mit Teilrückmeldungen, Reservierung, Verbrauch, Ausschuss und Einlagerung verbinden | `mvp`, `feature`, `production`          | [048](./048-lagerwirtschaft-bestandsintegration.md), [047](./047-fertigungs-montage-arbeitsauftraege.md)      |
| MVP-072 | Persistierte Outbox, Idempotenz, Retry, Konflikte und Kompensationsbuchungen für externe Provider umsetzen | `mvp`, `feature`, `production`            | [048](./048-lagerwirtschaft-bestandsintegration.md), [008](./008-integrationen-api.md)                         |
| MVP-073 | Optionales JTL-Wawi-Plugin gegen den Provider-Vertrag pilotieren                                          | `mvp`, `feature`, `production`             | [048](./048-lagerwirtschaft-bestandsintegration.md), [008](./008-integrationen-api.md)                         |
| MVP-074 | Fertigerzeugnisse ausliefern, Bestand abbuchen und als konkrete Variante an das führende Fakturasystem übergeben | `mvp`, `feature`, `production`        | [047](./047-fertigungs-montage-arbeitsauftraege.md), [048](./048-lagerwirtschaft-bestandsintegration.md), [045](./045-datev-finanzschnittstelle.md) |

Definition of Done:

- Arbeitsplan-Version und Materialbedarf werden beim Freigeben eingefroren.
- Rezepturen und Sollmengen sind reproduzierbar und einheitensicher.
- Wartezeiten und blockierende Schritte werden serverseitig erzwungen.
- Ist-Verbrauch und Ergebnis sind bis zum konkreten Auftrag nachvollziehbar.
- Artikel, Varianten, Einheiten und externe Zuordnungen bilden einen
  einheitlichen Stamm.
- Fehlmaterial, Eigentumsarten, Inventur und historische Kosten sind fachlich
  nachvollziehbar.
- Varianten und freie Auftragsparameter bleiben getrennt; Stücklisten werden
  vererbt und als Gesamtstand eingefroren.
- Bestätigte Lagerbewegungen werden nur durch Gegenbuchungen korrigiert.
- Lexoffice kann Artikel/Faktura führen, während WorkDiary Bestände lokal
  führt.
- Auslieferung, Bestandsabbuchung und Fakturaübergabe besitzen getrennte
  Status.
- Ein externer Bestandsprovider kann denselben Fachablauf übernehmen, ohne
  Fertigungslogik in das Plugin zu verlagern.
