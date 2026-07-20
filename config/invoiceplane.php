<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoiceplane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * InvoicePlane-Faktura-Plugin (Feature 086, MVP-418..430).
 *
 * Diese Datei ist die **Versions-/Capability-/Schema-Matrix** (MVP-418): sie
 * legt fest, welche InvoicePlane-Hauptversion produktiv angebunden werden darf,
 * welche Tabellen lesend projiziert werden dürfen und welche Pflichtspalten der
 * Schema-Preflight erwartet. Werte stammen aus den offiziellen 1.x-Quellen
 * (ip_*-Schema), nicht aus dem Produktnamen.
 *
 * Der **produktive Adapter, die Bridge-Schreibbefehle und der reale Pilot**
 * (MVP-421..430) hängen an einer echten InvoicePlane-Instanz und der
 * InvoicePlane-seitigen Bridge-Extension; sie werden erst nach Pilotfreigabe
 * scharf geschaltet. Ohne Bridge bleibt der Zugriff lesend (Blocked-State).
 */
return [
    // Modulschalter; die eigentliche Freigabe je Organisation läuft über die
    // verschlüsselte Verbindungskonfiguration + Preflight.
    'enabled' => (bool) env('INVOICEPLANE_ENABLED', false),

    // Sicherer Verbindungsrahmen (MVP-419). Öffentlich-routbare Hosts sind NUR
    // erlaubt, wenn sie hier gelistet sind; private Hosts/VPN/Connector sind
    // ohne Allowlist zulässig. DNS-Rebinding-/SSRF-Schutz über UrlSafety.
    'host_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INVOICEPLANE_HOST_ALLOWLIST', '')),
    ))),
    'require_tls' => (bool) env('INVOICEPLANE_REQUIRE_TLS', true),

    // Datensparsame Lesezugriffe: kleine Budgets, Pagination, Timeouts.
    'query' => [
        'timeout_seconds' => (int) env('INVOICEPLANE_QUERY_TIMEOUT', 5),
        'page_size' => 500,
        'max_rows' => 100_000,
    ],

    // Zulässige Zeitdifferenz (Sekunden) zwischen WorkDiary und InvoicePlane-DB.
    'max_clock_drift_seconds' => 300,

    // Verbindliche Produkt-/Versionsgrenze.
    // Schlüssel bewusst dot-frei (`v1`/`v2`) — Laravel-Config-Dot-Notation.
    'versions' => [
        'v1' => [
            'status' => 'supported', // produktiver Erstadapter (1.x-Pilotstand)
            'version_prefixes' => ['1.'],
            'table_prefix_default' => 'ip_',
            // Tabellen (ohne Präfix), die in den Schema-Fingerprint einfließen.
            'fingerprint_tables' => ['clients', 'quotes', 'invoices', 'invoice_items', 'invoice_amounts', 'invoice_item_amounts', 'payments'],
            // Pflichtspalten; Fehlen führt zu einem sichtbaren Blocked-State.
            'required_columns' => [
                'clients' => ['client_id', 'client_name'],
                'quotes' => ['quote_id', 'client_id', 'quote_number', 'quote_status_id'],
                'invoices' => ['invoice_id', 'client_id', 'invoice_number', 'invoice_status_id'],
                'invoice_items' => ['item_id', 'invoice_id'],
                'invoice_amounts' => ['invoice_amount_id', 'invoice_id', 'invoice_item_subtotal', 'invoice_total', 'invoice_paid', 'invoice_balance'],
                'payments' => ['payment_id', 'invoice_id', 'payment_amount'],
            ],
        ],
        'v2' => [
            'status' => 'blocked', // develop, keine veröffentlichte Version/API
            'version_prefixes' => ['2.'],
            'reason' => 'InvoicePlane v2 hat keine veröffentlichte Version/API (nur develop) — kein belastbarer Vertrag.',
        ],
    ],

    // Nur diese Tabellen (ohne Präfix) dürfen lesend projiziert werden.
    'read_allowlist' => [
        'clients', 'client_custom', 'products', 'families', 'units', 'tax_rates',
        'projects', 'tasks', 'quotes', 'quote_items', 'quote_amounts',
        'invoices', 'invoice_items', 'invoice_amounts', 'invoice_item_amounts',
        'invoice_custom', 'payments', 'payment_methods', 'invoice_recurring',
    ],

    // Datenführerschaft je Capability — NIE als pauschaler Gesamtschalter
    // (Feldkonflikte landen in der Integrations-Inbox).
    'capabilities' => [
        'clients' => 'manual_review',
        'products' => 'invoiceplane_wins',
        'quotes' => 'single_system',
        'invoices' => 'invoiceplane_wins',
        'recurring' => 'invoiceplane_wins',
        'payments' => 'invoiceplane_wins',
        'gateways' => 'invoiceplane_wins',
        'templates' => 'invoiceplane_wins',
    ],
];
