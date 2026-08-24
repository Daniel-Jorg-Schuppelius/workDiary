<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Integrations-Drehscheibe (MVP-103): Betreiber-Fristen, die bisher nur als
 * Code-Default existierten (Vollscan 2026-08-23, J14).
 */
return [
    // Aufgelöste Inbox-Einträge (resolved/dismissed) nach n Tagen löschen;
    // offene Einträge werden nie automatisch gelöscht (integration:purge-inbox).
    'inbox_retention_days' => (int) env('INTEGRATION_INBOX_RETENTION_DAYS', 90),
    // Zustell-/Outbox-Protokolle (Webhook-Deliveries, Integration-/Inventory-
    // Outbox): bestätigte/erfolgreiche Einträge nach n Tagen, endgültig
    // gescheiterte nach m Tagen (model:prune, Scheduler `retention.prune_models`).
    'delivery_retention_days' => (int) env('INTEGRATION_DELIVERY_RETENTION_DAYS', 90),
    'failed_retention_days' => (int) env('INTEGRATION_FAILED_RETENTION_DAYS', 180),
];
