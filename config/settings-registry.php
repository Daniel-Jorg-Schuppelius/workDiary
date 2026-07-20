<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settings-registry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Settings-Registry (Feature 067, MVP-173): Metadaten je registrierter
 * Einstellung — Typ, erlaubte Scopes, Validierung, Sensibilität. KEINE
 * Werte: Defaults leben weiter in config/<gruppe>.php (env-überschreibbar),
 * Org-Overrides in organizations.settings, System-Overrides in
 * system_settings. Die Registry wächst mit den Phasen (operations.*,
 * updates.* folgen mit dem 041-Paket); unregistrierte Keys verhalten sich
 * über Setting::get() unverändert.
 *
 * Reine Arrays, keine Closures (config-cachebar).
 */

return [
    // --- Pagination (config/pagination.php) ---
    // Grenzen = historische Formulargrenzen des Org-Formulars (067-P3b):
    // die Registry ist Single Source of Truth für Formular- UND
    // kanonische Validierung, daher gilt überall min:1|max:1000.
    'pagination.timesheets' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.duty_plans' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.customers' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.customer_search' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.customer_attachments' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.organizations' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:1000'],
    'pagination.tours' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.vehicles' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.tags' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.archive' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.dashboard_recent' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.notifications' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],

    // --- Uploads (config/uploads.php, KB wie Laravel-max-Regel) ---
    // Grenzen = historische Formulargrenzen (067-P3b, s. pagination).
    'uploads.csv_import_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],
    'uploads.customer_attachment_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],
    'uploads.attachment_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],

    // --- Archiv (config/archive.php) ---
    'archive.schedule_at' => ['type' => 'time', 'scopes' => ['system'], 'affects' => ['archive.run']],

    // --- Scheduler (config/scheduler.php) ---
    'scheduler.retention_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:7|max:365', 'affects' => ['scheduler.watchdog']],

    // --- Betrieb (Feature 041, MVP-056–058) ---
    // Betreiber-Organisation für installationsweite Aufgaben/Meldungen
    // (null/nicht gesetzt = erste Organisation).
    'operations.system_org_id' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'nullable|min:1'],
    // Snooze-Voreinstellung des Aufgabencenters in Tagen.
    'operations.snooze_days' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:90', 'fallback' => 7],

    // --- Integrationen (Feature 067, MVP-178) ---
    // Auto-Disable-Schwelle für Konnektoren mit HasConnectionHealth.
    'integrations.auto_disable_threshold' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:2|max:100', 'fallback' => 10],

    // --- Ablaufwarnungen (Feature 041, MVP-057) ---
    'operations.expiry.license_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:365', 'fallback' => 30],
    'operations.expiry.credential_days' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:90', 'fallback' => 14],
    'operations.expiry.eol_lead_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:7|max:365', 'fallback' => 90],

    // --- Updates (Feature 022, MVP-054) ---
    // Externe Update-Kommunikation ist Opt-in: disabled | manual | auto.
    'updates.check_mode' => ['type' => 'enum', 'scopes' => ['system'], 'options' => ['disabled', 'manual', 'auto'], 'fallback' => 'manual', 'affects' => ['updates.check']],
    'updates.feed_url' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|url'],

    // --- Telemetrie (Feature 036, MVP-337) ---
    // Lokale Feature-Nutzungszähler (feature_usage_counters). Kein externer
    // Versand — daher Opt-out mit Default AN (config/telemetry.php) statt
    // Opt-in wie updates.check_mode; Org-Scope erlaubt mandantenweises
    // Abschalten, System-Scope schaltet die ganze Installation.
    'telemetry.enabled' => ['type' => 'boolean', 'scopes' => ['system', 'organization'], 'fallback' => true],

    // --- Fehlermeldesystem (Feature 041, MVP-053) ---
    // Diagnose-Anhang: ask (Melder entscheidet) / always / never.
    'support.problem_reports.diagnostics' => ['type' => 'enum', 'scopes' => ['system', 'organization'], 'options' => ['ask', 'always', 'never'], 'fallback' => 'ask'],
    // Versandweg je Betriebsmodell.
    'support.problem_reports.delivery' => ['type' => 'enum', 'scopes' => ['system'], 'options' => ['saas_inbox', 'mail', 'webhook', 'local_export'], 'fallback' => 'saas_inbox'],
    'support.problem_reports.mail_to' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|email'],
    'support.problem_reports.webhook_url' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|url', 'sensitive' => true],

    // --- Backup-Schwellen (config/backup.php) ---
    'backup.thresholds_hours.warn' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:720'],
    'backup.thresholds_hours.critical' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:2160'],
    'backup.heartbeat_freshness_hours' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:720'],
    'backup.restore_test_overdue_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:7|max:1095'],

    // --- Faktura/Anzeige (config/invoicing.php, config/ui.php) ---
    'invoicing.default_currency' => ['type' => 'string', 'scopes' => ['system', 'organization'], 'rules' => 'size:3'],
    'invoicing.default_tax_rate' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:8'],
    'invoicing.time_unit' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:8'],
    // Abrechnungs-Taktung: Org-Default, Kunde/Projekt können übersteuern (harter Default 1/0 im Trait)
    'invoicing.billing_increment_minutes' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:1440', 'fallback' => 1],
    'invoicing.billing_grouping_gap_minutes' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1440', 'fallback' => 0],
    'ui.dashboard.recent_limit' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'ui.calendar.slot_minutes' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'options' => [10, 15, 20, 30, 60]],

    // --- Wetter/Zeiterfassung ---
    // weather.auto_fetch hat keinen config-Default (harter Default false in Protocol)
    'weather.auto_fetch' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Provider-Auswahl (Bauturbo A7/MVP-131): open-meteo (Default) oder dwd
    // (amtliche DWD-Open-Data-Tageswerte, CC BY 4.0, nur Deutschland).
    'weather.provider' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['open-meteo', 'dwd'], 'fallback' => 'open-meteo'],
    // DWD: maximale Entfernung zur nächsten Station — darüber hinaus lieber
    // kein Snapshot als falsche Daten.
    'weather.dwd_max_station_km' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:200', 'fallback' => 30],
    // edit_window_days: kein config-Default; null = kein Bearbeitungsfenster erzwungen
    'timesheet.edit_window_days' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:365'],

    // =====================================================================
    // Org-Formular-Keys (067-P3b): jede hier org-scoped registrierte
    // Einstellung ist über formRulesForScope(Organization) validier- und
    // setzbar; `options_from` = statisches [Klasse, Methode]-Paar.
    // settings.billing_mode bleibt bewusst UNregistriert (punktloser
    // Top-Level-Key) und wird im Controller separat + Gate-gebunden geschrieben.
    // =====================================================================

    // --- Personalisierung (Org-Default für Datums-/Zeitformat) ---
    'personalization.date_format' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Support\Formats::class, 'dateOptions']],
    'personalization.time_format' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Support\Formats::class, 'timeOptions']],

    // --- E-Rechnung (Feature 045): Verkäuferstammdaten für XRechnung ---
    'einvoice.seller_name' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:200'],
    'einvoice.street' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:255'],
    'einvoice.zip' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:32'],
    'einvoice.city' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:128'],
    'einvoice.country' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'size:2'],
    'einvoice.vat_id' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:64'],
    'einvoice.tax_number' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:64'],
    'einvoice.contact_name' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:200'],
    'einvoice.contact_email' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'email|max:255'],
    'einvoice.contact_phone' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:64'],
    'einvoice.iban' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:64'],
    'einvoice.bic' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:32'],
    'einvoice.account_holder' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:200'],
    'einvoice.payment_terms_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:0|max:365'],
    'einvoice.small_business' => ['type' => 'boolean', 'scopes' => ['organization']],

    // --- Benachrichtigungen (config/notifications.php) ---
    'notifications.push.body_truncate' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:20|max:500'],

    // --- Routing/Karten (config/routing.php) ---
    'routing.nominatim.base_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    'routing.nominatim.email' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'email|max:255'],
    'routing.nominatim.rate_limit_per_sec' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:50'],
    'routing.osrm.base_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    'routing.osrm.profile' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:32'],
    'routing.osrm.timeout' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:120'],
    'routing.tiles.url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:255'],
    'routing.tiles.max_zoom' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:22'],

    // --- Zeiterfassung/Feiertage/Anwesenheit ---
    'timesheet.default_schedule_type' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\WorkSchedule\ScheduleType::class, 'values']],
    // Yasumi-Provider-Pfad (Feature 034); leer → config('holidays.provider').
    'holidays.provider' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Support\HolidayRegions::class, 'providers']],
    'attendance.self_correction' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['request', 'self']],

    // --- Fahrtkosten (config/travel.php) ---
    'travel.enabled' => ['type' => 'boolean', 'scopes' => ['organization']],
    'travel.mode' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['flat', 'km']],
    'travel.flat_amount' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:0'],
    'travel.rate_per_km' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:0'],
    'travel.km_source' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['company', 'tour']],
    'travel.round_trip' => ['type' => 'boolean', 'scopes' => ['organization']],
    'travel.origin_lat' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'between:-90,90'],
    'travel.origin_lng' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'between:-180,180'],
    'travel.label' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:50'],

    // --- Wartungsmodus pro Mandant (Rang 65) ---
    'maintenance.enabled' => ['type' => 'boolean', 'scopes' => ['organization']],
    'maintenance.message' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:300'],
    'maintenance.until' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'date'],
    'maintenance.block_ingest' => ['type' => 'boolean', 'scopes' => ['organization']],

    // --- Rechtstexte (config/legal.php, MVP-326) ---
    // Öffentliches Impressum/Datenschutzerklärung — betreiberspezifisch (nur
    // System-Scope); Klartext, Ausgabe HTML-escaped auf /impressum bzw. /datenschutz.
    'legal.imprint' => ['type' => 'text', 'scopes' => ['system'], 'rules' => 'nullable|max:65535'],
    'legal.privacy' => ['type' => 'text', 'scopes' => ['system'], 'rules' => 'nullable|max:65535'],

    // --- Öffentlicher Karrierebereich (Feature 068, MVP-437) ---
    // Je Organisation Opt-in (Default AUS). Öffentliche Stellen + sessionlose
    // Self-Service-Bewerbung; Einbettung nur für freigegebene HTTPS-Origins.
    'applications.portal.enabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    'applications.portal.privacy_notice_text' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:65535'],
    'applications.portal.privacy_notice_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|url|max:500'],
    // Zeilen-/kommagetrennte HTTPS-Origins für die Iframe-Einbettung (frame-ancestors).
    'applications.portal.embed_origins' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:2000'],
    'applications.portal.contact_email' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|email|max:190'],
];
