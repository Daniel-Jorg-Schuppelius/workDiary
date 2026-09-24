<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields\Install;

use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Services\Fields\CustomFieldService;

/**
 * Branchenprofil-Schlüssel `custom_fields` (MVP-868): je Träger-Alias eine
 * Liste von Felddefinitionen. Ergänzt nur fehlende Schlüssel; beim
 * Deinstallieren bleiben Definitionen stehen, weil Werte daran hängen können.
 */
class CustomFieldInstallStep implements ProfileInstallStep {
    public function __construct(private readonly CustomFieldService $customFields) {}

    public function key(): string {
        return 'custom_fields';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Alias → Felddefinitionen (Profildaten, ungeprüft)
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        $subjects = $this->customFields->subjects();
        foreach ($rows as $alias => $fields) {
            if (! is_string($alias) || ! isset($subjects[$alias]) || ! is_array($fields) || $fields === []) {
                $skipped++;

                continue;
            }
            $result = $this->customFields->mergeDefinition($organization, $alias, array_values($fields));
            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
