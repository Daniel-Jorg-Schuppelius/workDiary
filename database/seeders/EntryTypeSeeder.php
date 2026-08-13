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
    /** Neutraler Grundstock für Orgs ohne (oder mit unbekanntem) Branchenprofil. */
    public const DEFAULT_SLUGS = [EntryType::SLUG_GENERAL, EntryType::SLUG_SERVICE];

    public function run(): void {
        // deploy.sh seedet bei jedem Deploy — Orgs mit vorhandenen Typen
        // dürfen nie angefasst werden, sonst erstehen gelöschte/umbenannte
        // Typen wieder auf. Nur Erstausstattung; neue Orgs laufen über den
        // OrganizationObserver.
        Organization::query()->each(
            fn (Organization $organization) => self::seedOrganization($organization)
        );
    }

    /** Erstausstattung passend zum Branchenprofil — no-op, sobald die Org eigene Typen hat. */
    public static function seedOrganization(Organization $organization): void {
        $exists = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->exists();
        if ($exists) {
            return;
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $code = $settings['branch_profile_code'] ?? null;

        foreach (self::profilesFor(self::defaultSlugsFor(is_string($code) ? $code : null)) as $sort => $profile) {
            EntryType::query()->withoutGlobalScopes()->create(
                array_merge($profile, ['organization_id' => $organization->id, 'sort' => $sort])
            );
        }
    }

    /**
     * Default-Slugs fürs Branchenprofil: Profil-Schlüssel `entry_type_defaults`
     * (Struktur-Typen, nicht die Classification-Domäne `entry_type`), sonst
     * neutraler Grundstock.
     *
     * @return list<string>
     */
    public static function defaultSlugsFor(?string $profileCode): array {
        if ($profileCode !== null && $profileCode !== '') {
            $path = database_path("data/branchprofiles/{$profileCode}.php");
            if (is_file($path)) {
                /** @var array<string, mixed> $profile */
                $profile = require $path;
                $slugs = array_values(array_filter(
                    (array) ($profile['entry_type_defaults'] ?? []),
                    'is_string'
                ));
                if ($slugs !== []) {
                    return $slugs;
                }
            }
        }

        return self::DEFAULT_SLUGS;
    }

    /**
     * Auf die gegebenen Slugs gefilterte Default-Definitionen (Reihenfolge wie profiles()).
     *
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    public static function profilesFor(array $slugs): array {
        return array_values(array_filter(
            self::profiles(),
            fn (array $profile): bool => in_array($profile['slug'], $slugs, true)
        ));
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
