<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeRuleInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Install;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;

/**
 * Branchenprofil-Schlüssel `entry_type_rules` (Feature 198, MVP-1634): legt
 * fehlende Eintragsarten (Slug = Code der Klassifikation `entry_type`) mit
 * Periodenpflicht (`requires_period`, Liste aus `PeriodKind`) und Prüfpflicht
 * (`requires_review`) an. Vorhandene Eintragsarten bleiben unverändert —
 * eine Pflicht, die die Organisation abgeschaltet hat, kommt nicht still
 * zurück. Erst mit der Eintragsart greifen auch die Pflichtklassifikationen
 * des Profils für diesen Code.
 */
final class EntryTypeRuleInstallStep implements ProfileInstallStep {
    public function key(): string {
        return 'entry_type_rules';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Zeilen mit `slug`, `label`, optional `icon`, `color`, `requires_period`, `requires_review`, `requires_customer`
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        $sort = (int) EntryType::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->max('sort');
        foreach ($rows as $row) {
            $slug = is_array($row) ? (string) ($row['slug'] ?? '') : '';
            if ($slug === '' || preg_match('/^[a-z0-9_]+$/', $slug) !== 1) {
                $skipped++;

                continue;
            }
            $exists = EntryType::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('slug', $slug)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            $kinds = array_values(array_unique(array_map(
                static fn (PeriodKind $kind): string => $kind->value,
                array_filter(array_map(static fn (mixed $value): ?PeriodKind => is_string($value) ? PeriodKind::tryFrom($value) : null, (array) ($row['requires_period'] ?? []))),
            )));
            $sort += 10;
            EntryType::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'slug' => $slug,
                'label' => (string) ($row['label'] ?? $slug),
                'icon' => (string) ($row['icon'] ?? 'assignment'),
                'color' => (string) ($row['color'] ?? 'primary'),
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'sort' => $sort,
                'is_active' => true,
                'requires_customer' => (bool) ($row['requires_customer'] ?? true),
                'requires_address' => false,
                'requires_schedule' => false,
                'requires_tour' => false,
                'allow_priority' => true,
                'allow_tour' => false,
                'default_status' => 2,
                'requires_period' => $kinds !== [] ? $kinds : null,
                'requires_review' => (bool) ($row['requires_review'] ?? false),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
