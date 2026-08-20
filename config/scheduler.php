<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Scheduler-Job-Registry (Feature 067, MVP-175/180): Allowlist aller
 * planbaren Artisan-Kommandos mit Default-Plan (1:1 aus der früheren
 * routes/console.php migriert — Verhalten von Standardinstallationen
 * ändert sich NICHT), erlaubten Umplanungs-Kadenzen und Einstufung.
 *
 * Die UI (MVP-176) kann Jobs nur innerhalb von `allowed` umplanen oder
 * pausieren; freie Cron-Ausdrücke bleiben Betreiber-Funktion. Neue
 * wiederkehrende Jobs werden HIER registriert, nicht in
 * routes/console.php hartcodiert.
 *
 * Reine Arrays, keine Closures (config-cachebar).
 */

return [

    // Aufbewahrung der Laufnachweise (scheduled_job_runs) in Tagen;
    // via Settings-Registry (scheduler.retention_days) übersteuerbar.
    'retention_days' => (int) env('SCHEDULER_RUNS_RETENTION_DAYS', 30),

    'jobs' => [
        // --- Reklamations-Fristeneskalation (Feature 072, MVP-255) ---
        'claims.escalate' => [
            'command' => 'claims:escalate',
            'cadence' => ['type' => 'dailyAt', 'time' => '07:15'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Wiederkehrende Rechnungen (Phase 38, MVP-415) ---
        // Erzeugt ausschließlich ENTWÜRFE aus fälligen Abrechnungsplänen;
        // Ausstellung und Versand bleiben manuelle, auditierte Schritte.
        'invoicing.recurring' => [
            'command' => 'invoices:generate-recurring',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:15'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Kunden-Sonderkonditionen: Monatsrechnungen (Feature 098) ---
        // Fakturiert am Monatsersten den Vormonat aller invoice-Mode-Profile;
        // idempotent über exported — Nachläufe erzeugen keine Doppelbelege.
        'billing.account-invoices' => [
            'command' => 'customer-billing:generate-invoices',
            'cadence' => ['type' => 'cron', 'expression' => '25 5 1 * *'],
            'allowed' => ['cron', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Kunden-Sonderkonditionen: Retainer-Pauschalen an Lexoffice (Feature 098) ---
        // Erzeugt am Monatsersten die Vormonats-Pauschale je Retainer-Agreement
        // und übergibt sie an Lexoffice; idempotent über retainer_invoice_id.
        'billing.push-retainers' => [
            'command' => 'customer-billing:push-retainers',
            'plugin' => 'lexoffice',
            'cadence' => ['type' => 'cron', 'expression' => '35 5 1 * *'],
            'allowed' => ['cron', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 5,
        ],

        // --- KI-Betriebslauf (Feature 025/084, MVP-411) ---
        'ai.maintenance' => [
            'command' => 'ai:maintenance',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:40'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Betriebsaufgaben-Sync (Feature 041, MVP-058) ---
        'operations.scan' => [
            'command' => 'operations:scan',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 3,
        ],

        // --- Update-Verfügbarkeitsprüfung (Feature 022, MVP-054) ---
        'updates.check' => [
            'command' => 'updates:check',
            'cadence' => ['type' => 'dailyAt', 'time' => '06:30'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Optionale Neuigkeiten in der eingeklappten Hilfe-Rail ---
        // Der Command ist ohne Opt-in ein No-op; Seitenaufrufe greifen nur auf
        // seinen letzten erfolgreichen Cache-Stand zu.
        'news-feed.refresh' => [
            'command' => 'news-feed:refresh',
            'cadence' => ['type' => 'everyThirtyMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 1,
        ],

        // --- Quelltext-Integritätsprüfung (Feature 095, MVP-441) ---
        // Abschaltbar via INTEGRITY_CHECK_ENABLED (der Command prüft selbst).
        'security.integrity' => [
            'command' => 'integrity:verify --trigger=schedule',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:20'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],

        // --- Angriffserkennung: Schwellwert-Auswertung (Feature 096, MVP-445) ---
        'security.evaluate' => [
            'command' => 'security:evaluate',
            'cadence' => ['type' => 'everyFiveMinutes'],
            'allowed' => ['everyFiveMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 1,
        ],

        // --- Scheduler-Selbstüberwachung (MVP-177) ---
        'scheduler.watchdog' => [
            'command' => 'scheduler:watchdog',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],
        // --- GoBD-Integritätsnachweis (Bauturbo A17, MVP-335) ---
        // Rechnet alle Audit-Hash-Ketten (config/audit.php) nach; Exit-Code 1
        // bei Manipulation → Watchdog/Laufnachweis schlägt an. Quelle:
        // gobd-gap-analyse.md ("audit:verify in CI/Cron einhängen").
        'audit.verify' => [
            'command' => 'audit:verify',
            'cadence' => ['type' => 'dailyAt', 'time' => '02:30'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 15,
        ],

        // Vollaudit 2026-07 (H10): E-Mail-Eingang (MVP-117) — ohne diesen
        // Eintrag lief der Abruf nie automatisch.
        'mail.poll' => [
            'command' => 'mail:poll',
            'cadence' => ['type' => 'everyFiveMinutes'],
            'allowed' => ['everyFiveMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 3,
        ],

        // Vollaudit 2026-07 (H13): Domainverwaltung (Feature 083) — Bestands-
        // Sync und Provider-Ereignisse liefen nie automatisch.
        'domain.sync' => [
            'command' => 'domain:sync',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:40'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],
        'domain.events' => [
            'command' => 'domain:events',
            'cadence' => ['type' => 'everyThirtyMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 3,
        ],

        // Vollaudit 2026-07 (M19): MHD-Überwachung (Feature 048 E2) —
        // expiringUntil war zuvor toter Code ohne Aufrufer.
        'inventory.expiring_lots' => [
            'command' => 'inventory:expiring-lots',
            'cadence' => ['type' => 'dailyAt', 'time' => '06:10'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
            'expected_runtime_minutes' => 3,
        ],

        // Offene-Zeiten-Digest (MVP-461): wöchentlicher Hinweis an die
        // Buchhaltung bei Nachzüglern/überfälligen offenen Zeiten; ohne Befund
        // wird nichts verschickt.
        'finance.open_times_digest' => [
            'command' => 'finance:open-times-digest',
            'cadence' => ['type' => 'weeklyOn', 'day' => 1, 'time' => '06:40'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
            'expected_runtime_minutes' => 3,
        ],

        // Vollaudit 2026-07 (N17): zyklische Inventur (E6) — planbar und
        // automatisch statt nur manuell.
        'inventory.cycle_counts' => [
            'command' => 'inventory:cycle-counts',
            'cadence' => ['type' => 'weeklyOn', 'day' => 1, 'time' => '05:50'],
            'allowed' => ['dailyAt', 'weeklyOn', 'monthlyOn'],
            'criticality' => 'housekeeping',
            'expected_runtime_minutes' => 5,
        ],

        // --- Kern/Housekeeping (täglich) ---
        'archive.run' => [
            'command' => 'archive:run',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:00'],
            // Uhrzeit weiterhin über Setting archive.schedule_at steuerbar
            // (env ARCHIVE_SCHEDULE_AT bzw. system_settings-Override).
            'cadence_setting_key' => 'archive.schedule_at',
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
            'expected_runtime_minutes' => 30,
        ],
        'plans.purge' => [
            'command' => 'plans:purge',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:30'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
        ],
        'privacy.deadlines' => [
            'command' => 'privacy:deadlines',
            'cadence' => ['type' => 'dailyAt', 'time' => '06:00'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
        ],
        'location.purge_points' => [
            'command' => 'location:purge-points',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:45'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
        ],
        'integration.purge_inbox' => [
            'command' => 'integration:purge-inbox',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:00'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'housekeeping',
        ],
        'recurrence.generate' => [
            'command' => 'recurrence:generate',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:30'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
        ],
        'backup.check_restore' => [
            'command' => 'workdiary:backup:check-restore',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:00'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
        ],
        'maintenance.scan_due' => [
            'command' => 'maintenance:scan-due',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:30'],
            'allowed' => ['dailyAt', 'hourly'],
            'criticality' => 'core',
        ],
        'security.advisories_pull' => [
            'command' => 'security:advisories-pull',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:30'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'core',
        ],
        'privacy.retention_scan' => [
            'command' => 'privacy:retention-scan',
            'cadence' => ['type' => 'weeklyOn', 'time' => '04:30', 'day' => 1],
            'allowed' => ['weeklyOn', 'dailyAt', 'monthlyOn'],
            'criticality' => 'core',
        ],
        // --- ArbZG-Compliance-Verstoß-Persistenz (Feature 006, Welle D) ---
        // Persistiert die on-the-fly berechneten Verstöße revisionssicher
        // (Dedup, Auto-„behoben", Audit) je Organisation.
        'compliance.scan_findings' => [
            'command' => 'compliance:scan-findings',
            'cadence' => ['type' => 'dailyAt', 'time' => '01:30'],
            'allowed' => ['dailyAt', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],
        // --- Rollplan-Fortschreibung (MVP-522) ---
        // Erzeugt aus aktiven Rollplan-Zuweisungen Draft-Dienste für das
        // Planungsfenster; idempotent, manuelle Planung gewinnt.
        'shifts.roll_forward' => [
            'command' => 'shifts:roll-forward',
            'cadence' => ['type' => 'dailyAt', 'time' => '02:10'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],
        // --- Zeitkonten-Bebuchung (MVP-526) ---
        // Bebucht konfigurierte Zusatzkonten aus dem Bestand (append-only,
        // idempotent) inkl. Kappungsbuchungen beim Monatsabschluss.
        'accounts.post' => [
            'command' => 'accounts:post',
            'cadence' => ['type' => 'dailyAt', 'time' => '02:40'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],
        'events.check_certificates' => [
            'command' => 'events:check-certificates',
            'cadence' => ['type' => 'dailyAt', 'time' => '06:00'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
        ],
        'events.materialize_recurrences' => [
            'command' => 'events:materialize-recurrences',
            'cadence' => ['type' => 'dailyAt', 'time' => '02:00'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
        ],

        // --- Kern (minütlich/viertelstündlich) ---
        'chat.send_reminders' => [
            'command' => 'chat:send-reminders',
            'cadence' => ['type' => 'everyMinute'],
            'allowed' => ['everyMinute', 'everyFiveMinutes'],
            'criticality' => 'core',
            'on_one_server' => false, // Bestandsverhalten (kein onOneServer)
            'expected_runtime_minutes' => 1,
        ],
        'chat.send_scheduled' => [
            'command' => 'chat:send-scheduled',
            'cadence' => ['type' => 'everyMinute'],
            'allowed' => ['everyMinute', 'everyFiveMinutes'],
            'criticality' => 'core',
            'on_one_server' => false, // Bestandsverhalten (kein onOneServer)
            'expected_runtime_minutes' => 1,
        ],
        'attendance.close_open' => [
            'command' => 'attendance:close-open',
            'cadence' => ['type' => 'everyFifteenMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],
        'events.dispatch_reminders' => [
            'command' => 'events:dispatch-reminders',
            'cadence' => ['type' => 'everyFiveMinutes'],
            'allowed' => ['everyFiveMinutes', 'everyFifteenMinutes'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],
        'tickets.scan_sla_breaches' => [
            'command' => 'tickets:scan-sla-breaches',
            'cadence' => ['type' => 'everyFiveMinutes'],
            'allowed' => ['everyFiveMinutes', 'everyFifteenMinutes'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],
        'notifications.scan_deadlines' => [
            'command' => 'notifications:scan-deadlines',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'core',
        ],
        // Bekanntmachungs-Radar (Feature 108, MVP-629): Der Bund stellt einen
        // Veröffentlichungstag erst am Folgetag vollständig bereit — der Abruf
        // holt deshalb nachts den Vortag.
        'tenders.fetch_notices' => [
            'command' => 'tenders:fetch-notices',
            'cadence' => ['type' => 'dailyAt', 'time' => '05:15'],
            'allowed' => ['dailyAt', 'hourly'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'catalog.fetch_due' => [
            'command' => 'catalog:fetch-due',
            'cadence' => ['type' => 'everyFifteenMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 10,
        ],
        // Feature 107 (W3-Rest): fällige, vorgemerkte DATANORM-Preisstände aktivieren.
        'catalog.apply_pending_prices' => [
            'command' => 'catalog:apply-pending-prices',
            'cadence' => ['type' => 'dailyAt', 'time' => '02:20'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 2,
        ],

        // --- Integrationen (Plugins, stündlich) ---
        'plugin.healthcheck' => [
            'command' => 'plugin:healthcheck --no-fail',
            'plugin' => '*',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        // Aufbewahrung der Plugin-Fehler-Inbox (Review 2026-08, W2c):
        // quittierte nach 30, offene nach 90 Tagen (config/plugins.php).
        'plugin.errors_prune' => [
            'command' => 'model:prune --model=App\\Models\\PluginError',
            'plugin' => '*',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:40'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 2,
        ],
        'remote.sync_sessions' => [
            'command' => 'remote:sync-sessions',
            'plugin' => 'remote-support',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
        ],
        'toggl.import' => [
            'command' => 'toggl:import',
            'plugin' => 'toggl',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'toggl.push' => [
            'command' => 'toggl:push',
            'plugin' => 'toggl',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        'clockify.push' => [
            'command' => 'clockify:push',
            'plugin' => 'clockify',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        'openproject.import' => [
            'command' => 'openproject:import',
            'plugin' => 'openproject',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'todoist.sync' => [
            'command' => 'todoist:sync',
            'plugin' => 'todoist',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        // --- Calendly-Terminbuchung (Feature 095) ---
        'calendly.backfill' => [
            'command' => 'calendly:backfill',
            'plugin' => 'calendly',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // --- Cloud-Dokumenteingang (Feature 080, MVP-359) ---
        'cloud-intake.sync' => [
            'command' => 'cloud-intake:sync',
            // Plugin-IDs der Intake-Adapter (CloudIntakeProvider::pluginId) — Microsoft läuft
            // über das Msgraph-Plugin, nicht über das capability-lose Sharepoint-Mirror-Plugin.
            'plugin' => 'dropbox,google-drive,msgraph,nextcloud',
            'cadence' => ['type' => 'everyFifteenMinutes'],
            'allowed' => ['everyFiveMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // --- Cloud-Backupziele (Feature 017 Phase 32, MVP-364/365) ---
        'backup.cloud-run' => [
            'command' => 'workdiary:backup:run',
            'cadence' => ['type' => 'dailyAt', 'time' => '01:30'],
            'allowed' => ['dailyAt'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 60,
        ],
        'backup.cloud-verify' => [
            'command' => 'workdiary:backup:verify',
            'cadence' => ['type' => 'weeklyOn', 'day' => 6, 'time' => '03:30'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 30,
        ],
        // --- CardDAV-Kontakt-Lese-Sync (Bauturbo A9, MVP-329) ---
        'carddav.sync' => [
            'command' => 'carddav:sync',
            'plugin' => 'carddav',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // --- Kalender-Publish-Abgleich (MVP-126/328, Bauturbo A8/A11): täglicher
        // Voll-Publish als Reconciliation; Einzeltermine gehen weiterhin sofort
        // über den ereignisgetriebenen CalendarEventPublishJob raus. ---
        'caldav.publish' => [
            'command' => 'caldav:publish',
            'plugin' => 'caldav',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:35'],
            'allowed' => ['everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'msgraph.publish' => [
            'command' => 'msgraph:publish',
            'plugin' => 'msgraph',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:45'],
            'allowed' => ['everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        // Zwei-Wege-Kalender-Rückimport (Feature 102, C3; nur two_way-Opt-in).
        'msgraph.calendar-import' => [
            'command' => 'msgraph:calendar-import',
            'plugin' => 'msgraph',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // Microsoft-To-Do-Abgleich (Feature 102, Schnitt E; Todoist-Muster).
        'msgraph.todo-sync' => [
            'command' => 'msgraph:todo-sync',
            'plugin' => 'msgraph',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        // Graph-Change-Notification-Subscriptions des Dokumenteingangs
        // (MS365-Plan §8): täglich anlegen/erneuern (driveItem < 30 Tage).
        'msgraph.subscriptions' => [
            'command' => 'msgraph:subscriptions',
            'plugin' => 'msgraph',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:20'],
            'allowed' => ['hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 5,
        ],
        // Google-Drive-Push-Kanäle des Dokumenteingangs (Feature 080; Audit
        // 2026-08, W4.4): Kanäle laufen ~24 h und lassen sich NICHT
        // verlängern — deshalb dreimal täglich neu anlegen statt einmal wie
        // bei Graph.
        'google-drive.subscriptions' => [
            'command' => 'google-drive:subscriptions',
            'plugin' => 'google-drive',
            'cadence' => ['type' => 'cron', 'expression' => '35 */8 * * *'],
            'allowed' => ['cron', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 5,
        ],
        'google-calendar.publish' => [
            'command' => 'google-calendar:publish',
            'plugin' => 'google_calendar',
            'cadence' => ['type' => 'dailyAt', 'time' => '04:55'],
            'allowed' => ['everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'openproject.push' => [
            'command' => 'openproject:push',
            'plugin' => 'openproject',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'lexoffice.sync_contacts' => [
            'command' => 'lexoffice:sync-contacts',
            'plugin' => 'lexoffice',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'lexoffice.sync_articles' => [
            'command' => 'lexoffice:sync-articles',
            'plugin' => 'lexoffice',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'lexoffice.sync_vouchers' => [
            'command' => 'lexoffice:sync-vouchers',
            'plugin' => 'lexoffice',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        'jtl.sync' => [
            'command' => 'jtl:sync',
            'plugin' => 'jtl_wawi',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],
        // --- Billbee-Multichannel-Sync (Feature 093, MVP-433/434) ---
        'billbee.sync' => [
            'command' => 'billbee:sync',
            'plugin' => 'billbee',
            'cadence' => ['type' => 'everyFifteenMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // --- Etsy-Marktplatz-Sync (Feature 101, MVP-495/498) ---
        'etsy.sync' => [
            'command' => 'etsy:sync',
            'plugin' => 'etsy',
            'cadence' => ['type' => 'everyFifteenMinutes'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        // --- easybill-Beleg-Rückabruf (Feature 093, MVP-431) ---
        'easybill.sync' => [
            'command' => 'easybill:sync',
            'plugin' => 'easybill',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 10,
        ],
        'orgamax.sync' => [
            'command' => 'orgamax:sync',
            'plugin' => 'orgamax',
            'cadence' => ['type' => 'hourly'],
            'allowed' => ['everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'dailyAt'],
            'criticality' => 'integration',
            'expected_runtime_minutes' => 15,
        ],

        // --- Druck: Löschfristen der Produktionsdateien (MVP-459) ---
        // Entfernt nur die Kundendatei; Auftrag/Snapshot/Hash bleiben als
        // kaufmännischer Nachweis erhalten.
        'print.purge_files' => [
            'command' => 'print:purge-files',
            'cadence' => ['type' => 'dailyAt', 'time' => '03:45'],
            'allowed' => ['dailyAt', 'weeklyOn'],
            'criticality' => 'core',
            'expected_runtime_minutes' => 5,
        ],

        // --- Sonderpläne ---
        'payroll.import_minimum_wages' => [
            'command' => 'payroll:import-minimum-wages',
            'cadence' => ['type' => 'cron', 'expression' => '0 4 15 1,7 *'],
            'allowed' => ['cron', 'monthlyOn'],
            'criticality' => 'core',
        ],
    ],
];
