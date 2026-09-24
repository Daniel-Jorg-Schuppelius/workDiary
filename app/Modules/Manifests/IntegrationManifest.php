<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Integrationen, Import/Export, Sync“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class IntegrationManifest extends Manifest {
    public function code(): string {
        return 'integration';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Integrationen, Import/Export, Sync';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Integration',
            'Plugins',
            'Import',
            'Export',
            'Sync',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'billbee_orders',
            'caldav_connections',
            'calendly_connections',
            'calendly_webhook_deliveries',
            'calendly_webhook_subscriptions',
            'carddav_cards',
            'carddav_connections',
            'carrier_connections',
            'chat_webhooks',
            'cti_connections',
            'email_connections',
            'etsy_connections',
            'etsy_ledger_entries',
            'etsy_receipts',
            'etsy_webhook_deliveries',
            'export_runs',
            'external_article_mappings',
            'external_reference_aliases',
            'external_references',
            'google_calendar_connections',
            'import_run_errors',
            'import_runs',
            'import_value_mappings',
            'integration_inbox_items',
            'integration_outbox',
            'jtl_connections',
            'jtl_stock_snapshots',
            'jtl_warehouse_mappings',
            'lexoffice_articles',
            'lexoffice_invoice_handovers',
            'lexoffice_voucher_lines',
            'lexoffice_vouchers',
            'lexoffice_webhook_deliveries',
            'msgraph_connections',
            'msgraph_contact_connections',
            'msgraph_mail_connections',
            'msgraph_onenote_connections',
            'msgraph_task_connections',
            'msgraph_task_list_links',
            'orgamax_connections',
            'orgamax_invoices',
            'pending_external_conflicts',
            'sharepoint_connections',
            'sync_commands',
            'time_tracking_webhook_deliveries',
            'todoist_connections',
            'todoist_project_links',
            'todoist_section_links',
            'todoist_webhook_deliveries',
            'webdav_connections',
            'zammad_connections',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Plugins\Support\Mirror\Contracts\DocumentVersionImporter::class => \App\Plugins\Support\Mirror\Contracts\NullDocumentVersionImporter::class,
        ];
    }
}
