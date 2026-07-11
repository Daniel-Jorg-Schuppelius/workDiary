<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * orgaMAX-Buchhaltung-Plugin (Feature 077). Nur die dokumentierte OpenAPI
 * (https://api.orgamax.de/openapi). Betreibergeheimnis (Marketplace-Modus)
 * kommt aus der Umgebung, nie aus der Datenbank.
 */
return [
    'enabled' => env('ORGAMAX_ENABLED', false),

    'base_url' => env('ORGAMAX_BASE_URL', 'https://api.orgamax.de/openapi'),

    // Betreibergeheimnis für die veröffentlichte WorkDiary-Erweiterung
    // (Marketplace-Modus); im privaten Pilotmodus je Org verschlüsselt.
    'operator_api_key' => env('ORGAMAX_OPERATOR_API_KEY'),
    'operator_api_secret' => env('ORGAMAX_OPERATOR_API_SECRET'),

    // Seitengröße der paginierten Reads (offset/limit).
    'page_size' => (int) env('ORGAMAX_PAGE_SIZE', 100),

    // Laufbudget: maximale Seiten je Ressource und Lauf.
    'sync_page_budget' => (int) env('ORGAMAX_SYNC_PAGE_BUDGET', 10),

    // Reconciliation-Fenster: wie viele jüngste Aufträge nach dem
    // workdiary:-Quellmarker durchsucht werden, bevor neu angelegt wird.
    'reconcile_scan_limit' => (int) env('ORGAMAX_RECONCILE_SCAN_LIMIT', 200),

    // Verbindungsabsicht (iid-Callback) läuft nach X Minuten ab.
    'intent_ttl_minutes' => (int) env('ORGAMAX_INTENT_TTL', 30),

    // Token-Erneuerung, wenn Restlaufzeit unter X Sekunden fällt.
    'token_refresh_window' => (int) env('ORGAMAX_TOKEN_REFRESH_WINDOW', 120),

    // MVP-312: Die dokumentierte Expense-Receipt-Übergabe ist widersprüchlich
    // (POST /expense/receipt fehlt im Pfadkatalog). Bis zum erfolgreichen
    // Pilot bleibt die Beleg-Übergabe blockiert — kein undokumentierter Call.
    'expense_receipt_contract_confirmed' => (bool) env('ORGAMAX_EXPENSE_RECEIPT_CONFIRMED', false),
];
