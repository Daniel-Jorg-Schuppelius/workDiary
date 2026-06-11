# Changelog

Alle nennenswerten Änderungen an WorkDiary werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/)
(siehe `docs/release-prozess.md`).

## [Unreleased]

### Added

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
- Finanzschnittstelle, erstes Inkrement (Feature 045): Fakturierungsweg je
  Organisation/Kunde (`billing_mode`) mit Rechnungshoheit beim externen
  Programm — lokale Rechnungserstellung ist bei extern geführter Fakturierung
  gesperrt; Übergabenachweise (`billing_transfers`) mit getrennten Kanälen
  Zeit/Material, Payload-Hash und Hash-Ketten-Events (`audit:verify`);
  Lexoffice-Positionsübergabe als Rechnungsentwurf über die bestehende API;
  Datei-Übergabepaket (CSV) für DATEV-/manuelle Abläufe; Sperre von
  Zeitkorrekturen an bereits übergebenen Zeiten (`module.finance`,
  Enterprise).
