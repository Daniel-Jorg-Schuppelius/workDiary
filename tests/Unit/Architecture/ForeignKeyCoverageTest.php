<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignKeyCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für Fremdschlüssel (Vollscan 2026-08-23, F12): Spalten wie
 * appointment_requests.lead_id oder procedure_deviations.open_issue_id wurden
 * „bis Feature X" ohne FK angelegt — die Zieltabellen existieren längst, der
 * Vorhalt verwaiste. Regel: Eine neue `…_id`-Spalte trägt einen FK oder steht
 * mit Begründung in der BASELINE (Hash-Ketten-Tabellen bewusst ohne FK,
 * externe/Plugin-IDs, Morph-Spalten). Abgearbeitete Einträge werden gestrichen.
 *
 * Ausgenommen per Namensmuster: Morph-Spalten (*able_id), externe/Remote-/
 * Plugin-IDs, source/subject/counterparty/target/reference (polymorph oder
 * fachliche Kennung statt Relation).
 */
class ForeignKeyCoverageTest extends TestCase {
    use ScansSourceTree;

    private const EXCLUDED_PATTERN = '/(able_id$|external|plugin_id|end_to_end|remote_|^source_id$|^subject_id$|counterparty_id|_external_id|^provider_id$|^channel_id$|^message_id$|resource_id|subscription_id|^thread_id$|^tracking_id$|^session_id$|^request_id$|^object_id$|^record_id$|^item_id$|^entity_id$|^reference_id$|^primary_source_id$|^from_source_id$|^to_source_id$|^target_id$|^anchor_id$)/';

