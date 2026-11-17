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
use App\Models\Customer\{Customer, ForeignCustomer};
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
    public const array BLOCKING = [
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
    public const array DELETED_WITH_PARTY = [
        'customers' => [
            'ai_memory_entries', 'b2b_catalog_accesses', 'customer_circular_recipients', 'customer_geofences',
            'invoice_schedules', 'invoice_sender_rules', 'sales_discount_group_overrides', 'sites',
            // Kundenfristen (Feature 197): Merkmale, Leistungsumfang und Fristen samt Verlauf hängen am Kunden.
            'customer_deadline_profiles', 'customer_deadlines', 'customer_services',
            // Leistungsplan (Feature 199): „entfällt“ sowie Unterlagen/Bemerkung je Zelle hängen am Kunden.
            'customer_service_period_notes', 'customer_service_waivers',
        ],
        'suppliers' => [
            'article_supplies', 'invoice_sender_rules', 'pricing_margin_rules', 'resale_provider_suppliers', 'supplier_catalog_items',
            'supplier_catalog_sources',
        ],
    ];

    /**
     * Vorgänge, deren Fremdschlüssel beim Löschen auf null fällt oder sperrt: Abos
     * verlören still ihren Halter, der Lizenzverkauf bricht mit einem Datenbankfehler.
     */
    public const array HOLDING = [
        'customers' => ['resale_subscriptions', 'resale_license_assignments', 'resale_account_allocations'],
        'foreign_customers' => ['resale_subscriptions', 'resale_license_assignments', 'resale_account_allocations'],
    ];

    /** @return list<string> Bezeichnungen der vorhandenen Belegarten */
    public function blockers(Customer|Supplier|ForeignCustomer $party): array {
        $column = match (true) {
            $party instanceof Customer => 'customer_id',
            $party instanceof ForeignCustomer => 'foreign_customer_id',
            default => 'supplier_id',
        };
        $found = [];
        foreach ([...(self::BLOCKING[$party->getTable()] ?? []), ...(self::HOLDING[$party->getTable()] ?? [])] as $alias) {
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

    public function assertDeletable(Customer|Supplier|ForeignCustomer $party): void {
        $blockers = $this->blockers($party);
        if ($blockers !== []) {
            throw new PartyDeletionBlockedException($blockers);
        }
    }
}
