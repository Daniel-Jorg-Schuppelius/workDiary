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
    'pagination.organizations' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:1|max:1000'],
    'pagination.tours' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.vehicles' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.tags' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.archive' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'pagination.notifications' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],

    // --- Uploads (config/uploads.php, KB wie Laravel-max-Regel) ---
    // Grenzen = historische Formulargrenzen (067-P3b, s. pagination).
    'uploads.csv_import_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],
    'uploads.customer_attachment_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],
    'uploads.attachment_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],
    'uploads.print_data_kb' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1048576'],

    // --- Archiv (config/archive.php) ---
    'archive.schedule_at' => ['type' => 'time', 'scopes' => ['system'], 'affects' => ['archive.run']],

    // --- Scheduler (config/scheduler.php) ---
    'scheduler.retention_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:7|max:365', 'affects' => ['scheduler.watchdog']],
    // Betriebsfenster (Ortszeit app.schedule_timezone): Tageszeit-Jobs außerhalb
    // rücken an den Beginn. Beginn = Ende oder leer = rund um die Uhr; Ende 00:00 = Mitternacht.
    'scheduler.operating_window_start' => ['type' => 'time', 'scopes' => ['system'], 'affects' => ['scheduler.*']],
    'scheduler.operating_window_end' => ['type' => 'time', 'scopes' => ['system'], 'affects' => ['scheduler.*']],

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
    'backup.restore_test_interval_days' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:7|max:365'],
    // Vollscan 2026-08-23 (J6): ab wie vielen Minuten ohne Worker-Heartbeat der
    // Betriebsalarm „Queue-Worker inaktiv" ausgelöst wird.
    'operations.queue_worker_silence_minutes' => ['type' => 'integer', 'scopes' => ['system'], 'rules' => 'min:2|max:1440', 'fallback' => 10],

    // --- Faktura/Anzeige (config/invoicing.php, config/ui.php) ---
    'invoicing.default_currency' => ['type' => 'string', 'scopes' => ['system', 'organization'], 'rules' => 'size:3'],
    'invoicing.default_tax_rate' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:8'],
    'invoicing.time_unit' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:8'],
    // Abrechnungs-Taktung: Org-Default, Kunde/Projekt können übersteuern (harter Default 1/0 im Trait)
    'invoicing.billing_increment_minutes' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:1440', 'fallback' => 1],
    'invoicing.billing_grouping_gap_minutes' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1440', 'fallback' => 0],
    // Standard-Erlös je Stunde (MVP-482): greift erst, wenn weder Eintrag,
    // Kondition, Mitarbeiter, Tätigkeit, Projekt noch Kunde einen Satz setzen.
    'invoicing.default_hourly_rate' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000'],
    // Stundensatz für Gerätelaufzeit (Feature 193): Satz je Laufzeitstunde eines
    // Geräts oder Prozesses; nie der Personensatz. Leer = 0,00 € mit Meldung.
    'invoicing.machine_hourly_rate' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000'],
    // Kalkulationsstundensatz Montage (Feature 107, MVP-602): bewertet die
    // Artikel-Montagezeit im VK-Vorschlag; Rückfall default_hourly_rate.
    'invoicing.assembly_hourly_rate' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000'],
    // Standardleistung (MVP-486): Artikel des Faktura-Systems, aus dem
    // Bezeichnung, Einheit, Standardtext, MwSt und — als Rückfall — der Preis
    // kommen. Projekt-Abrechnungsregeln überschreiben sie.
    // Katalogschlüssel der Standardleistung (`art:12`, `lex:34`, MVP-1026); nur je Organisation.
    'invoicing.default_service_article' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|max:80'],
    // Rechnungstexte der Übergabe (MVP-491): Vorlage für Einleitung und
    // Schlussbemerkung; Platzhalter :customer, :from, :to, :channel.
    // Girocode auf Rechnungs-PDFs (Feature 111, MVP-600).
    // Pflichtnachweise: Sperrt ein fehlender Nachweis die Beauftragung?
    // (Feature 117, MVP-606) — Default AUS, siehe config/procurement.php.
    'procurement.credential_blocking' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],

    // --- Lager (Feature 048, MVP-1579/1580) ---
    // Bewertungsverfahren: `service_managed` — nur ValuationMethodSwitch (Lager-Einstellungen) schreibt,
    // weil ein Wechsel den Bestand ins andere Bewertungsbuch übertragen muss.
    'inventory.valuation_method' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\Inventory\ValuationMethod::class, 'values'], 'fallback' => 'moving_average', 'service_managed' => true],
    // Zeitpunkt der Materialreservierung eines Fertigungsauftrags: Freigabe oder Start.
    'inventory.reservation_timing' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['release', 'start'], 'fallback' => 'release'],
    // Vier-Augen-Freigabe der Inventurdifferenzen (MVP-1585): ab dem Grenzwert (Bestandswert der
    // Differenz je Zeile) gibt nicht frei, wer gezählt hat. Grenzwert 0 = jede Differenz.
    'inventory.four_eyes.count' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    'inventory.four_eyes.count_threshold' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:999999999', 'fallback' => 0],
    // Vier-Augen-Freigabe für Entnahmen in den Negativbestand (MVP-1626): bucht erst nach Freigabe im Genehmigungs-Eingang.
    'inventory.four_eyes.negative' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],

    // --- Lieferantenbewertung (Feature 050, MVP-1581) ---
    // Gewichte der Teilkennzahlen als Anteil (0–1); die Summe muss nicht 1 sein, der Dienst normalisiert.
    'scorecard.weight.ontime' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.35],
    'scorecard.weight.complaints' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.30],
    'scorecard.weight.quality' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.20],
    'scorecard.weight.price' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.15],
    'scorecard.weight.recourse' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.15],
    'invoicing.girocode_enabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // RF-Gläubigerreferenz (ISO 11649, MVP-978) aus der Rechnungsnummer im Girocode und Zahlungshinweis.
    'invoicing.creditor_reference' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Arbeitskosten nach § 35a EStG (MVP-1053); Branchenprofile des Handwerks setzen „private_customers“.
    'invoicing.labour_cost_disclosure' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['off', 'private_customers', 'always'], 'fallback' => 'off'],
    // KI-Assistenten über MCP (MVP-1063/1065): Opt-in je Organisation, ab Werk aus —
    // ohne Freigabe keine Zustimmung, keine Tokenausgabe und keine Werkzeuge.
    'mcp.enabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Genehmigungs-Eingang (Entscheidung 2026-10-06): Stufen nach Art (Vertragsverhandlungen,
    // Investitionsanträge seit MVP-1522) erscheinen bei dieser Rolle; Vorgaben in config/approvals.php.
    'approvals.step_role.commercial' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\User\UserRole::class, 'values']],
    'approvals.step_role.technical' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\User\UserRole::class, 'values']],
    'approvals.step_role.hr' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\User\UserRole::class, 'values']],
    'approvals.step_role.management' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\User\UserRole::class, 'values']],
    // Online-Zahlung (MVP-1067): Anbieter, wenn mehrere Plugins aktiv sind (leer = erster
    // aktiver), und ob der Zahlungslink auf Rechnung und Mail steht (Portal immer).
    'payments.online.provider' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|string|max:40|regex:/^[a-z0-9_-]+$/'],
    'payments.online.on_documents' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => true],
    // Aufmaß (MVP-1058): Formelvorlagen des Gewerks als Schnellwahl im Blatt —
    // Liste aus {label, formula (REB-Nummer), unit, factor}; setzt das Branchenprofil.
    'takeoff.presets' => ['type' => 'json', 'scopes' => ['organization'], 'rules' => 'nullable', 'fallback' => []],
    // Lexware-Office-Tarifergänzungen (Feature 158, MVP-831): Tarifprofil je
    // Organisation — Orientierung, keine Berechtigung. Übergabewege entscheidet
    // die geprüfte Verfügbarkeit; `api` bleibt bis Paket A (MVP-834) an XL gebunden.
    'lexware.plan' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['unknown', 's', 'm', 'l', 'xl'], 'fallback' => 'unknown'],
    'lexware.plan_source' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['user', 'provider'], 'fallback' => 'user'],
    'lexware.plan_confirmed_on' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|date'],
    'lexware.trial_ends_on' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|date'],
    'lexware.trial_successor_plan' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['unknown', 's', 'm', 'l', 'xl'], 'fallback' => 'unknown'],
    'lexware.handover_channel' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['manual', 'api'], 'fallback' => 'manual'],
    'lexware.local_features' => ['type' => 'json', 'scopes' => ['organization'], 'rules' => 'nullable'],
    // Gläubiger-Identifikationsnummer für SEPA-Lastschriften (Feature 120,
    // MVP-609). Ohne sie weist die Bank jede pain.008-Datei zurück.
    'finance.sepa_creditor_id' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|max:35'],
    // Zollpapiere (MVP-1007): EORI-Nummer des Absenders — Ländercode + bis zu 15 Zeichen.
    'shipping.eori_number' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|max:17|regex:/^[A-Za-z]{2}[A-Za-z0-9]{1,15}$/'],
    // Führungsrichtung der Stammdaten (Feature 122, MVP-611). Ohne
    // Festlegung entsteht Ping-Pong: zwei Systeme überschreiben sich
    // gegenseitig. Default: workDiary führt, der Push ist erlaubt.
    'finance.master_data_authority' => ['type' => 'string', 'scopes' => ['organization'], 'options' => ['workdiary', 'accounting'], 'fallback' => 'workdiary'],
    // Vier-Augen-Prinzip im Buchungskern (Feature 125, MVP-673): Wer einen
    // Vorschlag vorbereitet hat, schreibt ihn dann nicht selbst fest. Default
    // aus — wer allein bucht, hätte sonst eine Sperre ohne Ausweg.
    'finance.accounting_four_eyes' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Steuerlich beraten (Feature 125, MVP-686): verlängert die Frist der
    // Umsatzsteuer-Jahreserklärung auf den letzten Februartag des
    // Zweitfolgejahres (§ 149 Abs. 3 AO). Eine Angabe, keine Ableitung —
    // ob eine Kanzlei mandatiert ist, weiß das Programm nicht.
    'finance.accounting_tax_advised' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Bankabgleich (Feature 045, MVP-1623): Rundungsdifferenz, die einen Betrag noch passen lässt, und
    // Skontoabzug, der ohne Skontokondition am Beleg als Vollzahlung gilt. Vorgaben wie MatchingService::DEFAULT_*.
    'finance.reconciliation.cent_tolerance' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1', 'fallback' => 0.02],
    'finance.reconciliation.skonto_percent' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10', 'fallback' => 3.0],
    // GWG und Sammelposten (MVP-892): Wertgrenzen netto, gesetzliche Werte prüft der Betrieb.
    'finance.fixed_assets.gwg_limit' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:100000', 'fallback' => 800],
    'finance.fixed_assets.pool_lower' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:100000', 'fallback' => 250],
    'finance.fixed_assets.pool_upper' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:100000', 'fallback' => 1000],
    'finance.fixed_assets.pool_years' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:20', 'fallback' => 5],
    // Degressive AfA (MVP-980): gesetzliche Anschaffungsfenster, plattformweit pflegbar.
    'finance.fixed_assets.declining_windows' => ['type' => 'json', 'scopes' => ['system'], 'rule_class' => App\Rules\DecliningWindows::class, 'fallback' => [
        ['from' => '2009-01-01', 'until' => '2010-12-31', 'factor' => '2.5', 'cap' => '25'],
        ['from' => '2020-01-01', 'until' => '2022-12-31', 'factor' => '2.5', 'cap' => '25'],
        ['from' => '2024-04-01', 'until' => '2024-12-31', 'factor' => '2', 'cap' => '20'],
        ['from' => '2025-07-01', 'until' => '2027-12-31', 'factor' => '3', 'cap' => '30'],
    ]],
    // Ersatzprognose (MVP-908): erwartete Preissteigerung je Jahr in Prozent.
    'finance.fixed_assets.replacement_inflation_pct' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:50', 'fallback' => 0],
    // Vier-Augen-Freigabe für Kundenrundschreiben (Feature 119). Default aus:
    // Wer allein arbeitet, hätte sonst eine Sperre ohne Ausweg.
    'communication.circular_approval' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Nachfass-Vorlauf in Werktagen (Feature 112, MVP-601); 0 = keine Vorbelegung.
    'invoicing.quote_follow_up_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:90', 'fallback' => 7],
    // Bindefrist in Kalendertagen ab Belegdatum (Vollscan 2026-10-21, tk-09).
    'invoicing.quote_validity_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:365', 'fallback' => 30],
    // Freigabe vor der Ausstellung (Vier-Augen, MVP-1562); liest InvoiceIssueService. Default aus:
    // Wer allein arbeitet, hätte sonst eine Sperre ohne Ausweg.
    'invoicing.require_approval' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    'invoicing.transfer_intro_text' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:2000'],
    'invoicing.transfer_closing_text' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:2000'],
    // Mahnwesen (Feature 127, MVP-691): Karenz/Gebühr/Zahlungsfrist je Stufe
    // + Verzugszins % p. a. (0 = aus). Defaults: config/invoicing.php.
    'invoicing.dunning.level1.grace_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:365', 'fallback' => 7],
    'invoicing.dunning.level1.fee' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'invoicing.dunning.level1.pay_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:90', 'fallback' => 14],
    'invoicing.dunning.level2.grace_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:365', 'fallback' => 7],
    'invoicing.dunning.level2.fee' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'invoicing.dunning.level2.pay_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:90', 'fallback' => 10],
    'invoicing.dunning.level3.grace_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:365', 'fallback' => 7],
    'invoicing.dunning.level3.fee' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'invoicing.dunning.level3.pay_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:90', 'fallback' => 7],
    'invoicing.dunning.interest_rate' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:30', 'fallback' => 0],
    'invoicing.dunning.interest_mode' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['fixed', 'base_rate'], 'fallback' => 'fixed'],
    'invoicing.dunning.interest_points' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:20', 'fallback' => 0],
    // Kundenportal (MVP-1237): sichtbare Portalzeiten in Kalenderjahren einschließlich des laufenden; 0 = unbegrenzt.
    'customer_portal.time_entry_years' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:50', 'fallback' => 3],
    // Mietbedingungen (MVP-895): Übergabe nur mit unterschriebener Fassung.
    'rental.require_signed_terms' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Direktbuchung im Kundenportal (MVP-916): freie, freigegebene Geräte ohne Anfrage reservieren.
    'rental.portal_direct_booking' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Online-Vorauszahlung der Direktbuchung (MVP-1515): Anteil am Bruttopreis, 0 = keine; Mietobjekte können abweichen.
    'rental.portal_prepayment_percent' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:100', 'fallback' => 0],
    // So lange hält eine unbezahlte Direktbuchung das Gerät (Minuten).
    'rental.prepayment_hold_minutes' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:10|max:1440', 'fallback' => 30],
    // Radius um den Einsatzort-Standort für die Geofence-Abweichung (MVP-975).
    'rental.geofence_radius_m' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:50|max:50000', 'fallback' => 500],
    // MVP-994: keine neue Fahrt mit einem Fahrzeug, dessen Pflichtprüfung (HU/UVV) überfällig ist.
    'fleet.block_trips_on_overdue_inspection' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // --- Einstellungen mit Schreibstelle (MVP-1589) ---
    // Abstand der Führerscheinkontrolle in Monaten.
    'fleet.license_check_interval_months' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:60', 'fallback' => 6],
    // Auslastung in Prozent, ab der die Frühwarnung „Kapazität“ meldet.
    'hr.capacity.threshold' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:50|max:300', 'fallback' => 100],
    // Investitionssumme, ab der die zweite Freigabestufe greift (Default config/investments.php).
    'investments.approval_threshold' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1000000000'],
    // Domainverwaltung (MVP-1614): Benachrichtigung vor teuren Verlängerungen und bei knappem Anbieterguthaben; leer = aus.
    'domain.renewal_cost_threshold' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:1000000'],
    'domain.balance_threshold' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'nullable|min:0|max:100000000'],
    // CSV-Export der Berichte (Default config/reports.php); Trennzeichen als Name, weil ein Komma keine Regeloption sein kann.
    'reports.csv_delimiter_name' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['semicolon', 'comma', 'tab']],
    'reports.csv_meta_lines' => ['type' => 'boolean', 'scopes' => ['organization']],
    // Löschfrist abgelehnter und zurückgezogener Bewerbungen in Monaten (Default config/applications.php).
    'applications.rejected_retention_months' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:36'],
    // Aufbewahrung abgeschlossener Datenschutzvorfälle in Jahren (MVP-1683); leer = Vorgabe des Rechtsraums
    // aus config/retention.php. 1 bis 10 Jahre: Art. 33 Abs. 5 DSGVO nennt keine Frist, mehr als die längste
    // gesetzliche Aufbewahrung (§ 147 AO) trägt die Speicherbegrenzung nicht.
    'privacy.incident_retention_years' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:10'],
    // Prioritätsmatrix des Helpdesks: Auswirkung (1–3) × Dringlichkeit (1–3) → Priorität; leere Zelle = Standard.
    'helpdesk.priority_matrix.1.1' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.1.2' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.1.3' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.2.1' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.2.2' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.2.3' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.3.1' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.3.2' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    'helpdesk.priority_matrix.3.3' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['low', 'normal', 'high', 'urgent']],
    // MVP-1514: fehlende Pflichtqualifikation nur anzeigen oder die Zuweisung (Schicht, Auftrag, Tour) ablehnen.
    'qualifications.enforcement' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['hint', 'block'], 'fallback' => 'hint'],
    // Reklamationsmuster (MVP-886): ab wie vielen gleichartigen Fällen in wie vielen Tagen.
    // Wiederkehrende Probleme (MVP-926): ab wie vielen Tickets je Kunde/Objekt in wie vielen Tagen.
    'reporting.recurring_tickets.threshold' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:2|max:50', 'fallback' => 3],
    'reporting.recurring_tickets.window_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:7|max:365', 'fallback' => 90],
    'claims.pattern.threshold' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:2|max:50', 'fallback' => 3],
    'claims.pattern.window_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:7|max:365', 'fallback' => 90],
    'ui.dashboard.recent_limit' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:1|max:1000'],
    'ui.calendar.slot_minutes' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'options' => [10, 15, 20, 30, 60]],
    // Neuigkeiten-Rail: externe Kommunikation bleibt installationsweit Opt-in.
    'ui.news_feed.enabled' => ['type' => 'boolean', 'scopes' => ['system'], 'fallback' => false, 'affects' => ['news-feed.refresh']],
    'ui.news_feed.url' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|url:http,https|max:2048', 'affects' => ['news-feed.refresh']],

    // --- Protokolle ---
    // GPS-Koordinaten aus den EXIF-Daten der Protokollfotos übernehmen (MVP-1573). Default aus (DSGVO):
    // Standortdaten sind personenbeziehbar; die Datei selbst wird immer bereinigt. Liest ProtocolController::uploadPhoto.
    'protocol.photo_geo' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],

    // --- Wetter/Zeiterfassung ---
    // weather.auto_fetch hat keinen config-Default (harter Default false in Protocol)
    'weather.auto_fetch' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Provider-Auswahl (Bauturbo A7/MVP-131): open-meteo (Default) oder dwd
    // (amtliche DWD-Open-Data-Tageswerte, CC BY 4.0, nur Deutschland).
    'weather.provider' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['open-meteo', 'dwd'], 'fallback' => 'open-meteo'],
    // DWD: maximale Entfernung zur nächsten Station — darüber hinaus lieber
    // kein Snapshot als falsche Daten.
    'weather.dwd_max_station_km' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:200', 'fallback' => 30],
    // Wetterwarnungen für disponierte Einsätze (Feature 062, MVP-716): Schalter +
    // Schwellen je Org; Defaults siehe WeatherWarningThreshold::defaultLimit().
    'weather.warnings_enabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => true],
    'weather.warn_rain_mm' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:0|max:500', 'fallback' => 20],
    'weather.warn_gust_kmh' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:0|max:300', 'fallback' => 60],
    'weather.warn_frost_c' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:-50|max:50', 'fallback' => 0],
    'weather.warn_heat_c' => ['type' => 'decimal', 'scopes' => ['organization'], 'rules' => 'min:-50|max:70', 'fallback' => 30],
    // edit_window_days: kein config-Default; null = kein Bearbeitungsfenster erzwungen
    'timesheet.edit_window_days' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:365'],
    // Schlüsselwort-Zuordnung importierter Zeiten (MVP-483)
    'project.keyword_matching.enabled' => ['type' => 'boolean', 'scopes' => ['system', 'organization'], 'fallback' => true],
    // Toleranz der Projekt-Sollzeiten (Feature 194): Minuten unter Soll, die noch keinen Befund ergeben.
    'project.target_tolerance_minutes' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:480', 'fallback' => 15],
    // Schwellen des Plan/Ist-Abgleichs der Anwesenheit (MVP-1552): später Beginn in Minuten, Stundenabweichung in Prozent.
    'plan_ist.late_start_minutes' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:240', 'fallback' => 15],
    'plan_ist.hours_diff_percent' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:100', 'fallback' => 10],

    // --- Reselling-Register (Feature 152, Serienlauf 2026-09-11) ---
    // Serienrechnung bei lokaler Rechnungshoheit: resale:draft-local legt je
    // Rechnungsempfänger einen lokalen Entwurf aus den fälligen Perioden an
    // (Default aus); lead_days = Perioden schon so viele Tage vor Beginn entwerfen.
    'resale.auto_local_drafts' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false, 'affects' => ['resale.draft_local']],
    'resale.last_proposal_run_at' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|string'],
    'resale.auto_local_drafts_lead_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:0|max:90', 'fallback' => 0, 'affects' => ['resale.draft_local']],
    // Serienlauf nach außen (Feature 185, MVP-1438): resale:draft-external legt monatlich je Empfänger mit
    // externer Rechnungshoheit eine Übergabe an sein Programm an (Default aus); Vorlauf 30 Tage = alles, was im
    // kommenden Monat fällig wird. Abos mit Serienvorlage im Programm bleiben außen vor.
    'resale.auto_external_drafts' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false, 'affects' => ['resale.draft_external']],
    'resale.auto_external_drafts_lead_days' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:0|max:90', 'fallback' => 30, 'affects' => ['resale.draft_external']],
    // Preisstaffel (Benutzerklasse des Domainportals), deren Preise für direkt berechnete Domains gelten (Feature 185, 2026-10-30); Subuser-Domains nehmen die Klasse des Subusers.
    'resale.domain_direct_price_class' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|string|max:64', 'fallback' => null],

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
    // Startseite je Rolle (MVP-799): nur Organisation; persönliche Wahl im Profil geht vor.
    'personalization.startpage_roles.admin' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.geschaeftsfuehrung' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.personalverwaltung' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.teamleitung' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.buchhaltung' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.user' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.aussendienst' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.callcenter' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.support' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],
    'personalization.startpage_roles.training_manager' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Services\Navigation\StartPageResolver::class, 'routeOptions']],

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
    // Ausstellung nur mit bestandener E-Rechnungs-Validierung (MVP-1562); liest InvoiceIssueService.
    'einvoice.enforce_validation' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    // Rechnungsbegründende Unterlagen und Empfängerprofil (Feature 196, MVP-1486). Die Grenzen
    // je Profil stehen in config/einvoice-profiles.php, der Kunde kann sie überschreiben.
    'einvoice.recipient_profile_default' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['standard', 'public_sector'], 'fallback' => 'standard'],
    'einvoice.attach_activity_report' => ['type' => 'boolean', 'scopes' => ['organization']],
    'einvoice.activity_report_include_signed_timesheets' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => true],
    'einvoice.activity_report_csv' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    'einvoice.activity_report_person_display' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['full_name', 'initials', 'none'], 'fallback' => 'full_name'],
    // Rechnungseingang (MVP-1487): je eingebettete Unterlage, gesamt 50 MB und 200 Dateien fest.
    'einvoice.incoming_attachment_limit_mb' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:50', 'fallback' => 15],
    // Externe Rechnungshoheit (Feature 196): wer stellt zu, und ist der automatische Versand im Fakturasystem abgeschaltet?
    'einvoice.external_dispatch_route' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['provider', 'workdiary'], 'fallback' => 'provider'],
    'einvoice.provider_send_disabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],

    // --- Benachrichtigungen (config/notifications.php) ---
    'notifications.push.body_truncate' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:20|max:500'],
    // SMS-Kanal (Feature 147, MVP-730): Kürzung nie über einem GSM-7-Segment;
    // Monatsdeckel in Segmenten (leer = unbegrenzt) mit Kostenwarnschwelle.
    'notifications.sms.body_truncate' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:20|max:160'],
    'notifications.sms.monthly_limit' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|min:0|max:100000'],
    'notifications.sms.warn_percent' => ['type' => 'integer', 'scopes' => ['system', 'organization'], 'rules' => 'min:0|max:100'],

    // --- Routing/Karten (config/routing.php) ---
    'routing.nominatim.base_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    'routing.nominatim.email' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'email|max:255'],
    'routing.nominatim.rate_limit_per_sec' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:50'],
    'routing.osrm.base_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    // Auditiertes SSRF-Opt-in: Nominatim/OSRM laufen häufig selbst gehostet im
    // eigenen Netz. Ohne diesen Schalter weist die zentrale Schranke der
    // PluginHttpFactory private Ziele ab (Sicherheitsscan 2026-08-23, S-10).
    'routing.allow_private_network' => ['type' => 'boolean', 'scopes' => ['organization']],
    'routing.osrm.profile' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'max:32'],
    'routing.osrm.timeout' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:120'],
    // VROOM (Tourvorschlag, MVP-1178): leer = nicht konfiguriert, die Heuristik rechnet.
    'routing.vroom.base_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    'routing.vroom.timeout' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:600'],
    // 'url' wie bei nominatim/osrm: der Wert landet als Origin in der
    // CSP jeder Antwort der Organisation (Sicherheitsscan 2026-08-23, S-05).
    // Die Platzhalter {z}/{x}/{y} im Pfad stören die Regel nicht.
    'routing.tiles.url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'url|max:255'],
    'routing.tiles.max_zoom' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:1|max:22'],
    // Quellenvermerk des eigenen Kachelservers; die Karte setzt ihn als HTML, darum ohne spitze Klammern.
    'routing.tiles.attribution' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => ['max:255', 'not_regex:/[<>]/']],

    // --- Zeiterfassung/Feiertage/Anwesenheit ---
    'timesheet.default_schedule_type' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Enums\WorkSchedule\ScheduleType::class, 'values']],
    // Yasumi-Provider-Pfad (Feature 034); leer → config('holidays.provider').
    'holidays.provider' => ['type' => 'enum', 'scopes' => ['organization'], 'options_from' => [App\Support\HolidayRegions::class, 'providers']],
    // Sprache der Feiertagsnamen (Yasumi-Locale) für die angebotenen Regionen; leer → config('holidays.locale').
    'holidays.locale' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['de_DE', 'de_AT', 'de_CH', 'fr_CH', 'it_CH', 'en_US', 'fr_FR', 'it_IT', 'es_ES']],
    'attendance.self_correction' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['request', 'self']],
    // Zuschlags-Stacking (Feature 005; Audit 2026-08, W4.3): 'highest' = es
    // gewinnt der hoechste Prozentsatz (Bestandsverhalten, Default),
    // 'add' = ueberlappende Zuschlaege werden addiert (nach §3b EStG bei
    // Nacht+Sonntag zulaessig; steuerliche Praxis vorher pruefen).
    'surcharge.stacking' => ['type' => 'enum', 'scopes' => ['organization'], 'options' => ['highest', 'add']],

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
    // --- Kommunikation: Fußzeile unter Mails, die Personen aus WorkDiary schreiben (Antwort aus der Notiz, Ticket-Antwort) ---
    'communication.mail_footer' => ['type' => 'text', 'scopes' => ['system', 'organization'], 'rules' => 'nullable|max:4000'],

    'legal.imprint' => ['type' => 'text', 'scopes' => ['system'], 'rules' => 'nullable|max:65535'],
    'legal.privacy' => ['type' => 'text', 'scopes' => ['system'], 'rules' => 'nullable|max:65535'],
    'legal.accessibility' => ['type' => 'text', 'scopes' => ['system'], 'rules' => 'nullable|max:65535'],
    // Vorhandene Seite des Betreibers: gesetzt → /impressum bzw. /datenschutz
    // leiten dorthin weiter, der Text oben greift nicht. Die Barrierefreiheits-
    // erklärung bleibt intern, sie betrifft diese Anwendung selbst.
    'legal.imprint_url' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|url:http,https|max:2048'],
    'legal.privacy_url' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|url:http,https|max:2048'],

    // --- Öffentlicher Karrierebereich (Feature 068, MVP-437) ---
    // Je Organisation Opt-in (Default AUS). Öffentliche Stellen + sessionlose
    // Self-Service-Bewerbung; Einbettung nur für freigegebene HTTPS-Origins.
    'applications.portal.enabled' => ['type' => 'boolean', 'scopes' => ['organization'], 'fallback' => false],
    'applications.portal.privacy_notice_text' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:65535'],
    'applications.portal.privacy_notice_url' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|url|max:500'],
    // Zeilen-/kommagetrennte HTTPS-Origins für die Iframe-Einbettung (frame-ancestors).
    'applications.portal.embed_origins' => ['type' => 'text', 'scopes' => ['organization'], 'rules' => 'nullable|max:2000'],
    'applications.portal.contact_email' => ['type' => 'string', 'scopes' => ['organization'], 'rules' => 'nullable|email|max:190'],

    // --- KI-Monatsbudget je Familie (Feature 025, MVP-399): LLM in Token, Übersetzung in Zeichen; leer → config('ai.budget') ---
    'ai.budget.monthly_units.llm' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:0|max:2000000000'],
    'ai.budget.monthly_units.translation' => ['type' => 'integer', 'scopes' => ['organization'], 'rules' => 'min:0|max:2000000000'],

    // --- Nutzungsabrechnung je Mandant (MVP-956): Einheitspreise des Betreibers ---
    'platform_billing.base_fee' => ['type' => 'decimal', 'scopes' => ['system'], 'rules' => 'nullable|min:0|max:100000', 'fallback' => 0],
    'platform_billing.per_user' => ['type' => 'decimal', 'scopes' => ['system'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'platform_billing.per_active_user' => ['type' => 'decimal', 'scopes' => ['system'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'platform_billing.per_gb' => ['type' => 'decimal', 'scopes' => ['system'], 'rules' => 'nullable|min:0|max:10000', 'fallback' => 0],
    'platform_billing.currency' => ['type' => 'string', 'scopes' => ['system'], 'rules' => 'nullable|size:3', 'fallback' => 'EUR'],
];