    /** @var list<string> table.column ohne FK — Stand 2026-08-23 (Welle 3, F12: appointment_requests/procedure_deviations zuerst) */
    private const BASELINE = [
        // F7 (2027_02_19_100600): audit_logs ist eine Hash-Kette — FKs mit
        // SET NULL hätten beim Org-Purge die Kette gebrochen; Werte bleiben.
        'audit_logs.organization_id',
        'audit_logs.user_id',
        // Keine Referenz: USt-IdNr.-WERT aus der Rechnung (E3, Lieferanten-Matching).
        'incoming_einvoices.seller_vat_id',
        'accounting_events.accounting_entry_id',
        'accounting_events.actor_user_id',
        'accounting_events.organization_id',
        'accounting_migration_events.accounting_migration_run_id',
        'accounting_migration_events.actor_user_id',
        'accounting_migration_events.organization_id',
        'accounting_open_item_settlements.payment_allocation_id',
        'accounting_recurring_runs.fulfilled_by_id',
        'article_merge_dismissals.article_high_id',
        'article_merge_dismissals.article_low_id',
        'attendances.open_user_id',
        'billbee_orders.billbee_order_id',
        'billing_transfer_events.actor_user_id',
        'billing_transfer_events.billing_transfer_id',
        'billing_transfer_events.organization_id',
        'billing_transfer_positions.article_id',
        'claim_actions.follow_up_id',
        'cloud_document_connections.container_id',
        'cloud_document_connections.root_folder_id',
        'cloud_document_items.imported_id',
        'cloud_document_routes.target_ref_id',
        'communication_note_participants.customer_contact_id',
        'customer_merge_dismissals.customer_high_id',
        'customer_merge_dismissals.customer_low_id',
        'customers.vat_id',
        'datev_booking_events.actor_user_id',
        'datev_booking_events.datev_booking_batch_id',
        'datev_booking_events.organization_id',
        'diary_entries.legacy_id',
        'documents.current_version_id',
        'domain_accounting_entries.accounting_id',
        'domain_provider_commands.command_id',
        'emergency_assignments.legacy_id',
        'etsy_connections.etsy_user_id',
        'etsy_connections.shop_id',
        'etsy_ledger_entries.ledger_entry_id',
        'etsy_ledger_entries.receipt_id',
        'etsy_receipts.receipt_id',
        'etsy_webhook_deliveries.receipt_id',
        'etsy_webhook_deliveries.webhook_id',
        'expense_categories.accounting_category_id',
        'google_calendar_connections.calendar_id',
        'integration_inbox_items.resolved_to_id',
        'isms_assessment_snapshots.isms_scope_id',
        'jtl_connections.app_id',
        'jtl_connections.client_id',
        'jtl_connections.company_id',
        'jtl_connections.registration_id',
        'jtl_connections.tenant_id',
        'jtl_warehouse_mappings.jtl_warehouse_id',
        'location_points.ingest_batch_id',
        'model_has_permissions.model_id',
        'model_has_permissions.team_id',
        'model_has_roles.model_id',
        'model_has_roles.team_id',
        'msgraph_connections.calendar_id',
        'msgraph_task_list_links.todo_list_id',
        'on_call_shifts.legacy_id',
        'open_issues.source_ref_id',
        'orgamax_connections.ownership_id',
        'organization_audit_logs.actor_user_id',
        'organization_audit_logs.organization_id',
        'payment_reconciliation_events.actor_user_id',
        'payment_reconciliation_events.bank_transaction_id',
        'payment_reconciliation_events.organization_id',
        'privacy_incident_events.actor_user_id',
        'privacy_incident_events.incident_id',
        'privacy_incident_events.organization_id',
        'privacy_processing_activities.current_version_id',
        'privacy_request_events.actor_user_id',
        'privacy_request_events.organization_id',
        'privacy_technical_measures.current_version_id',
        'procedure_backup_proofs.attachment_id',
        'procedure_step_runs.deviation_id',
        'procedure_step_runs.proof_attachment_id',
        'project_billing_rules.lexoffice_article_id',
        'project_merge_dismissals.project_high_id',
        'project_merge_dismissals.project_low_id',
        'rental_accessory_items.report_id',
        'rental_condition_items.report_id',
        'report_targets.scope_id',
        'restore_tests.performed_by_user_id',
        'roles.team_id',
        'service_requests.fulfilled_id',
        'service_ticket_links.linked_id',
        'service_ticket_messages.author_id',
        'service_tickets.requester_id',
        'sessions.user_id',
        'sharepoint_connections.drive_id',
        'sharepoint_connections.site_id',
        'shipments.carrier_shipment_id',
        'software_installations.os_asset_id',
        'sso_connections.client_id',
        'sso_connections.idp_entity_id',
        'supplier_merge_dismissals.supplier_high_id',
        'supplier_merge_dismissals.supplier_low_id',
        'suppliers.vat_id',
        'tender_notices.notice_id',
        'time_exports.scope_team_id',
        'time_tracking_webhook_deliveries.delivery_id',
        'todoist_connections.todoist_user_id',
        'todoist_project_links.todoist_project_id',
        'todoist_section_links.todoist_section_id',
        'todoist_webhook_deliveries.delivery_id',
        'two_factor_credentials.credential_id',
        'users.legacy_user_id',
        'whistleblowing_case_events.actor_user_id',
        'whistleblowing_case_events.case_id',
        'whistleblowing_case_events.organization_id',
        'whistleblowing_case_tombstones.organization_id',
        'whistleblowing_case_tombstones.public_id',
        'whistleblowing_cases.public_id',
    ];

    public function test_id_columns_carry_a_foreign_key_or_a_documented_exception(): void {
        $current = [];
        foreach ($this->schemaTables() as $table => $definition) {
            foreach (array_keys($definition['columns']) as $column) {
                if ($column === 'id' || ! str_ends_with($column, '_id') || preg_match(self::EXCLUDED_PATTERN, $column) === 1) {
                    continue;
                }
                if (! isset($definition['foreign'][$column])) {
                    $current[] = $table . '.' . $column;
                }
            }
        }
        sort($current);

        $new = array_values(array_diff($current, self::BASELINE));
        $this->assertSame([], $new, "Neue _id-Spalte ohne Fremdschlüssel:\n" . implode("\n", $new)
            . "\n\nFK mit kurzem explizitem Namen anlegen (nullOnDelete/restrictOnDelete) oder — bei bewusstem Verzicht (Hash-Kette, externe ID) — mit Begründung in die BASELINE.");

        $stale = array_values(array_diff(self::BASELINE, $current));
        $this->assertSame([], $stale, "Aus der BASELINE streichen (FK inzwischen vorhanden oder Spalte weg):\n" . implode("\n", $stale));
    }
}
