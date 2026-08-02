# Changelog

Alle nennenswerten Änderungen an WorkDiary werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/)
(siehe `release-prozess.md` im Doku-Repository WorkDiary-Architecture).

## [Unreleased]

### Added

- Kunden-Sonderkonditionen (Feature 098): **Monatsdetail in der Verwaltung**.
  Der Monat im Abrechnungspanel ist jetzt verlinkt und zeigt dieselben Zeilen
  wie Kundenportal und PDF-Nachweis (Datum, Tätigkeit, Von/Bis, Dauer,
  Anfahrt, Satz, Betrag) plus Tätigkeits-Summen und die Zahlungen des Monats —
  damit ist ohne Umweg über das Portal prüfbar, wie einzelne Zeiten bewertet
  wurden. Zugriff wie beim Panel über das Bearbeiten-Recht am Kunden.
- Kunden-Sonderkonditionen (Feature 098): **Anfahrtspauschale je Zeiteintrag**.
  In der Konditionsmaske lassen sich x Minuten Anfahrt hinterlegen, wahlweise
  nur für ausgewählte Tätigkeiten; sie werden mit dem Satz des Eintrags
  bewertet (Werktag/Wochenende gelten also automatisch mit). Die **erfasste
  Arbeitszeit bleibt unverändert** — die Anfahrt ist eine Preisregel und
  erhöht nur den Erlös, nicht Arbeitszeitkonto, Gleitzeit oder interne
  Kosten; es entsteht bewusst kein fiktiver Zeiteintrag. Kontoauszug, PDF-
  Nachweis und Kundenportal weisen sie in einer eigenen Spalte aus, die
  Rechnungsstellung zählt sie in die abgerechnete Menge (Menge × Satz bleibt
  der Betrag). Keine Anfahrt bei Fahrt-/Bereitschaftszeiten, Festpreis-
  Einträgen oder nicht abrechenbaren Zeiten; im Zeiterfassungs-Dialog je
  Eintrag übersteuerbar (auch auf 0). Neu ist außerdem ein Schalter
  **„Feiertage wie Wochenende abrechnen"** (Standard aus, damit sich
  Bestandsdaten nicht rückwirkend ändern) auf Basis des vorhandenen
  Feiertagskalenders der Organisation.

- DATEV-/Finanzschnittstelle (Feature 045) – Härtung & Nachweis: **Write→Read-
  Validierung** des DATEV-Buchungsstapels (die erzeugte EXTF-V700-CSV wird mit
  `php-financial-formats` über einen unabhängigen Codepfad wieder eingelesen;
  Formaterkennung, Version und Buchungszeilen-Anzahl werden geprüft —
  `finalize()` bricht bei Abweichung ab und legt keine Datei an, Format/Version
  landen im revisionssicheren `finalized`-Event). **Materialpositions-Snapshot**
  vollständig (`billing_transfer_items.unit/unit_price/tax_rate/cost_position`,
  Teil des Payload-Hashs, in der Übergabe-Vorschau sichtbar). DATEV-Vorschau
  zeigt **Formatversion + Roundtrip-Badge** sowie einen Hinweis auf
  **abgeleitete/vereinfachte Felder**. **Capability-Matrix** der
  Import-/Exportformate dokumentiert (inhaltsbasierte Erkennung, nicht per
  Dateiendung). Desktop-API-gebundene Punkte (Rechnungsnummer-Sync, Zugangsdaten-
  Hygiene, vollständiges Mandant-/Auftrag-Mapping) bleiben bewusst offen.
- Kundenportal & Freigaben (Feature 012): **Freigabe/Ablehnung mit offenen
  Punkten** und **Rückfrage-Funktion** für Kunden — additiv auf dem bestehenden
  Protokoll-Signaturlink (`ProtocolSignatureToken` / `PublicProtocolSignatureController`)
  aufgesetzt, **ohne** den Auth-/2FA-Teil des Portals zu berühren. Der Kunde
  kann ein vorgelegtes Protokoll/Abnahme über den (zeitlich begrenzten,
  einmalig nutzbaren) Token **freigeben** (Unterschrift wie bisher) **oder
  ablehnen**: Die Ablehnung verlangt eine **Pflicht-Begründung** und erfasst je
  gemeldetem Mangel einen **Offenen Punkt** (`OpenIssue`, neue Quelle
  `customerRejection`, Sichtbarkeit `customer`, am betroffenen Auftrag/Protokoll
  mit `source_ref_id`); Freigabe wie Ablehnung werden revisionssicher als
  `ProtocolEvent` (`signatureRejected`) mit Entscheidung, Zeitstempel, Token und
  IP protokolliert (neue Spalten `protocol_signature_tokens.decision/
  decision_reason/decided_at`). Neue **Kunden-Rückfrage** (`customer_queries`,
  polymorphes Subjekt, Frage/Antwort/Status): der Kunde stellt über denselben
  Link eine Freitext-Frage, die Org wird über das neue Ereignis
  `customer.queryRaised` (Default an Teamleitung) benachrichtigt, beantwortet
  sie intern auf der neuen Seite **Kunden-Rückfragen** (Permission
  `protocol.customerQuery.manage`, eigene Policy + NavGate-Mapping), und der
  Kunde sieht die Antwort über den Link. Sichtbarkeit strikt am Token-Vorgang
  (kein Zugriff auf fremde/interne Daten, Negativtest); abgelaufene/benutzte
  Tokens werden mit HTTP 410 abgewiesen. Hilfe-Topic `customer.queries` (de/en),
  i18n (de/en/fr/it/es) inkl. öffentlicher Portal-Texte, neue Tests.

- Tarife, Lizenzportal & Abrechnung (Feature 021): **Nutzerlimit-Durchsetzung**
  und **SaaS-Mandantenstatus**. Das in der Lizenz hinterlegte `max_users` wird
  beim Anlegen neuer Mitglieder über die Org-Admin-Oberfläche
  (`OrgMemberController@store`) durchgesetzt — der `LimitGuard` wertet jetzt die
  **org-gebundene** Lizenz (`organizations.license_key`, sonst globale Lizenz)
  aus und zählt die **aktiven Nutzer der Organisation** gegen das Limit; bei
  Erreichen wird die Anlage mit klarer Meldung „Nutzerlimit (X/Y) der aktuellen
  Lizenz erreicht. Bitte Lizenz erweitern." blockiert (HTTP 423 / Flash-Error)
  und ein `limit.exceeded`-Audit-Eintrag geschrieben; unbegrenzte/Enterprise-
  Lizenzen ohne `max_users` werden nicht begrenzt. Neuer **Mandantenstatus**
  (`TenantStatus`: trial/active/suspended/expired) je Organisation: explizit über
  die neue nullable Spalte `organizations.tenant_status` setzbar (Plattform-Admin,
  Permission `platform.license.install`, Audit `tenant.statusChanged`) oder sonst
  aus Testphase (`trial_ends_at`), Aktiv-Flag und Lizenz-Ablauf inkl. Grace-Period
  **abgeleitet** (gültig/in Kulanz/abgelaufen). Bei **gesperrtem oder endgültig
  abgelaufenem** Mandanten sperrt die neue Middleware `EnforceTenantStatus`
  **schreibende** Aktionen (HTTP 423), Lesen sowie Lizenz-/Logout-Routen bleiben
  erreichbar (Sperre aufhebbar) — der Auth-/2FA-/Signatur-Kern bleibt
  unangetastet. Die `admin/license`-Seite zeigt zusätzlich eine
  **Mandantenstatus-Karte** (Status-Badge, Ablauf-Warnung < 30 Tage,
  org-bezogene Nutzungsanzeige X/Y) samt Umschaltung. Hilfe-Topic `admin.license`
  (de/en) ergänzt, i18n (de/en/fr/it/es), neue Tests
  (`UserLimitEnforcementTest`, `TenantStatusTest`). Das **Online-Lizenzportal**
  (externe Selbstausstellung) bleibt bewusst offen.
