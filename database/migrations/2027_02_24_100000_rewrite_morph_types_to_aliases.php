<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_24_100000_rewrite_morph_types_to_aliases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Morph-Map (MVP-860): polymorphe Typspalten tragen den Alias (Tabellenname)
 * statt des Klassennamens. Umgeschrieben werden alle Morph-Paare des Schemas
 * außer den hash-verketteten Tabellen `audit_logs` und `audit_redactions`,
 * deren Zeilen nie verändert werden — sie führen den stabilen Schlüssel
 * ({@see MorphMap::stableKey()}). Werte, die keinem Legacy-Namen
 * entsprechen (Nicht-Modelle, Fremdschlüssel), bleiben stehen. Idempotent:
 * ein bereits umgeschriebener Wert ist kein Legacy-Name mehr.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string}> Tabelle, Typspalte — Stand des Schemas am 2026-09-23 */
    private const COLUMNS = [
        ['accounting_entries', 'source_type'],
        ['accounting_entry_lines', 'counterparty_type'],
        ['accounting_migration_items', 'referenceable_type'],
        ['accounting_open_items', 'counterparty_type'],
        ['accounting_open_items', 'source_type'],
        ['accounting_recurring_runs', 'fulfilled_by_type'],
        ['accounting_transfers', 'from_source_type'],
        ['accounting_transfers', 'to_source_type'],
        ['ai_text_suggestions', 'subject_type'],
        ['application_contract_negotiations', 'negotiable_type'],
        ['approval_steps', 'approvable_type'],
        ['approvals', 'approvable_type'],
        ['asset_blocks', 'source_type'],
        ['attachments', 'attachable_type'],
        ['automation_rule_runs', 'subject_type'],
        ['billing_transfer_items', 'source_type'],
        ['boq_catalog_assignments', 'assignable_type'],
        ['boq_item_mappings', 'mappable_type'],
        ['claim_actions', 'follow_up_type'],
        ['claim_case_links', 'linkable_type'],
        ['claim_evidence', 'evidencable_type'],
        ['classifiables', 'classifiable_type'],
        ['cloud_document_items', 'imported_type'],
        ['cloud_document_routes', 'target_ref_type'],
        ['collection_items', 'collectable_type'],
        ['comments', 'commentable_type'],
        ['communication_notes', 'notable_type'],
        ['compliance_findings', 'subject_type'],
        ['contact_addresses', 'addressable_type'],
        ['contact_bank_accounts', 'accountable_type'],
        ['content_references', 'source_type'],
        ['content_references', 'target_type'],
        ['crisis_case_links', 'linkable_type'],
        ['customer_queries', 'subject_type'],
        ['datev_booking_sources', 'source_type'],
        ['document_render_snapshots', 'documentable_type'],
        ['documents', 'documentable_type'],
        ['domain_provider_commands', 'subject_type'],
        ['etsy_ledger_entries', 'reference_type'],
        ['external_participants', 'subject_type'],
        ['external_reference_aliases', 'external_type'],
        ['external_reference_aliases', 'referenceable_type'],
        ['external_references', 'external_type'],
        ['external_references', 'referenceable_type'],
        ['fixed_assets', 'source_type'],
        ['form_submissions', 'subject_type'],
        ['integration_inbox_items', 'external_type'],
        // Kein Morph-Paar, trägt aber den Morph-Alias des Ziels (MatchProfileRegistry).
        ['integration_inbox_items', 'target_type'],
        ['integration_inbox_items', 'referenceable_type'],
        ['integration_inbox_items', 'resolved_to_type'],
        ['integration_outbox', 'subject_type'],
        ['investment_actuals', 'reference_type'],
        ['investment_links', 'linkable_type'],
        ['isms_assessment_snapshots', 'subject_type'],
        ['jtl_warehouse_mappings', 'warehouse_type'],
        ['learning_content_translations', 'translatable_type'],
        ['legal_holds', 'holdable_type'],
        ['maintenance_plans', 'subject_type'],
        ['material_cost_allocations', 'source_type'],
        ['model_has_permissions', 'model_type'],
        ['model_has_roles', 'model_type'],
        ['notification_dispatch_log', 'subject_type'],
        ['notifications', 'notifiable_type'],
        ['open_issues', 'subject_type'],
        ['payment_allocations', 'allocatable_type'],
        ['pending_external_conflicts', 'referenceable_type'],
        ['personal_access_tokens', 'tokenable_type'],
        ['privacy_attachments', 'attachable_type'],
        ['procedure_runs', 'subject_type'],
        ['procurement_requests', 'source_type'],
        ['protocols', 'subject_type'],
        ['rental_accessory_items', 'report_type'],
        ['rental_condition_items', 'report_type'],
        ['resale_period_links', 'linkable_type'],
        ['resale_purchase_entries', 'document_type'],
        ['retention_proposals', 'subject_type'],
        ['safety_events', 'subject_type'],
        ['search_documents', 'source_type'],
        ['service_requests', 'fulfilled_type'],
        ['service_ticket_links', 'linked_type'],
        ['service_ticket_messages', 'author_type'],
        ['service_tickets', 'requester_type'],
        ['stock_movements', 'source_type'],
        ['stock_reservations', 'source_type'],
        ['sustainability_activity_records', 'subject_type'],
        ['sustainability_assessments', 'subject_type'],
        ['taggables', 'taggable_type'],
        ['time_account_entries', 'source_type'],
        ['time_allocations', 'allocatable_type'],
        ['time_correction_items', 'target_type'],
    ];

    public function up(): void {
        $legacy = MorphMap::legacy();
        $this->rewrite(static function (string $value) use ($legacy): ?string {
            $class = $legacy[$value] ?? null;

            return $class === null ? null : MorphMap::alias($class);
        });
    }

    public function down(): void {
        $legacyByClass = [];
        foreach (MorphMap::legacy() as $name => $class) {
            $legacyByClass[$class] ??= $name;
        }
        $aliases = MorphMap::aliases();
        $this->rewrite(static function (string $value) use ($aliases, $legacyByClass): ?string {
            $class = $aliases[$value] ?? null;

            return $class === null ? null : $legacyByClass[$class] ?? null;
        });
    }

    /** @param callable(string): ?string $target liefert je gespeichertem Wert den neuen Wert oder null (unverändert lassen) */
    private function rewrite(callable $target): void {
        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $values = DB::table($table)->whereNotNull($column)->distinct()->pluck($column);
            foreach ($values as $value) {
                $value = (string) $value;
                $new = $target($value);
                if ($new === null || $new === $value) {
                    continue;
                }
                DB::table($table)->where($column, $value)->update([$column => $new]);
            }
        }
    }
};
