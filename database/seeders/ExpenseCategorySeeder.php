<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategorySeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\{ExpenseCategory, Organization};
use Illuminate\Database\Seeder;

/**
 * Erstausstattung der Spesenkategorien je Organisation. Bootstrap-only wie
 * der EntryTypeSeeder: deploy.sh seedet bei jedem Deploy — Orgs mit
 * vorhandenen Kategorien werden nie angefasst (updateOrCreate setzte vorher
 * Label/Steuersatz/is_active zurück; Vollscan 2026-08-23, J3). Neue Orgs
 * laufen über den OrganizationObserver.
 */
class ExpenseCategorySeeder extends Seeder {
    public function run(): void {
        $orgIds = Organization::query()->pluck('id')->all();
        if ($orgIds === []) {
            $orgIds = [null];
        }

        foreach ($orgIds as $orgId) {
            self::seedOrganization($orgId === null ? null : (int) $orgId);
        }
    }

    /** Legt die Profile nur an, wenn die Organisation noch keine Kategorie hat. */
    public static function seedOrganization(?int $organizationId): void {
        $exists = ExpenseCategory::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->exists();
        if ($exists) {
            return;
        }

        foreach (self::profiles() as $sort => $profile) {
            ExpenseCategory::query()->withoutGlobalScopes()->create(
                array_merge($profile, ['organization_id' => $organizationId, 'sort' => $sort])
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function profiles(): array {
        return [
            [
                'slug' => ExpenseCategory::SLUG_MEALS,
                'label' => 'Verpflegung',
                'icon' => 'restaurant',
                'color' => 'warning',
                'description' => 'Verpflegungsmehraufwand, Restaurantbesuche auf Dienstreise.',
                'default_tax_rate' => 7,
                'default_billable' => false,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_LODGING,
                'label' => 'Übernachtung',
                'icon' => 'hotel',
                'color' => 'info',
                'description' => 'Hotel- und Übernachtungskosten.',
                'default_tax_rate' => 7,
                'default_billable' => true,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_HOSPITALITY,
                'label' => 'Bewirtung',
                'icon' => 'local_bar',
                'color' => 'secondary',
                'description' => 'Geschäftliche Bewirtung von Kunden/Partnern.',
                'default_tax_rate' => 19,
                'default_billable' => false,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_TICKETS,
                'label' => 'Eintritt / Tickets',
                'icon' => 'confirmation_number',
                'color' => 'primary',
                'description' => 'Bahn-, Flug-, ÖPNV-Tickets, Eintrittsgelder.',
                'default_tax_rate' => 19,
                'default_billable' => true,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_PARKING,
                'label' => 'Parken / Maut',
                'icon' => 'local_parking',
                'color' => 'neutral',
                'description' => 'Parkgebühren, Maut, Vignetten.',
                'default_tax_rate' => 19,
                'default_billable' => true,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_SUPPLIES,
                'label' => 'Material / Verbrauch',
                'icon' => 'inventory_2',
                'color' => 'accent',
                'description' => 'Verbrauchsmaterial, Kleinwerkzeug, Büromaterial.',
                'default_tax_rate' => 19,
                'default_billable' => true,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_TELECOM,
                'label' => 'Telekommunikation',
                'icon' => 'call',
                'color' => 'info',
                'description' => 'Telefon-, Internet-, Roaming-Kosten.',
                'default_tax_rate' => 19,
                'default_billable' => false,
                'requires_receipt' => true,
                'is_active' => true,
            ],
            [
                'slug' => ExpenseCategory::SLUG_OTHER,
                'label' => 'Sonstiges',
                'icon' => 'more_horiz',
                'color' => 'ghost',
                'description' => 'Nicht anderweitig zugeordnete Auslagen.',
                'default_tax_rate' => 19,
                'default_billable' => false,
                'requires_receipt' => true,
                'is_active' => true,
            ],
        ];
    }
}
