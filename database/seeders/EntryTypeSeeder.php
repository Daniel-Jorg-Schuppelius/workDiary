<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\{EntryType, Organization};
use Illuminate\Database\Seeder;

class EntryTypeSeeder extends Seeder {
    public function run(): void {
        $orgIds = Organization::query()->pluck('id')->all();
        if ($orgIds === []) {
            $orgIds = [null];
        }

        foreach ($orgIds as $orgId) {
            foreach (self::profiles() as $sort => $profile) {
                $attrs = ['organization_id' => $orgId, 'slug' => $profile['slug']];
                EntryType::query()->withoutGlobalScopes()->updateOrCreate(
                    $attrs,
                    array_merge($profile, ['sort' => $sort])
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function profiles(): array {
        return [
            [
                'slug' => EntryType::SLUG_GENERAL,
                'label' => 'Allgemein',
                'icon' => 'assignment',
                'color' => 'primary',
                'description' => 'Standard-Eintrag ohne Pflichtfelder.',
                'is_active' => true,
                'requires_customer' => false,
                'requires_address' => false,
                'requires_schedule' => false,
                'requires_tour' => false,
                'allow_priority' => true,
                'allow_tour' => false,
                'default_status' => 2,
                'default_service_minutes' => null,
                'default_priority' => null,
            ],
            [
                'slug' => EntryType::SLUG_SERVICE,
                'label' => 'Service-Auftrag',
                'icon' => 'home_repair_service',
                'color' => 'info',
                'description' => 'Strukturierter Außendienst-Auftrag mit Kunde, Adresse und Termin.',
                'is_active' => true,
                'requires_customer' => true,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_tour' => false,
                'allow_priority' => true,
                'allow_tour' => true,
                'default_status' => 2,
                'default_service_minutes' => 60,
                'default_priority' => 'normal',
            ],
            [
                'slug' => EntryType::SLUG_CARE_VISIT,
                'label' => 'Pflegebesuch',
                'icon' => 'medical_services',
                'color' => 'success',
                'description' => 'Ambulanter Pflegebesuch mit Tour und Zeitfenster.',
                'is_active' => true,
                'requires_customer' => true,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_tour' => true,
                'allow_priority' => false,
                'allow_tour' => true,
                'default_status' => 2,
                'default_service_minutes' => 20,
                'default_priority' => null,
            ],
            [
                'slug' => EntryType::SLUG_IT_TICKET,
                'label' => 'IT-Ticket',
                'icon' => 'support_agent',
                'color' => 'warning',
                'description' => 'IT-Vorgang mit Kunde und Priorität; Adresse/Termin optional.',
                'is_active' => true,
                'requires_customer' => true,
                'requires_address' => false,
                'requires_schedule' => false,
                'requires_tour' => false,
                'allow_priority' => true,
                'allow_tour' => false,
                'default_status' => 2,
                'default_service_minutes' => null,
                'default_priority' => 'normal',
            ],
            [
                'slug' => EntryType::SLUG_HVAC_JOB,
                'label' => 'Klimatechnik-Auftrag',
                'icon' => 'ac_unit',
                'color' => 'info',
                'description' => 'Klimatechnik-Einsatz mit Kunde, Adresse, Termin und Tour.',
                'is_active' => true,
                'requires_customer' => true,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_tour' => false,
                'allow_priority' => true,
                'allow_tour' => true,
                'default_status' => 2,
                'default_service_minutes' => 120,
                'default_priority' => 'normal',
            ],
        ];
    }
}