- Datenschutz, Sicherheit & Datenlebenszyklus (Feature 016): Neue
  **Admin-Sicherheitsseite** (`/admin/security`, Permission `security.view`,
  Admin-only wie `metrics.view`) als **read-only Aggregation**
  sicherheitsrelevanter Zustände — **aktive Sessions** (nur beim
  `database`-Treiber; sonst wird der Treiber ehrlich als „keine Übersicht
  möglich" ausgewiesen; niemals der Session-`payload`), **API-Tokens** (nur
  Metadaten: Name, Abilities, `last_used_at`/`expires_at`/`created_at` —
  **niemals** Token-Wert oder -Hash), **aktive externe Integrationen**
  (aktivierte Plugins + Anzahl externer Referenzen, ohne verschlüsselte
  Plugin-Settings), **letzte Daten-/Zeit-Exporte** (ExportRun/TimeExport, nur
  Metadaten), **letzte Supportzugriffe** (Audit-Log, Ereignis-Präfix
  `support.`) sowie **2FA-Abdeckung** (reine Zählung bestätigter Faktoren) und
  **at-rest-Verschlüsselungs-Status** (APP_KEY-Hinweis + betroffene
  PII-Felder). Mandantentrennung über den vorhandenen `OrganizationScope` bzw.
  explizite Org-User-Filterung; globaler Plattform-Admin sieht plattformweit.
  Neuer `SecurityOverviewService` (read-only), `Admin\SecurityController`,
  Hilfe-Topic `admin.security` (de/en erweitert), i18n (de/en/fr/it/es).
  Die automatisierten **Lösch-/Aufbewahrungsläufe** bleiben bewusst offen
  (Feature 016, „Später").
- Support & Fehlerdiagnose (Feature 041): Der **Supportbericht** wurde um eine
  **Health-Zusammenfassung** und eine **reine JSON-Variante** erweitert. Der
  `SupportReportBuilder` ergänzt nun einen **`release`-Block** (App-Version,
  **Build-Hash**, PHP-/Laravel-/**DB-Version**, aktive Module + Plugins aus dem
  signaturfreien `ReleaseManifestService`-Kern), einen **`health`-Block** aus
  dem vorhandenen Befehl **`system:health --json`** (DB, Migrationen, Storage,
  Queue, APP_KEY, Mail, Lizenz, Backup — über den neuen `SupportHealthSummary`,
  **ohne** den DiagnosticsService zu duplizieren), einen **`plugin_errors`-Block**
  der letzten 7 Tage **(nur Plugin-ID/Phase/Anzahl, keine Meldungen/Payloads)**
  sowie einen **`operations`-Block** (Queue-Stand, letzte Backup-Heartbeats —
  nur Counts/Metadaten). Neue Admin-Aktionen **„Als JSON-Datei herunterladen"**
  (`support-report-{date}.json`) und **„Im Browser anzeigen"** sowie ein
  Artisan-Befehl **`support:report {--output=}`** für CLI/On-Premise. Strikter
  **Whitelist-Ansatz**: der Bericht enthält ausschließlich explizit erlaubte,
  technische Felder — niemals Kundennamen, personenbezogene Daten, Secrets oder
  Klartext-Zugangsdaten (Negativtests sichern Kunden-/`APP_KEY`-Freiheit ab).
  Jede Erzeugung wird im Audit-Log protokolliert. Hilfe-Topic `admin.support`
  (de/en) und i18n (de/en/fr/it/es) ergänzt.
- Demo-, Testdaten & Musterbranchen (Feature 040): Der **`DemoSeederService`**
  erzeugt jetzt je **Musterbranche** ein realistisches **End-to-End-Szenario**
  und setzt dabei auf den bestehenden **`BranchProfileInstaller`** auf (das
  passende Branchenprofil – Klassifikationen, Tags, SLAs, Prozeduren – wird
  mitinstalliert). Neben Kunden, Projekten, Demo-Nutzern und 25 Hintergrund-
  Aufträgen (jetzt in **gemischten Stati**) enthält der Hauptauftrag nun auch
  **Material** (über einen Stundenzettel mit `MaterialUsage`), ein **Asset**,
  ein **signiertes Abnahmeprotokoll** mit Prüfpunkten, einen **offenen Punkt**
  und einen **Kommunikationseintrag** – damit sind Zeiterfassung, Auswertungen,
  Protokolle und Fallakte mit echten Daten erlebbar. Neue **`DemoIndustry`**
  (IT-Service, Elektro, Facility Management) liefert erkennbar unterschiedliche,
  generische Demo-Inhalte (keine echten Firmen/Personen). Neue Artisan-Befehle
  **`demo:seed {org?} --industry=`** und **`demo:reset {org?} --all`** sowie eine
  **Branchen-Auswahl** in der Admin-Aktion „Demo-Mandant". Der **resetbare
  Demo-Modus** ist idempotent und **wasserdicht geschützt**: Reset wirkt
  ausschließlich auf Organisationen mit `is_demo = true` – echte Mandanten
  werden niemals angefasst (Service wirft für nicht-Demo-Orgs eine Ausnahme,
  der Befehl überspringt sie). Der Seeder bindet während des Laufs den
  `currentOrganization`-Kontext korrekt, sodass der Multi-Tenant-Scope auch in
  Konsolen-/Mehr-Org-Läufen sauber greift. i18n (de/en/fr/it/es) ergänzt.
- Import, Migration & Onboarding (Feature 020 / MVP-049): Der bestehende
  **CSV-Import-Wizard** wurde auf **Fahrzeuge** ausgeweitet und das
  **Material**-Importprofil bestätigt/dokumentiert. Beide nutzen exakt das
  vorhandene Muster (Spec-Registry `EntitySpecRegistry`, `CsvPreflightAnalyzer`,
  `ProcessCsvImportJob`, `ImportRun`/`ImportRunError`, Vorschau, Bestätigung,
  Fehler-Download) — keine parallele Import-Mechanik. Die neue `VehicleSpec`
  mappt Kennzeichen, Bezeichnung, Fahrzeugtyp/Antrieb/Eigentum (Enum-validiert),
  Kilometerstand sowie Tank-/Akku-/Verbrauchs-/Kilometersatz-Felder; Idempotenz
  per `(organization_id, license_plate)`, Preflight erkennt fehlende Kennzeichen
  und ungültige Enum-Werte. Neue Permission `vehicle.import` (Admin, in der
  bestehenden Import-Berechtigung). Entitäts-Auswahl des Wizards um „Fahrzeuge"
  ergänzt, i18n (de/en/fr/it/es), `docs/csv-import.md` um Vehicle/Material-Spalten
  aktualisiert. Die **Legacy-Migration** vorhandener WorkDiary-/Tagebuchdaten
  bleibt bewusst ein eigenständiger Folgeschritt.
- Auswertungen & Entscheidungsgrundlagen (Feature 002): **Zielwerte &
  Benchmarks** und **Kohortenvergleich vor/nach Fortbildung** schließen die
  zwei offenen MVP-Punkte. Zielwerte werden in einer neuen Tabelle
  `report_targets` je Kennzahl (Deckungsbeitrags-Marge, abrechenbare Quote,
  Nacharbeitsanteil, SLA-Einhaltungsquote, Auslastung) und Bezugsebene
  (Organisation/Kunde/Projekt/Mitarbeitende, optional mit Gültigkeitszeitraum)
  hinterlegt – Pflege unter **Admin → Zielwerte** (neue Permission
  `report.target.manage`, Geschäftsführung/Admin). Der `ReportTargetEvaluator`
  blendet Soll/Ist samt Ampel-Abweichung **additiv** in bestehende Reports ein:
  Deckungsbeitrags-Marge (org-weit und je Kunde) im **Wirtschaftlichkeits-
  Report** und Einhaltungsquote im **SLA-Report** – ausschließlich gegen bereits
  berechnete Kennzahlen, ohne neue Kennzahlen-Engine. Der neue
  **Kohortenvergleich** (`reports.cohort-comparison`) bildet je Qualifikation
  die Kohorte und vergleicht eine Kennzahl (abrechenbare Quote / Nacharbeits-
  anteil) im gleichlangen Fenster **vor vs. nach dem Erwerbsdatum**, je
  Mitarbeitendem und aggregiert; Datenquelle für das Erwerbsdatum ist
  `user_qualifications.valid_from`, Personen ohne Erwerbsdatum werden ehrlich
  gesondert ausgewiesen. Die Kennzahlen stammen aus denselben TimeEntry-Feldern
  wie die Wirtschaftlichkeitssicht. CSV-Export des Kohortenvergleichs, neues
  Hilfe-Topic `reports.cohort-comparison`, vollständige i18n (de/en/fr/it/es).
- Gewerke- und Branchenprofile (Feature 042, MVP): **Importierbare
  Vorlagenpakete je Gewerk**. Der `BranchProfileInstaller` legt – zusätzlich zu
  den bisherigen Auftragsarten/Kategorien (Klassifikationen), Pflichtregeln,
  Tags, Wartungsplan-, SLA- und Reinigungsprofil-Vorlagen sowie dem
  Softwarekatalog – nun auch **veröffentlichte Checklisten/Prozedurvorlagen**
  (Feature 026, mit Schritten, Zweite-Person- und Nachweispflicht) sowie
  organisationsweite **Raumanforderungs-Vorlagen** (neue Tabelle
  `room_requirement_templates`, Feature 027) an. Sechs Gewerke mit erkennbar
  erweitertem Paket: Elektro (Sicherheitscheck/E-Check), SHK
  (Wartung/Druckprüfung), Gebäudereinigung (QS/Sonderreinigung), Facility
  Management (Objektkontrolle/Schlüssel), IT-Service (Netzwerk-Change/
  Backup-Restore-Test) und GaLaBau (Baumpflege/Abnahme). Der Admin-Katalog
  (`admin.branch-profiles`) zeigt eine **Inhaltsvorschau** je Paket (Anzahl
  Auftragsarten, Kategorien, Pflichtregeln, Checklisten, Raumanforderungen,
  Tags sowie eine Liste enthaltener Auftragsarten und Checklisten) und eine
  **bestätigte** Installations-Aktion. Installation strikt **idempotent**:
  erneutes Installieren erzeugt keine Dubletten, lokal angepasste Org-Daten
  bleiben unberührt und veröffentlichte Checklisten werden nie überschrieben
  (auch nicht bei „Erneut anwenden“). Pakete sind weiterhin rein deklarativ als
  Config (`database/data/branchprofiles/*.php`) hinterlegt; neue Gewerke ohne
  Code-Änderung ergänzbar. Neues Hilfe-Topic `admin.branch-profiles`.
- Produkt-/Objektakte und Lebenszyklus (Feature 027, MVP): **Objektakte /
  Lebenszyklus-Dossier** je Asset (`assets.dossier`) als zusammenhängende,
  druckbare Read-Only-Gesamtsicht – Pendant zur Auftrags-Fallakte
  (`diary/case-file`, Standalone-HTML mit Print-CSS, `?print=1` öffnet den
  Druckdialog). Kopf mit Stammdaten, Standort/Raum, Status/Zustand,
  Inbetriebnahme/Außerbetriebnahme und Garantie; darunter Wartungen,
  Ausgaben/Rückgaben, Defekte/Sperren, Aufträge, Protokolle, Materialeinsatz,
  offene Punkte, Anhänge und die vollständige Lebenszyklus-Timeline. Wiederverwendet
  den bestehenden `AssetTimelineService` (additiv um Ausgabe/Rückgabe, Defekte und
  durchgeführte Wartungen erweitert) sowie die Feature-009-Modelle
  `AssetAssignment`/`AssetDefect`/`MaintenancePlan` – keine parallele
  Timeline-Mechanik. Neuer `AssetLifecycleService` leitet den **Lebenszyklus-Status**
  (in Betrieb / ersetzt / stillgelegt) aus `status` + `decommissioned_on` ab (keine
  neue Statusmaschine) und zeigt ihn im Kopf der Asset-Seite und der Objektakte.
  **Raumbezogene Anforderungen** je Gewerk über eigene 1:n-Tabelle
  `room_requirements` (kind/level/note: Hygienestufe, Sonderreinigung,
  Zugangsbeschränkung, IT-Inventar, technische Prüfung, Betreiberpflicht) –
  ergänzend zum Reinigungsprofil, gepflegt im Raum-Dialog
  (`rooms.requirements.*`, abgesichert über die bestehende Raum-Permission),
  sichtbar in Raumliste, Asset-Detailseite und Objektakte. Objektakte =
  `asset.view`; Hilfetopics `assets.fleet` und `facilities.manage` erweitert.

- Externe Beteiligte, Subunternehmer und Prüfer (Feature 033, MVP):
  **kontextbezogene, befristete externe Einladungen** zu Auftrag, Protokoll oder
  Dokument (`external_participants`, morphes Subject). Pro Einladung ein
  **login-freier, tokenisierter Zugang** (`/extern/{token}`, gedrosselt) auf eine
  schlanke, datensparsame Read-Only-Seite des Subjects mit nur den per
  **`abilities`** (ansehen | kommentieren | hochladen | bestätigen) erlaubten
  Aktionen. Token-Muster strikt analog `ProtocolSignatureToken` /
  `IsmsAuditPackageToken`: es wird nur der **SHA-256-Hash** gespeichert, der
  Klartext-Link wird genau **einmal** angezeigt; abgelaufene, widerrufene oder
  unbekannte Tokens antworten einheitlich 404. Die `abilities` werden
  **serverseitig je Aktion streng durchgesetzt** (view-only ⇒ 403 bei
  Upload/Bestätigung). **Jede externe Aktion** (Zugriff, Kommentar, Upload,
  Bestätigung) wird append-only in `external_participant_events` nachgewiesen
  (Akteur = externer Name/Token, kein interner User). Interne Verwaltung über das
  Panel „Externe Beteiligte" auf der Auftragsdetailseite (Einladen via Modal,
  Einmal-Link, Statusliste, Widerruf); neue Permission
  `externalParticipant.manage` (admin, teamleitung sowie der
  Auftragsverantwortliche über die Subject-Update-Policy). Plattform-Hilfetopic
  `external.participants`.

- ISMS / ISO 27001 (Feature 044, MVP 2/3): **Lieferantenbewertung**
  (`isms.suppliers`, Tabelle `isms_supplier_assessments`) – Kritikalitäts- und
  Risikoeinstufung von Lieferanten, geforderte Sicherheitsanforderungen,
  Vertragsmerkmale (Geheimhaltung/AVV/Prüfungsrecht), wiederkehrende Reviews und
  eine Statusmaschine (Entwurf → bewertet → freigegeben bzw. auffällig). Der
  Lieferantenbezug ist optional (loser FK auf das bestehende Lieferanten-
  Stammdatenmodell oder Freitext-Name); der AVV-Bezug zum Datenschutzmanagement
  bleibt bewusst lose (Flag + Freitext, kein FK). Überfällige Reviews speisen die
  Dashboard-Kennzahl „ungeprüfte Lieferanten" und werden über den Fristen-Scanner
  (`isms.supplierReviewOverdue`) gemeldet/eskaliert. Berechtigungen über die
  bestehenden `isms.*`-Rechte; Plan-Gating `module.isms`.
- ISMS / ISO 27001 (Feature 044, MVP 3): **Reifegrad-/Readiness-Assessment**
  (`isms.readiness`) – begründete Selbsteinschätzung der internen Auditbereitschaft
  je Geltungsbereich. Leitet aus den vorhandenen Registern (SoA-Abdeckung, offene
  hohe Risiken, überfällige/unbewertete Reviews, Nachweislücken, offene
  Nichtkonformitäten/überfällige Korrekturmaßnahmen, kritische Vorfälle/ausnutzbare
  Schwachstellen, ungeprüfte Lieferanten) einen Reifegrad je Domäne (Ampel +
  Score) und daraus eine Gesamteinschätzung „intern auditbereit?" mit Begründung
  ab. Ausdrücklich eine Selbsteinschätzung/Empfehlung – nie eine automatische
  Konformitätsbehauptung oder „zertifiziert" (prominenter Disclaimer).
- Compliance/Audit (Feature 006, MVP): **ArbZG-Compliance-Auswertung auf der
  Ist-Arbeitszeit** (`reports.arbzg-compliance`). Prüft je Mitarbeiter und Tag die
  tatsächlich erfasste Arbeitszeit (Attendance, netto nach Pausen) gegen die
  ArbZG-Schwellen und listet Verstöße auf: Tageshöchstarbeitszeit (> 10 h Netto),
  Ruhezeit (< 11 h zwischen zwei Arbeitstagen), Pflichtpause (ArbZG §4: 30 min ab
  6 h, 45 min ab 9 h) sowie als Hinweis die Wochenhöchstarbeitszeit (> Ø 48 h). Die
  Schwellen werden aus dem Bestand wiederverwendet (Organisations-Compliance-
  Einstellungen wie bei der Dienstplan-Prüfung, Pausenregeln wie im Tagesabschluss)
  – keine abweichenden Zahlen. Verstöße werden on-the-fly berechnet (die
  zugrunde liegenden Anwesenheiten sind über die Audit-Hash-Kette revisionssicher),
  mit Filter nach Verstoßart, Summen je Art, Drill-down zum Tagesabschluss sowie
  CSV-/PDF-Export. Liegt für einen Tag eine genehmigte Zeitkorrektur
  (`TimeCorrectionRequest`) vor, wird der Eintrag als „korrigiert“ markiert. Neue
  Permission `compliance.viewAny` (Admin, Teamleitung, Buchhaltung); Plan-Gating wie
  bei den übrigen Team-Auswertungen.
- Dienstplan-Intelligenz (Feature 007, MVP): **Verfügbarkeiten & Wunschdienste**
  als Self-Service (`schedule.availability.index`) – wiederkehrende oder
  datumsbezogene Verfügbarkeitsfenster (verfügbar/nicht verfügbar/bevorzugt) und
  Wunschdienste (Wunsch/Abneigung je Datum und optionalem Schichttyp); jeder
  Mitarbeiter pflegt nur die eigenen Einträge (Permission
  `availability.manage.own`). **Schichttausch mit Freigabe**
  (`schedule.exchanges.index`): Mitarbeitende beantragen Abgabe oder Tausch einer
  eigenen Schicht (Permission `shift.exchange`), ein Ziel-Kollege kann annehmen,
  die Teamleitung gibt frei (`shift.exchange.approve`) – die Freigabe prüft die
  neue Zuordnung über den bestehenden `ShiftComplianceService` (Ruhezeit,
  Höchstarbeitszeit, Überschneidung, Abwesenheit) und blockt harte Verstöße
  (Override durch die Leitung möglich); erst mit der Freigabe wechselt die
  Schicht-Zuordnung (bei echtem Tausch beide Schichten). **Besetzungsvorschläge**:
  der neue `StaffingSuggester` schlägt für eine offene/unterbesetzte Schicht
  gerankte Kandidaten vor (passende Qualifikation, Verfügbarkeit/Wunsch, kein
  Compliance-Konflikt, Fairness nach Wochenstunden) und blendet Kandidaten mit
  hartem Konflikt aus – nutzbar direkt an der offenen Schicht im Schichtplan,
  die Zuweisung läuft über den regulären Speichern-Pfad mit Compliance-Re-Check.
  **Unter-/Überbesetzungswarnung**: je Tag ein Warn-Badge bei offenen
  Soll-Schichten (wiederverwendet `OpenSlotService`/`CoverageService`).
  Synchrone Benachrichtigungen `shiftExchange.requested`/`.decided` plus
  Scanner-Reminder für ausstehende Freigaben; revisionssichere Audit-Einträge.
  Plan-Gating `module.planung`, eigene Hilfe-Topics
  (`planning.availability`, `planning.exchange`).
- Karten, Standort und Leitstelle (Feature 029, MVP): **Dispatch-Board /
  Leitstellen-Ansicht** (`dispatch.board`) zeigt die offenen und geplanten
  Aufträge eines Zeitraums kompakt – wahlweise als **Spalten nach
  Dispositionsstatus** (ungeplant/geplant/bestätigt/unterwegs/erledigt) oder als
  **Bahnen nach Mitarbeiter**. Jede Auftragskarte nennt Kunde, Zeitfenster und
  Mitarbeiter und markiert **harte Dispositionskonflikte** sowie **SLA-Risiken**
  (gefährdet/verletzt); ein Klick führt zum Auftrag. Ergänzend eine
  **Karten-Sicht** (`dispatch.map`) auf Basis der bestehenden Leaflet-/`map.js`-
  Einbindung: Aufträge werden über ihren eigenen Standort oder den
  **Kundenstandort** verortet, die Marker-Farbe folgt dem Dispositionsstatus,
  **SLA-gefährdete/-verletzte** Aufträge werden rot hervorgehoben; Filter
  „**nur SLA-Risiko**" und „**nur unbestätigte**". Beide Ansichten nutzen den
  neuen `DispatchBoardService`, der **ausschließlich vorhandene Bausteine
  wiederverwendet**: Dispositionsstatus über den `DispatchStatusResolver` und
  Konflikte über den `DispatchConflictChecker` (Feature 028) sowie das
  abgeleitete SLA-Risiko der offenen Service-Tickets (Feature 010, je Auftrag
  über den Kunden zugeordnet). Recht über die bestehende Permission
  `dispatch.viewAny`, Plan-Gating `module.planung`; eigener Hilfe-Topic
  `dispatch.board`. Bewusst **nicht** enthalten (Datenschutz):
  Tourenoptimierung, Echtzeit-Tracking, dauerhafte Standortüberwachung. Keine
  neuen Migrationen/Pakete.
- Terminierung, Einsatzplanung und Disposition (Feature 028, MVP-Kern):
  **Dispositionsstatus** am Auftrag (ungeplant/geplant/bestätigt/unterwegs/
  erledigt) als neuer Enum `DispatchStatus`. Der effektive Status wird vom
  `DispatchStatusResolver` bevorzugt aus der neuen, nullable Spalte
  `diary_entries.dispatch_status` gelesen und sonst aus den vorhandenen
  Planungsfeldern (`planned_at`/`assigned_user_id`/`status`/Lifecycle-
  Zeitstempel) **abgeleitet** — die WIP-Modellklasse `DiaryEntry` bleibt dabei
  unangetastet (Lese-/Schreibzugriff ausschließlich über den Service-/Query-
  Layer). **Konfliktwarnungen vor der Terminbestätigung** über den neuen
  `DispatchConflictChecker`, der die **bestehenden Compliance-Regeln**
  (`OverlapRule`, `RestPeriodRule`, `MaxDailyHoursRule`, `MaxWeeklyHoursRule`,
  `ConsecutiveDaysRule`, `VacationConflictRule`) wiederverwendet, indem er aus
  der geplanten Zuweisung eine transiente `ScheduledShift` baut und dem
  `ShiftComplianceService` füttert; zusätzlich eine Überschneidungsprüfung
  gegen andere Auftrags-Einsätze desselben Mitarbeiters. **Harte Konflikte**
  blockieren die Bestätigung und erfordern eine bewusste, revisionssicher
  protokollierte Übersteuerung mit Begründung; weiche Konflikte sind Hinweise.
  **Fahrzeug-Reservierung** (`vehicle_reservations`) mit
  `VehicleReservationService`, der Doppelreservierungen im selben Zeitfenster
  verhindert; Reservierung am Auftrag und Reservierungsliste je Fahrzeug.
  Anzeige des Dispositionsstatus als Badge in Auftragsliste und -detail. Neue
  Permissions `dispatch.viewAny`/`dispatch.manage` und `vehicle.reserve`
  (Teamleitung + Admin); Plan-Gating über `module.planung` (Disposition) bzw.
  `module.fuhrpark` (Reservierung). Hilfe-Topic `dispatch.overview`; i18n in
  allen fünf Sprachen (de/en/fr/it/es).
- Internationalisierung & Rechtsräume (Feature 034, MVP): **mandantenbezogener
  Feiertags-Rechtsraum**. Eine Organisation wählt unter *Erweiterte
  Einstellungen → Region & Feiertage* das maßgebliche Land/Bundesland
  (Yasumi-Provider, z. B. „Germany\\Bavaria"); damit gelten regionale Feiertage
  wie **Fronleichnam** oder **Reformationstag** nur dort, wo sie rechtlich
  greifen. Die Auflösung erfolgt mandantenbewusst über
  `Setting::get('holidays.provider')` (neue `config/holidays.php`, aus
  `config/app.php` ausgelagert) — der `HolidayService` cacht jetzt **pro
  Rechtsraum**. Da Feiertagszuschläge (`SurchargeCalculator`) und die
  Dienstplan-Compliance ausschließlich über den `HolidayService` lesen, nutzen
  **alle Konsumenten automatisch dieselbe Quelle**; die Feiertagsberechnung
  wurde nicht dupliziert, nur die Region-Auflösung erweitert. Neuer Helper
  `App\Support\HolidayRegions` (alle 16 DE-Bundesländer + bundesweit + AT) als
  Auswahl- und Validierungsregistry. Länderspezifische Spesen-/Pauschalsätze
  (BMF-Auslandstagegelder je Land/Region) sind bereits über `PerDiemRate`
  (`country` + `region_label`) und die Admin-Pflege abgebildet. i18n in allen
  fünf Sprachen (de/en/fr/it/es).
- Release-, Update- und Plugin-Strategie (Feature 022, MVP): **signierte/
  integritätsgesicherte Release-Metadaten** und **Plugin-Kompatibilität**.
  Neuer Befehl `php artisan release:manifest` erzeugt ein `release.json`
  (App-/Build-Version, PHP-/Laravel-/DB-Versionen, aktive Module + Plugins mit
  Kompatibilitätsangaben und **SHA-256-Prüfsummen** der Artefakte SBOM,
  `composer.lock`, `package-lock.json`); ist ein **Ed25519**-Private-Key
  vorhanden, wird das Manifest mit demselben Schlüssel/Mechanismus wie das
  Lizenzsystem (`sodium_crypto_sign_*`) signiert — sonst bleibt es unsigniert
  und nur prüfsummen-integer. `php artisan release:verify` prüft Prüfsummen
  und (falls vorhanden) die Signatur und erkennt Manipulationen (Exit-Code 1).
  Der Plugin-Contract erhält additiv `minAppVersion()` / `maxAppVersion()`
  (Default `null` über `PluginDefaults`, bestehende Plugins unberührt); die
  neue `PluginCompatibility` setzt den Bereich gegen `config('app.version')`
  durch: ein inkompatibles Plugin lässt sich **nicht aktivieren** und wird im
  Healthcheck als `failing` geführt (zählt auf Auto-Disable ein). Auf
  `admin/components` werden jetzt der **system:health**-Status (Hinweis „nach
  Update ausführen", inkl. ausstehender Migrationen) sowie das **Release-
  Manifest** (Erzeugen/Download, Signatur- und Integritätsstatus) angezeigt;
  `admin/plugins` zeigt Kompatibilitätsbereich/-status. `system:health`
  unterstützt zusätzlich `--json` (UI/Monitoring). Der **Build-Hash** steht nun
  neben der Version im Footer. Keine neuen Pakete, keine Migrationen
  (Metadaten als Datei/Command).
- Integrationen & offene API (Feature 008): neues **Webhook-System** für
  ausgehende, signierte Event-Benachrichtigungen. Die kuratierte Enum
  `WebhookEvent` (8 stabile Ereignisse) bindet je Fall genau ein real
  verdrahtetes `NotificationEvent` — die Auslösung hängt additiv im zentralen
  `NotificationDispatcher::notify()` und damit an denselben Stellen
  (Service-Trigger + Fristen-Scanner), die heute schon Benachrichtigungen
  feuern, ohne Umbau der Geschäftslogik. Neue Tabellen `webhook_endpoints`
  (HMAC-Signing-Key verschlüsselt at-rest, `$hidden`; abonnierte Events als
  JSON; Auto-Disable nach N aufeinanderfolgenden Fehlern) und
  `webhook_deliveries` (Zustellprotokoll je Versuch). Versand über
  `WebhookDispatchService` + `WebhookDeliveryJob` (Queue) mit
  **HMAC-SHA256-Signatur** über `<timestamp>.<body>` (Header
  `X-WorkDiary-Signature`, Replay-Schutz), kurzem Timeout, Retry mit Backoff
  und automatischer Endpunkt-Deaktivierung. Admin-UI `admin/webhooks` (CRUD
  als Modal, **Secret-Einmal-Anzeige** und -Rotation, Zustellprotokoll je
  Endpunkt, „Test-Event senden"). Neue Permissions `webhook.viewAny` /
  `webhook.manage` (Admin), Policy mit Org-Bindung, Hilfe-Topic
  `admin.webhooks`. Bewusst NICHT Teil dieses Schritts: Microsoft-365-/
  Google-Kalender-Anbindung (OAuth, separater Pilot).
- Qualität, Sicherheit & Arbeitsschutz (Feature 013, MVP): neues
  **Sicherheitsereignis-Register** (`safety-events.*`) für Unfall,
  Beinaheunfall, Gefährdung und Mangel — mit laufender `event_no` je
  Organisation, Schweregrad, Sofortmaßnahme, Ursachenanalyse, Foto-Nachweisen
  (`HasAttachments`) und Statusmaschine (*gemeldet → in Untersuchung →
  Maßnahmen definiert → geschlossen*; Abschluss erfordert eine
  Ursachenanalyse). Modell `SafetyEvent`, `SafetyEventService`,
  `SafetyEventController`, `SafetyEventPolicy`, Liste/Detail/Modale. **Kritische
  Ereignisse** (Unfall ODER Schweregrad „kritisch") feuern synchron das neue
  Ereignis `NotificationEvent::SafetyCriticalEvent` an die Leitung. Beim
  Schließen kann ein **offener Punkt** als Folgemaßnahme angelegt werden
  (Wiederverwendung des Offene-Punkte-Systems). Neue Permissions
  `safety.viewAny` / `safety.report` / `safety.manage` (Teamleitung führt das
  Register, Außendienst meldet); bewusst ungated (Core-Arbeitsschutz). Neuer
  **Sicherheits-Report** (`reports.safety`, Menü Auswertungen → Team):
  Ereignisse je Art und Schweregrad im Zeitraum, offen vs. geschlossen.
- Qualifikations-/Unterweisungs-Ablaufwarnung (Feature 013): der
  Fristen-Scanner (`notifications:scan-deadlines`) meldet ablaufende
  Mitarbeiter-Qualifikationen über das neue Ereignis
  `NotificationEvent::QualificationExpiring` (Vorlauf `--expiring-days`) an
  Person + Teamleitung; neues Pivot-Modell `UserQualification` für stabile
  Dedup-Subjekte. Pflicht-Sicherheitschecklisten je Auftragstyp laufen über
  das bestehende Prozedursystem (Feature 026, `applicability.diary_entry_type`,
  Vier-Augen über `SecondPersonGate`) — keine Parallelmechanik. Hilfe-Topic
  `safety.overview` (de/en).

- Nachkalkulation & Wirtschaftlichkeit (Feature 014, MVP): neuer
  **Wirtschaftlichkeits-/Deckungsbeitrags-Report** (`reports.economics`,
  Menü „Finanzen & Audit", Plan-Gating `module.auswertungen_team`, nur für
  Admin/`report.view` – Geschäftsführung/Buchhaltung). Je **Kunde** und je
  **Projekt** im gewählten Zeitraum: **Erlös** (abrechenbare Zeiten ×
  `TimeEntry.rate` + abgerechnetes Material `MaterialUsage.line_total_net` +
  abrechenbare, freigegebene Spesen `Expense.amount_net`) gegen **Kosten**
  (interner Zeit-Kostensatz `TimeEntry.internal_rate` + Material-/Beleg-
  Direktaufwand) ⇒ **Deckungsbeitrag** absolut und als **Marge** in Prozent,
  inkl. abrechenbarer vs. nicht-abrechenbarer Stunden. **Top/Flop-5-Ranking**
  je Projekt und Kunde nach Deckungsbeitrag. **Nacharbeit/Kulanz** als ehrlicher
  Proxy über nicht-abrechenbare Zeit (`billable=false`) ausgewiesen (es gibt
  keinen dedizierten Aktivitätstyp). **Plan-vs-Ist** je Projekt in Minuten
  (`Project.time_budget`) und in Geld (`Project.budget`). Reine Auswertung über
  ECHTE Modellfelder; fehlende interne Kostensätze werden transparent markiert
  (`*` + Hinweisbanner „Kostensätze nicht gepflegt"). CSV/PDF-Export mit
  Audit-Log (`report.exported`) wie die übrigen Reports; neuer
  `EconomicsReportBuilder`. Rechnungshoheit bleibt beim externen Faktura-System
  – die Werte hier sind Projektion. i18n de/en/fr/it/es paritätisch,
  Hilfe-Topic `reports.economics` (de/en). Bewusst offen: dedizierter
  Nacharbeit-/Kulanz-Typ, Beleg-/Positions-Drilldown, separater
  Materialkostensatz.
- Klassifikationen, Tags & Datenqualität (Feature 024, MVP): **Tagging über die
  bestehende polymorphe `HasTags`-Mechanik** auf **Kunde, Asset und Protokoll**
  ausgeweitet (Auftrag/Wissensartikel nutzten sie bereits) — keine neue Tag-Mechanik,
  dieselbe `taggables`-Tabelle (ohne Migration). `Tag` erhält die zusätzlichen
  `morphedByMany`-Relationen `customers()`/`assets()`/`protocols()`. Asset: Tag-Picker
  (`x-tag-picker`) im Bearbeiten-/Anlege-Dialog, Anzeige als Badges auf Detailseite
  und in der Liste (`SaveAssetRequest` um `tag_ids`/`new_tags` erweitert, Sqid-Dekodierung
  im Controller). Protokoll: `tag_ids`/`new_tags` in `ProtocolController::{store,update}`,
  Anzeige in der Fallakte. **Datenqualitäts-Hinweise**: neuer `DataQualityInspector`
  leitet fehlende Pflichtklassifikationen rein lesend aus dem vorhandenen
  `ClassificationRequirementValidator` und den am Auftrag persistierten Werten
  (Auftragsart, Priorität) ab; dezentes Badge auf der Auftrags-Detailseite. Der Validator
  erhielt dafür additiv ein `audit`-Flag (Hinweise erzeugen keine Audit-Logs).
  **Stillgelegte Klassifikationen** (`deprecated_at`) werden im Admin mit Datum
  ausgewiesen — über den Resolver nicht mehr neu wählbar, für historische Daten weiterhin
  lesbar. i18n: neue `classification.dataquality.*`-Sprachdateien + JSON-Keys in
  de/en/fr/it/es paritätisch. Bewusst offen: Tag-/Kategorie-Mapping für CSV-Import,
  Datenqualitäts-Report-Widget, Produkt-Tagging.
- Prozeduren, Arbeitsanweisungen & Checklisten (Feature 026, MVP-025): **Vorlagen-
  Designer-UI** und **PDF-/Druckansicht eines Laufs** auf dem bestehenden, bereits
  getesteten Execution-Backend (`ProcedureTemplate*`/`ProcedureRun*`/`ProcedureStepDef`,
  `ProcedureTemplateService`, `ProcedureExecutionService`, `ProcedureApplicabilityResolver`).
  Neue Admin-Seite `procedures.index` (Liste + Anlage-Modal) und Voll-Seiten-Designer
  `procedures.edit`: Stammdaten, Versionsverwaltung (Entwurf bleibt editierbar,
  Veröffentlichen friert die Version ein → Korrekturen erzeugen neue Version),
  Schritt-Editor mit dynamischen Zeilen (echte `ProcedureStepType`-/`ProcedureProofType`-
  Cases, Pflicht/sperrend, Vier-Augen, Rolle/Qualifikation) sowie **Anwendbarkeit**
  (Auftragstypen + Tags, wie vom `ProcedureApplicabilityResolver` genutzt). Neuer Service-
  Zusatz `ProcedureTemplateService::{updateTemplate,updateVersion,syncSteps}` (additiv;
  Sync ersetzt Schritte nur in Draft-Versionen). **Druckbare Read-Only-Lauf-Ansicht**
  `procedure-runs.print` (Standalone-Blade + Print-CSS, Hausmuster diary/case-file):
  Kopf (Vorlage/Version/Subjekt/Status/Zeiten), Schritte mit Ergebnis/Bestätiger/
  Vier-Augen, Abweichungen (`ProcedureDeviation`) und Backup-Nachweise
  (`ProcedureBackupProof`). **Automatische Zuordnung** sichtbar gemacht: Auf der
  Auftragsdetailseite zeigt ein Prozedur-Panel laufende/abgeschlossene Läufe (mit
  Druck-Link) und per Resolver vorgeschlagene, noch nicht gestartete Vorlagen als
  Start-Button. **Bedingte Schritte (wenn-dann)** additiv über `config.depends_on`
  (Bezugsschritt + erwarteter Wert) im Designer erfassbar und in der Druckansicht
  ausgewiesen — ohne Migration. `ProcedureTemplate`/`ProcedureRun` erhalten `HasSqid`
  (opake URLs), NavGate-Mapping + Admin-Menüeintrag (Permission `procedure.template.view`),
  i18n `procedure.*` + `enums.procedure.proof-type.*` in de/en/fr/it/es paritätisch,
  Hilfe-Topic `procedures.designer` (de/en) + Mapping. Bewusst offen: bedingte Schritte
  werden im Execution-Kern noch nicht ausgewertet (nur Vorlagen-Metadaten/Anzeige);
  die ausführende Schritt-für-Schritt-Lauf-UI bleibt außerhalb dieses MVP-Schritts.
- Inventar, Dienstmittel & Assets (Feature 009, MVP): **Ausgabe-/Rückgabe-Workflow
  (Checkout)** und **Defekt-/Sperrstatus**. Neue Tabellen `asset_assignments`
  (offene Zuweisung = ausgegeben; pro Asset höchstens eine, vom Service erzwungen;
  optional Person/Team, Auftragsbezug, erwartete Rückgabe, Zustand bei Ausgabe/Rückgabe)
  und `asset_defects` (Schweregrad low/medium/high/critical, Status open/inRepair/
  resolved/writtenOff mit Statusmaschine, `blocks_usage`-Sperre, Pflicht-Lösungsnotiz
  bei Erledigen/Ausbuchen) — beide `Auditable` + `BelongsToOrganization` + `HasSqid`
  + `softDeletes`. **Verfügbarkeit/Sperre werden aus diesen Tabellen abgeleitet**
  (keine neuen `AssetStatus`-Enum-Werte); der bestehende `Asset.status` wird zur
  Kompatibilität auf die vorhandenen Werte `loanOut`/`blocked` gespiegelt, soweit
  die Statusmaschine es zulässt. Ein gesperrtes oder bereits ausgegebenes Asset
  kann nicht ausgecheckt werden. Auf der Asset-Detailseite die Panels
  „Ausgabe / Rückgabe" (aktuelle Zuweisung + Historie, Checkout/Checkin als Modals)
  und „Defekte / Sperren" (Liste + „Defekt melden" + Status-Aktionen); in der
  Asset-Liste ein Verfügbarkeits-/Sperr-Badge (verfügbar / ausgegeben / gesperrt:
  Defekt). Neues NotificationEvent `asset.returnOverdue` im Fristen-Scanner
  `notifications:scan-deadlines` (überfällige Rückgabe an die ausleihende Person,
  Fallback/Eskalation Teamleitung, Dedup über `notification_dispatch_log`).
  Permissions `asset.checkout` (admin/teamleitung/aussendienst) und
  `asset.defect.manage` (admin/teamleitung), Policy-Abilities `checkout`/
  `manageDefects`, Enums `DefectSeverity`/`DefectStatus`, i18n `asset.*`-UI-Strings
  + `enums.asset.*` + `notification.*` + `access.*` in de/en/fr/it/es, Hilfe-Topic
  `assets.fleet` (de/en) erweitert. Assets sind keinem Plan-Modul für Checkout/Defekt
  zugeordnet — die Funktion bleibt ungated (nur Permission), konsistent zur
  bestehenden Asset-Verwaltung. Bewusst offen: Foto-/Anhang-Verknüpfung am Defekt,
  Wiederholdefekt-Statistik, Prüfintervall-Eskalation.
- SLA, Verträge & Service-Level (Feature 010, MVP): Service-Tickets zeigen auf
  Liste und Detail einen **abgeleiteten SLA-Status** (im Plan / gefährdet bei
  < 20 % Restzeit / verletzt) als Tone-Badge inkl. Restzeit, hergeleitet über
  den bestehenden `SlaTimer` aus den Reaktions-/Lösungsfristen des Tickets. Neues
  **SLA-Verletzungsregister** (`sla_violations`, `Auditable` + `softDeletes`,
  je Ticket+Typ genau eine Zeile) wird **idempotent** befüllt: durch den
  erweiterten Scanner `tickets:scan-sla-breaches` und durch zu späte
  Statusübergänge (erste Reaktion/Lösung) im `ServiceTicketService`. Neuer
  **SLA-Report** (`reports.sla`, Auswertungen → SLA, Permission `sla.viewAny`)
  mit Einhaltungsquote, Aufschlüsselung je Verletzungstyp, Priorität, Kunde und
  Ursache sowie Verletzungsliste mit Drill-down zum Ticket und Quittierung
  (`sla.manage`); CSV- und PDF-Export. **Eskalation** über den Fristen-Scanner
  `notifications:scan-deadlines`: neue NotificationEvents `sla.atRisk`
  (Restzeit < 20 %) und `sla.breached` (Frist überschritten, `supportsEscalation`)
  an den Ticket-Verantwortlichen, Fallback/Eskalation an die Teamleitung
  (Dedup über das `notification_dispatch_log`). Permissions `sla.viewAny`/
  `sla.manage` (admin + teamleitung), Policy `SlaViolationPolicy`, Enums
  `SlaStatus`/`SlaViolationKind`, i18n `sla.*`/`enums.sla.*`/`notification.*`/
  `access.*` in de/en/fr/it/es, Hilfe-Topic `sla.overview` (de/en) inkl.
  Route-Mapping. Service-Tickets sind keinem Plan-Modul zugeordnet — der Report
  bleibt ungated (nur Permission). Bewusst offen: Auftrags-/DiaryEntry-Verknüpfung
  des SLA-Kontexts, Wartungsintervalle, Inklusivzeiten/Kontingente und
  Geschäftszeiten in der Fristberechnung.
- Backup-Statusseite & Restore-Test-Register (Feature 017, MVP): neue
  **plattformweite Admin-Seite** `admin/backup` (Permission `backup.view`,
  Systembetrieb-Menü) zeigt je Quelle die **letzte registrierte Sicherung**
  (Zeitpunkt, Alter, Größe, gekürzter Manifest-Hash) aus den vorhandenen
  `backup_heartbeats` und warnt rot, wenn ein Heartbeat die Frische-Schwelle
  überschreitet (`backup.heartbeat_freshness_hours`, Default 26 h) oder gar
  kein Backup registriert ist. Neues **Restore-Test-Register**
  (`restore_tests`, plattformweit/ohne Tenant-Bezug analog Heartbeat, mit
  `softDeletes` + `Auditable`) inkl. „Restore-Test protokollieren"-Modal
  (Quelle, Datum, Ergebnis `passed|partial|failed`, Umfang, Größe, Dauer,
  Notiz, nächste Fälligkeit) und **Überfälligkeits-Warnung**, wenn der letzte
  erfolgreiche Test älter als `backup.restore_test_overdue_days` (Default 180)
  ist. Beide Schwellen zusätzlich als `system:health`-Checks (Backup-Heartbeat,
  Restore-Test; Tabelle-fehlt ⇒ übersprungen, Exit-Logik unverändert). Enum
  `RestoreTestResult` mit `label()/tone()`; i18n `backup.*` + `enums.backup.*`
  in de/en/fr/it/es; Hilfe-Mapping `admin.backup.*` → `admin.backups`. Bewusst
  offen: automatisierte Restore-AUSFÜHRUNG (Register dokumentiert manuell/Skript
  durchgeführte Tests), SaaS-mandantenbezogenes Restore.

- DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3 — MVP): gestellte und
  bezahlte **Rechnungen**, **Gutschriften** sowie optional freigegebene
  **Spesen** eines abgeschlossenen Zeitraums werden als prüfbarer
  **DATEV-Buchungsstapel (Format V700)** exportiert — über `php-financial-formats`
  (`BookingDocumentBuilder` + `DatevDocumentGenerator`), gekapselt im
  `DatevBookingAdapter`. Je Rechnung ein Debitor-Buchungssatz **Soll
  Debitorenkonto an Haben Erlöskonto** mit BU-Schlüssel (Brutto; 19 %⇒3, 7 %⇒2,
  0 %⇒0, konfigurierbar), Gutschriften umgekehrt; Belegfeld 1 = Rechnungsnummer,
  Belegdatum = Ausstellungsdatum. **Buchhaltungs-Konfiguration je Organisation**
  (`settings['datev']`): Berater-/Mandantennummer, Kontenrahmen (SKR03/SKR04),
  Sachkontenlänge, Erlöskonten (Standard + steuerfrei), Debitoren-Nummernkreis,
  Steuerschlüssel-Mapping, Festschreibekennzeichen (GoBD), Zeichensatz (Default
  ISO-8859-1). **Debitorennummer je Kunde** (`customers.debtor_no`) mit
  deterministischer Vergaberegel als Fallback. Datenmodell `datev_booking_batches`
  / `datev_booking_sources` (morph, Doppel-Übergabe-Schutz) / append-only
  Hash-Kette `datev_booking_events` (`audit:verify`). **Hoheits-Ausschluss**:
  extern (Lexoffice/DATEV) geführte Rechnungen gehören nicht in den lokalen
  Stapel und werden ausgeschlossen + im Preflight gewarnt. Finalisierter Stapel
  unveränderlich (CSV + SHA-256). Berechtigung `finance.booking.export`
  (Buchhaltung + Admin), Konfiguration `finance.config` (Admin); Modul-Gating
  `module.finance`. Prozesshilfe `finance.datev-bookings` (de/en).

- ISMS „Betrieb und Wirksamkeit" (Feature 044, MVP 2 — Kern): **Sicherheits-
  vorfälle** (`isms_security_incidents`) unabhängig vom Personenbezug, mit
  Kategorie/Kritikalität, Statusmaschine (gemeldet → Bewertung → eingedämmt →
  bereinigt → wiederhergestellt → geschlossen; Abschluss erzwingt
  Ursachenanalyse **und** Lessons Learned) und Rückführung in Risiken/Maßnahmen
  (`isms_incident_risk`/`isms_incident_control`). Datenschutz bewusst lose
  gekoppelt: ein Flag weist auf die **separate** Datenschutzmeldung hin
  (Fallakten getrennt), ein optionaler Freitext-Verweis (`privacy_incident_ref`,
  kein FK auf die Privacy-WIP-Tabelle) referenziert den Datenschutzvorfall;
  neue **kritische** Vorfälle melden synchron an die Leitung. **Schwachstellen-
  register** (`isms_vulnerabilities`) mit Kritikalität (aus CVSS-v3 ableitbar),
  Verantwortung, Frist, Inventar-Bezug und Statusmaschine; die **Ausnutzbarkeits-
  Entscheidung** ist eine bewusste, begründete Nutzeraktion (Pflichtnotiz),
  überfällige Schwachstellen werden über `notifications:scan-deadlines` gemeldet
  und eskaliert (`isms.vulnerabilityOverdue`). **Advisory-Import (CSAF/VEX)**
  nativ per `json_decode` (kein neues Paket): Abgleich betroffener Komponenten
  gegen das Softwareinventar und optional die letzte Release-SBOM
  (`workdiary-latest.cdx.json`); `known_affected` ⇒ offen + Ausnutzbarkeit „in
  Untersuchung" (**nie automatisch ausnutzbar**), `known_not_affected` (VEX) ⇒
  „nicht betroffen" mit VEX-Begründung als Pflichtnotiz. Original-Advisory mit
  SHA-256 als Nachweis (`isms_advisories`), Re-Import idempotent. Routen unter
  `compliance/isms` (`isms.incidents.*`, `isms.vulnerabilities.*`,
  `isms.advisories.*`), Plan-Gating `module.isms`, Berechtigungen über die
  bestehenden `isms.viewAny/view/manage` (keine neuen Permissions). Offen:
  Lieferantenbewertung (Stretch), vollständige VEX-Profile, automatischer
  Advisory-Feed.
- Zahlungsabgleich (Feature 045, Priorität 3 / Phase 4): Import von
  Bankauszügen im Format **CAMT.053** (bevorzugt) und **MT940** (Fallback)
  über einen Adapter um `php-financial-formats`
  (`BankStatementParser`). Bankumsätze landen in einem Prüfbereich
  (`bank_statements`/`bank_transactions`) und ändern keinen Beleg; offene
  Rechnungen und freigegebene Spesen werden score-basiert vorgeschlagen
  (`MatchingService`: Rechnungsnummer/Betrag/Skonto/IBAN-Hash/Datumsnähe,
  Skonto-Toleranz Default 3 %, Cent-Toleranz ±0,02). Erst die Bestätigung
  (`ReconciliationService::confirm`) setzt `Invoice.status=paid`/`paid_on`
  bzw. `Expense.reimbursed_at`; Teil-/Überzahlung werden unterschieden,
  Zuordnungen sind reversibel (`payment_allocations` SoftDelete, `unmatch`
  ohne Veränderung des Bankumsatzes). Dublettenschutz über Datei-Hash je
  Organisation und Umsatz-Fingerprint, Saldenketten-Prüfung
  (`balance_check`). Eigene Bankkonten (`bank_accounts`, IBAN verschlüsselt
  at-rest + `iban_hash`-Blindindex, Admin-CRUD via Modal). PII der Bankumsätze
  (Name/IBAN/Verwendungszweck) verschlüsselt; Matching ausschließlich über
  unverschlüsselte Ableitungen. Append-only Hash-Kette
  (`payment_reconciliation_events`, `config('audit.chains')`, `audit:verify`).
  Neue Permissions `finance.payment.import` und `finance.payment.reconcile`
  (Buchhaltung + Admin); Bankkonten über `finance.config`. Modul-Gating
  `module.finance`. Bewusst offen: Fremdwährungs-Kursdifferenz, Sammelbuchungs-
  Auflösung, EBICS/FinTS, Lastschrift-Rückläufer.
- Tagesabschluss (MVP-015, Feature 001): Seite `/tagesabschluss` mit
  Anwesenheit (Durchgriff auf die Stempeluhr), Pausen-Soll/Ist, Buchungsliste
  (bestehendes Buchungs-Modal), 7 Konsistenzprüfungen (⛔ blockierend / ⚠
  Hinweis, `DayClosureValidator`), Soll/Ist-Bilanz inkl. Monats-Saldo und
  sticky Abschluss-CTA. Statusmaschine open → closed → correction → open
  (abgeleitetes `locked` aus der Monatsfreigabe MVP-016) mit Audit-Spur
  (`dayClose.opened/.entrySaved/.closed/.correctionRequested/
  .correctionApproved/.correctionRejected/.reopened`), Korrektur-Workflow
  mit Pflicht-Begründung (≥ 20 Zeichen) und Stempel-Sperre nach Freigabe,
  Admin-Reopen mit Audit-Grund sowie 7 `dayClose.*`-Permissions
  (view.own/team/organization, close.own, requestCorrection.own,
  approveCorrection, reopen). Bewusst offen: Drag-Quick-Buchung (§2.3),
  Ctrl+Enter-Shortcut (§8), Korrektur-Inbox (MVP-017).

- E-Rechnung-MVP (Feature 045, Abschnitt 8): XRechnung-konformes
  UBL-2.1-XML (EN 16931, CIUS XRechnung 3.0) für lokale Ausgangsrechnungen
  im Pfad „WorkDiary führt" — `XRechnungGenerator` mit
  Pflichtfeld-Preflight (Fehler blockieren, Warnungen nicht),
  Verkäuferstammdaten je Organisation (Invoicing-Tab,
  `settings['einvoice']`: Anschrift, USt-IdNr./Steuernummer, Kontakt,
  IBAN/BIC, Zahlungsziel, Kleinunternehmer § 19 UStG ⇒ Steuerkategorie E),
  Leitweg-ID/Käuferreferenz (BT-10) je Kunde (`customers.buyer_reference`),
  Steuerkategorien S/Z/E, SEPA-Zahlweg 58, Einheiten-Mapping
  (Stunde ⇒ HUR, Stück/Default ⇒ C62), Gutschriften als Typ 381 und
  Download-Button auf der Rechnungs-Detailseite (nur gestellt/bezahlt,
  gesperrt bei externer Fakturierungshoheit). Bewusst offen:
  Schematron-/KoSIT-Validierung (Java), Peppol-Versand.

- E-Rechnung auf `php-erechnung-toolkit` umgestellt und ZUGFeRD ergänzt
  (Feature 045, Abschnitt 8): der `XRechnungGenerator` bleibt als Adapter
  mit unveränderter öffentlicher API (preflight/generate), baut das UBL-XML
  intern aber über den `ERechnungDocumentBuilder` des Toolkits. NEU:
  ZUGFeRD-Download (`invoices.zugferd`, Button „ZUGFeRD (PDF)") als
  PDF/A-3 mit eingebettetem CII-XML (Profil EN 16931/COMFORT) über
  `ZugferdPdfGenerator` + `php-pdf-toolkit`; die visuelle Darstellung ist
  die bestehende Rechnungs-PDF-View (`invoices.pdf`). Der Preflight bleibt
  die Validierungsschicht (das Toolkit prüft keine Geschäftsregeln) und ist
  profilabhängig: BT-10/BuyerReference ist nur für die XRechnung Pflicht,
  für ZUGFeRD eine Warnung; zusätzlicher Betragstreue-Check gegen die vom
  Toolkit selbst berechneten Summen. Gutschriften werden jetzt als
  UBL-CreditNote-Dokument emittiert (vorher Invoice mit TypeCode 381).

- Kommunikationsnotizen (MVP-012): Telefonate, E-Mails und Vor-Ort-Gespräche
  als Notizen an Aufträgen, Kunden und Projekten — inkl. Vertraulichkeit,
  Kundenportal-Freigabe und Folgeaktionen.
- Dokumentenmanagement (MVP-031): Verträge, Zertifikate und Prüfberichte mit
  append-only-Versionierung, Gültigkeiten und Archivierung.
- Benachrichtigungsregeln (MVP-018): konfigurierbare Regeln und Eskalationen
  je Organisation.
- Zuschlagsregeln (Feature 005): Nacht-/Sonn-/Feiertagszuschläge für die
  Lohnübergabe.
- Timeline/Fallakte (Feature 023): chronologische Fallakte je Auftrag mit
  allen verknüpften Ereignissen.
- Wissensbasis & Problemhistorie (Feature 011): Artikel mit Problem/Lösung,
  Kategorien, Tags und Redaktions-Workflow.
- Vorlagen- & Formularsystem (Feature 032): Formularvorlagen mit
  versionssicherem Felder-Snapshot beim Ausfüllen.
- Betriebsmetriken (Feature 036): Admin-Seite `admin/metrics` mit Queue-Stand,
  Backup-Heartbeats, Plugin-Fehlern, Speicher-Kennzahlen, Datensatzzählungen
  und datenschutzfreundlicher, rein lokaler Feature-Nutzungsstatistik
  (`feature_usage_counters`).
- Release-Basics (Feature 022): Versions-Anzeige (`config('app.version')`,
  Footer + Metrik-Seite), Health-Check-Command `php artisan system:health`
  für die Prüfung nach Updates sowie Release-Prozess-Doku
  (`docs/release-prozess.md`).
- ISMS MVP1 (Feature 044): Risikoregister mit 5×5-Matrix und Statusmaschine,
  Maßnahmenkatalog mit ISO/IEC-27001:2022-Annex-A-Import (93 Controls) und
  druckbarem Statement of Applicability (`module.isms`, Enterprise).
- Managementsystem-Kern (Feature 046): ISMS auf den gemeinsamen Kern
  refactort — Geltungsbereiche (`isms_scopes`), versionierte
  Normanforderungen (`isms_requirements`, Annex-A als Normprofil
  ISO/IEC 27001:2022), normneutrale Maßnahmen mit
  n:m-Anforderungs-Mapping und SoA als eigene Applicability-Statements je
  Geltungsbereich (inkl. Datenmigration bestehender Controls).
- Normprofil-Registry (Feature 046, Inkrement A): sieben Normprofile als
  Kataloge (ISO/IEC 27001:2022 mit Annex A + HLS-Kapiteln; 27701, 9001,
  22301, 45001, 37301 und 42001 auf HLS-Ebene mit eigenen Kurztiteln),
  Normprofil-Auswahl beim Katalog-Import, Norm-Filter und Mehr-Scope-SoA
  mit Geltungsbereichs-Wechsel.
- Zertifikatsregister (Feature 046, Inkrement B): Konformitätsstatus je
  Geltungsbereich und Norm mit strikter Statuskette — `zertifiziert` nur
  mit hinterlegtem, aktuell gültigem Zertifikat (Zertifizierungsstelle,
  Nummer, Geltungsbereich, Gültigkeit, Überwachungstermine, optionales
  Dokument aus dem Dokumentenmodul); automatischer Verfall und
  Ablauf-Warnung (`isms.certificateExpiring`) über den Fristen-Scanner.
- Risiko-Bewertungshistorie (Feature 046, Inkrement D): Brutto-/Netto-/
  Ziel-Bewertungen als unveränderliche, freigegebene Stände je Risiko
  (Person/Zeitpunkt), Direktbewertungen historisieren automatisch,
  Restrisiko-Akzeptanz erfordert eine freigegebene Netto-Bewertung mit
  Reviewdatum; Scanner-Ereignis `isms.riskReviewDue`.
- Audit- und Verbesserungszyklus (Feature 046, Inkrement C): interne/
  externe/Lieferanten-Audits je Geltungsbereich mit Statuskette und
  Unabhängigkeitsprüfung, Feststellungen (Nichtkonformität major/minor,
  Beobachtung, Verbesserung) mit Anforderungsbezug, Korrekturmaßnahmen mit
  Ursachenanalyse und Wirksamkeitsprüfung (Pflicht-Notiz; unwirksam setzt
  die Feststellung zurück), Managementbewertungen mit unveränderlicher
  Freigabe (Person/Zeitpunkt) sowie Fristen-Scanner-Ereignis
  `isms.correctiveActionOverdue` für überfällige Korrekturmaßnahmen
  (`module.isms`, Enterprise).
- Auditpakete & Prüferzugang (Feature 046, Inkrement E / 044
  „Auditbereitschaft"): stichtagsbezogene, integritätsgeschützte
  Auditpakete je Geltungsbereich (`isms_audit_packages`) — Finalisierung
  friert den Datenstand als JSON-Snapshot ein (SoA, Risikoregister inkl.
  freigegebener Bewertungen, Maßnahmen, Konformität + Zertifikate, Audits
  mit Feststellungen/Korrekturmaßnahmen, freigegebene
  Managementbewertungen, Softwareinventar) mit SHA-256-Integritätsnachweis
  (`isms:verify-packages` + UI-Prüfung; finalisierte Pakete sind
  unveränderlich). Ehrliche Stichtags-Semantik: `as_of_date` =
  dokumentierter Berichtsstichtag, `data_captured_at` = Datenstand bei
  Finalisierung (kein Event-Sourcing). Zeitlich begrenzter, lesender
  Prüfer-Download über tokenisierte öffentliche Links (nur SHA-256-Hash
  gespeichert, Klartext einmalig sichtbar, 1–90 Tage, widerrufbar)
  (`module.isms`, Enterprise).
- Auditbereitschafts-Dashboard & Register-Exporte (Feature 044, MVP1-
  Abschluss): Kennzahlen-Dashboard „Auditbereitschaft" je Geltungsbereich
  als erster Eintrag des ISMS-Bereichs (`ReadinessService`, reine
  Leseaggregation) — SoA-Fortschritt je Norm, hohe Risiken (Score > 12),
  überfällige Bewertungs-Reviews und unbewertete Risiken, überfällige
  Korrekturmaßnahmen, offene Nichtkonformitäten, Nachweislücken
  (anwendbar ohne Evidenz und umgesetzte Maßnahme), Zertifikatsablauf/
  Überwachungstermine < 90 Tage und Software-EOL; KPI-Kacheln mit
  Warn-Tones und Drill-down in die Register (reines Blade/CSS). Dazu
  JSON-/CSV-Direkt-Exporte (`?format=json|csv`) für Risikoregister,
  Anforderungen/SoA (je Scope) und Maßnahmen mit meta-Block
  (Organisation, Geltungsbereich, generated_at, App-Version; CSV mit
  Semikolon + UTF-8-BOM) — „versioniert" leistet weiterhin der
  unveränderliche Auditpaket-Snapshot (`module.isms`, Enterprise).
- Kontextbezogene Prozesshilfe (Feature 039): rechte, nicht-blockierende
  Hilfe-Sidebar am Desktop (mobil Drawer) mit automatischem Seitenkontext
  über eine Route→Topic-Registry (`config/help-topics.php`), Hilfe-Button
  im Header, `?`-Shortcut, gemerktem Auf/Zu-Zustand und Fallback mit
  Suche; 26 neue Hilfe-Topics in Deutsch und Englisch (ISMS-Prozesse,
  Datenschutz, Dokumente, Formulare, Wissensbasis, Kommunikationsnotizen,
  Faktura-Übergabe, Lohnexport, Glossar, 7-teiliges Admin-Handbuch) plus
  rollenbasierte Einstiegshilfen für Außendienst, Teamleitung,
  Buchhaltung, Admin und Geschäftsführung (audience-gesteuert).
- Softwareinventar & Release-SBOM (Feature 044 MVP1):
  organisationsbezogenes Softwareinventar (Produkte, Installationen,
  Support-Status mit EOL-Automatik) sowie `php artisan sbom:generate`
  (CycloneDX 1.5 aus composer.lock/package-lock.json, Modulen und Plugins)
  mit geschützter Admin-Komponentenübersicht (`admin/components`).
- Finanzschnittstelle, erstes Inkrement (Feature 045): Fakturierungsweg je
  Organisation/Kunde (`billing_mode`) mit Rechnungshoheit beim externen
  Programm — lokale Rechnungserstellung ist bei extern geführter Fakturierung
  gesperrt; Übergabenachweise (`billing_transfers`) mit getrennten Kanälen
  Zeit/Material, Payload-Hash und Hash-Ketten-Events (`audit:verify`);
  Lexoffice-Positionsübergabe als Rechnungsentwurf über die bestehende API;
  Datei-Übergabepaket (CSV) für DATEV-/manuelle Abläufe; Sperre von
  Zeitkorrekturen an bereits übergebenen Zeiten (`module.finance`,
  Enterprise).
- Globale Suche erweitert (Feature 023): die Command-Palette findet jetzt
  auch Kommunikationsnotizen (Betreff; vertrauliche nur für Erfasser und
  `communication.confidential.manage`), Dokumente (Titel, nur mit
  `document.viewAny`), Wissensartikel (Titel/Problem; Veröffentlichtes
  plus eigene Entwürfe) und Formular-Submissions (Vorlagen-Name; ohne
  `formTemplate.viewAny` nur eigene) — Dokumente/Wissensbasis/Formulare
  modul-gegatet über Plan/Lizenz, Mandantengrenzen über die
  Organization-Scopes abgesichert.

### Fixed

- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): vier Lücken aus dem
  ersten Praxiseinsatz. **Bestandszeiten blieben mit 0,00 € bewertet** — Zeiten,
  die vor Anlage der Kondition erfasst wurden, tragen keinen Satz-Snapshot, und
  „Satz neu anwenden" fasste nur Einträge mit gesetztem Konditions-Marker an
  (bei den übrigen wurde kein Feld „dirty", also rechnete auch der Save-Hook
  nicht neu). „Neu berechnen" bewertet sie jetzt nach; manuelle Satz-Overrides
  bleiben unangetastet. **Lexoffice-Zahlungen wurden brutto in einen
  Netto-Saldo gebucht** (voucherlist liefert nur Brutto) — der Nettobetrag wird
  jetzt am Beleg nachgeladen und gecacht, Teilzahlungen anteilig. **Pauschal-
  rechnungen, die direkt in Lexoffice erstellt wurden, waren nicht zuordenbar**
  (der Abgleich kannte nur selbst gepushte Belege) — es gibt jetzt „Beleg
  verknüpfen" am Monat plus einen eng gefassten Auto-Match (Monat, Nettobetrag
  und genau ein Kandidat); bei verknüpftem Beleg entfällt „Pauschale senden",
  damit in Lexoffice keine zweite Rechnung entsteht. **Der Abgleich lief nur im
  stündlichen Cron** — der Belege-Sync an der Kundenakte und der Hintergrund-
  Sync ziehen den Zahlstatus jetzt mit; zudem band `lexoffice:sync-vouchers`
  den Organisations-Kontext nicht, wodurch der Belegabruf den API-Key einer
  fremden Organisation hätte verwenden können.
- Offene Zeiten (MVP-460): Kunden mit laufendem Leistungssaldo (Sonderkonditionen
  im Modus „Kundenkonto" oder „Pauschale") standen in der Fakturierungs-
  Arbeitsliste, obwohl ihre Zeiten nie fakturiert, sondern über den Monatsblock
  der Kundenakte abgerechnet werden — sie wurden erst beim Monatsabschluss
  `exported` und waren bis dahin Dauergäste, die Anzahl, offene Zeit und
  erwarteten Netto-Erlös verfälschten. Sie sind jetzt aus Liste, CSV-Export,
  Digest-Mail und der Massenaktion „Als abgerechnet markieren" ausgenommen; ein
  Hinweis über der Liste nennt die Zahl der ausgeblendeten Einträge, damit die
  Kontrollfunktion erhalten bleibt. Kunden im Modus „monatliche Rechnung"
  bleiben sichtbar — sie laufen über die normale Fakturierung.
- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): **die Monatszeile
  zeigte die Zahlung des Vormonats**. Retainer-Rechnungen gehen am Monatsende
  raus und werden Anfang des Folgemonats bezahlt; da Zahlungen bisher strikt
  nach ihrem Zahldatum einsortiert wurden, stand die Januar-Pauschale im
  Februar und der Januar bei „Abgerechnet 0,00 €" — der Endsaldo stimmte,
  die Monatsdarstellung war um einen Monat versetzt. Zahlungen zu einem Beleg,
  der an einem Monat hängt, zählen jetzt in **diesen** Monat (neue Zuordnung
  `customer_account_payments.customer_billing_statement_id`); das echte
  Zahldatum bleibt für den Nachweis erhalten, Bank-, Hand- und Import-
  Zahlungen ordnen sich weiterhin über das Datum ein. Zahlungen in bereits
  abgeschlossenen Monaten behalten ihre Zuordnung.
- Kunden-Sonderkonditionen (Feature 098): **die Tätigkeitsauswahl der
  Anfahrtspauschale bot Kategorien an, die auf Kundenprojekten nie vorkommen**
  (Pause, Krank, Verwaltung …). Angeboten werden jetzt nur Kategorien, die an
  den Zeiten des jeweiligen Kunden tatsächlich auftreten; tragen dessen Zeiten
  gar keine Kategorie, steht dort der Hinweis, dass die Anfahrt für alle
  Einträge gilt.
- Kunden-Sonderkonditionen (Feature 098): **eine Satzänderung wirkte nicht auf
  bereits erfasste Zeiten**. Der Konditionsdialog löschte beim Speichern alle
  Satzzeilen und legte sie neu an; da der Konditionsnachweis am Zeiteintrag
  (`customer_billing_rate_id`) per `nullOnDelete` an der Satzzeile hängt,
  verlor jeder Eintrag seine Zuordnung — auch in abgeschlossenen Monaten —,
  und „Neu berechnen" erkannte ihn danach nicht mehr. Wer den Wochenendsatz
  von 17,50 € auf 18,50 € änderte, sah im offenen Monat weiterhin 17,50 €.
  Satzzeilen werden jetzt anhand von Tätigkeit × Tagtyp fortgeschrieben statt
  ersetzt (mit Soft-Delete für entfernte Zeilen), und der Nachweis am Eintrag
  bleibt erhalten; nur ein tatsächlicher Handeingriff löst ihn noch ab.
  „Neu berechnen" erfasst zudem alle abrechenbaren Zeiten der offenen Monate —
  manuell gesetzte Stundensätze behalten ihren Satz.
- Kunden-Sonderkonditionen, Pauschal-Modus (Feature 098): **„Beleg verknüpfen"
  brach nach einem vorherigen Lösen/Storno mit einem Datenbankfehler ab**
  (Duplicate entry für `uq_cap_source_ref`). Die stornierte Zahlung wird nur
  soft-deleted, blockiert den Unique-Index aber weiter, während
  `updateOrCreate` sie nicht mehr fand und neu anlegen wollte. Der
  Zahlungs-Rücksync sucht die Zeile jetzt inklusive stornierter Einträge und
  belebt sie wieder, statt eine zweite anzulegen.
- Rechnungs-Detailseite: Inline-`@php(...)` in Kombination mit einem
  `@php … @endphp`-Block in derselben View erzeugte über Blades
  Raw-Block-Erkennung ein ungültiges `<?php(` (kein PHP-Open-Tag) — der
  Block wurde nie ausgeführt („Undefined variable $showServiceDates").
  Alle `@php`-Vorkommen der View nutzen jetzt die Blockform.
