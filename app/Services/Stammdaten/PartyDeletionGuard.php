<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDeletionGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Exceptions\PartyDeletionBlockedException;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Support\{EntityType, MorphMap};
use Illuminate\Database\Eloquent\Model;

/**
 * Sperre gegen das Löschen von Kunden und Lieferanten mit Belegen
 * (Vollscan 2026-10-21): Die Fremdschlüssel dieser Tabellen löschen per
 * ON DELETE CASCADE mit — an den Modell-Guards (GoBD) vorbei. Jede Kaskade
 * auf `customers`/`suppliers` steht in genau einer der beiden Listen
 * (Gate PartyDeletionCascadeRuleTest).
 */
final class PartyDeletionGuard {
    /** Belege und Vorgänge: solange eine Zeile existiert, bleibt die Partei. */
    public const BLOCKING = [
        'customers' => [
            'invoices', 'quotes', 'order_confirmations', 'billing_transfers', 'sepa_mandates',
            'disposal_jobs', 'rental_cases', 'rental_requests', 'customer_billing_agreements',
            'meter_billing_agreements', 'material_cost_allocations', 'customer_intakes', 'foreign_customers',
        ],
        'suppliers' => [
            'purchase_orders', 'claim_supplier_recourses', 'supplier_questionnaire_requests',
            'investment_supplier_ratings', 'supplier_credentials', 'self_billing_credits',
        ],
    ];

    /** Zubehör der Partei ohne eigene Aufbewahrung: geht mit ihr. */
    public const DELETED_WITH_PARTY = [
        'customers' => [
            'ai_memory_entries', 'b2b_catalog_accesses', 'customer_circular_recipients', 'customer_geofences',
            'invoice_schedules', 'invoice_sender_rules', 'sales_discount_group_overrides', 'sites',
        ],
        'suppliers' => [
            'article_supplies', 'invoice_sender_rules', 'pricing_margin_rules', 'supplier_catalog_items',
            'supplier_catalog_sources',
        ],
    ];

    /** @return list<string> Bezeichnungen der vorhandenen Belegarten */
    public function blockers(Customer|Supplier $party): array {
        $column = $party instanceof Customer ? 'customer_id' : 'supplier_id';
        $found = [];
        foreach (self::BLOCKING[$party->getTable()] ?? [] as $alias) {
            $class = MorphMap::classFor($alias);
            if (! is_string($class) || ! is_a($class, Model::class, true)) {
                continue;
            }
            if ($class::query()->withoutGlobalScopes()->where($column, $party->getKey())->exists()) {
                $found[] = EntityType::label($alias);
            }
        }

        return $found;
    }

    public function assertDeletable(Customer|Supplier $party): void {
        $blockers = $this->blockers($party);
        if ($blockers !== []) {
            throw new PartyDeletionBlockedException($blockers);
        }
    }
}
