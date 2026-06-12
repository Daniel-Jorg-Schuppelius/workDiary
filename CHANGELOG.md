# Changelog

Alle nennenswerten Änderungen an WorkDiary werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/)
(siehe `docs/release-prozess.md`).

## [Unreleased]

### Added

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
  (Stunde ⇒ HUR, Stück ⇒ H87, Default C62), Gutschriften als Typ 381 und
  Download-Button auf der Rechnungs-Detailseite (nur gestellt/bezahlt,
  gesperrt bei externer Fakturierungshoheit). Bewusst offen: ZUGFeRD
  (PDF/A-3), Schematron-/KoSIT-Validierung (Java), Peppol-Versand.

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

- Rechnungs-Detailseite: Inline-`@php(...)` in Kombination mit einem
  `@php … @endphp`-Block in derselben View erzeugte über Blades
  Raw-Block-Erkennung ein ungültiges `<?php(` (kein PHP-Open-Tag) — der
  Block wurde nie ausgeführt („Undefined variable $showServiceDates").
  Alle `@php`-Vorkommen der View nutzen jetzt die Blockform.
