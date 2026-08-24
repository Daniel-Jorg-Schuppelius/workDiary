<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategorySeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\Activity\ActivityCategoryType;
use App\Models\{ActivityCategory, Organization};
use Illuminate\Database\Seeder;

/**
 * Erstausstattung der Tätigkeitskategorien je Organisation (und global,
 * organization_id=NULL). Bootstrap-only wie der EntryTypeSeeder: deploy.sh
 * seedet bei jedem Deploy — Orgs mit vorhandenen Kategorien werden nie
 * angefasst, sonst stellte updateOrCreate per UI geänderte Labels/Farben
 * zurück und reaktivierte deaktivierte Kategorien (Vollscan 2026-08-23, J3).
 * Neue Orgs laufen über den OrganizationObserver.
 */
class ActivityCategorySeeder extends Seeder {
    public function run(): void {
        $orgs = Organization::query()->pluck('id')->all();
        $orgs[] = null; // global defaults

        foreach ($orgs as $orgId) {
            self::seedOrganization($orgId === null ? null : (int) $orgId);
        }
    }

    /** Legt die Defaults nur an, wenn die Organisation noch keine Kategorie hat. */
    public static function seedOrganization(?int $organizationId): void {
        $exists = ActivityCategory::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->exists();
        if ($exists) {
            return;
        }

        // Bypass BelongsToOrganization::creating so explicit organization_id=null is preserved.
        ActivityCategory::withoutEvents(function () use ($organizationId): void {
            foreach ((new self())->defaults() as $i => $row) {
                ActivityCategory::query()->create(
                    $row + ['organization_id' => $organizationId, 'sort_order' => ($i + 1) * 10, 'active' => true]
                );
            }
        });
    }

    /**
     * @return list<array{key:string,label:string,activity_type:ActivityCategoryType,billable_default:bool,counts_as_work:bool,color:string|null,icon:string|null,description:string|null}>
     */
    private function defaults(): array {
        return [
            [
                'key' => 'administration',
                'label' => 'Verwaltung',
                'activity_type' => ActivityCategoryType::Admin,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#6b7280',
                'icon' => 'work',
                'description' => 'Allgemeine Verwaltungs- und Büroarbeiten.',
            ],
            [
                'key' => 'team_meeting',
                'label' => 'Teambesprechung',
                'activity_type' => ActivityCategoryType::Meeting,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#2563eb',
                'icon' => 'groups',
                'description' => 'Interne Besprechungen und Abstimmungen.',
            ],
            [
                'key' => 'training',
                'label' => 'Schulung / Weiterbildung',
                'activity_type' => ActivityCategoryType::Training,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#0ea5e9',
                'icon' => 'school',
                'description' => 'Eigene Weiterbildung oder Schulungen.',
            ],
            [
                'key' => 'internal_work',
                'label' => 'Interne Arbeiten',
                'activity_type' => ActivityCategoryType::Internal,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#16a34a',
                'icon' => 'settings',
                'description' => 'Werkzeugpflege, Lager, interne IT etc.',
            ],
            [
                'key' => 'travel',
                'label' => 'Anfahrt / Reisezeit',
                'activity_type' => ActivityCategoryType::Travel,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#f59e0b',
                'icon' => 'local_shipping',
                'description' => 'Fahrtzeiten ohne direkten Projektbezug.',
            ],
            [
                'key' => 'standby',
                'label' => 'Bereitschaftsdienst',
                'activity_type' => ActivityCategoryType::Standby,
                'billable_default' => false,
                'counts_as_work' => true,
                'color' => '#a855f7',
                'icon' => 'notifications',
                'description' => 'Rufbereitschaft / Standby ohne Einsatz.',
            ],
            [
                'key' => 'break',
                'label' => 'Pause',
                'activity_type' => ActivityCategoryType::Break_,
                'billable_default' => false,
                'counts_as_work' => false,
                'color' => '#9ca3af',
                'icon' => 'pause',
                'description' => 'Pausenzeiten (nicht als Arbeitszeit gezählt).',
            ],
            [
                'key' => 'sick_paid',
                'label' => 'Krank (bezahlt)',
                'activity_type' => ActivityCategoryType::Absence,
                'billable_default' => false,
                'counts_as_work' => false,
                'color' => '#ef4444',
                'icon' => 'sick',
                'description' => 'Krankheit mit Lohnfortzahlung.',
            ],
        ];
    }
}
