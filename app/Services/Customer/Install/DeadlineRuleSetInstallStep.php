<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineRuleSetInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Install;

use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;

/**
 * Branchenprofil-Schlüssel `deadline_rule_sets` (Feature 197, MVP-1630):
 * schaltet die genannten Regelsätze der Kundenfristen für die Organisation
 * ein. Ergänzt nur — was die Organisation ausgeschaltet oder zusätzlich
 * eingeschaltet hat, bleibt; unbekannte Schlüssel (Modul aus) zählen als
 * übersprungen.
 */
final class DeadlineRuleSetInstallStep implements ProfileInstallStep {
    public function __construct(private readonly CustomerDeadlineRuleSets $ruleSets) {}

    public function key(): string {
        return 'deadline_rule_sets';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Schlüssel der Regelsätze (`tax_advisory`, `home_care`)
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $keys = array_values(array_unique(array_filter($rows, static fn (mixed $key): bool => is_string($key) && preg_match('/^[a-z0-9_]+$/', $key) === 1)));
        if ($keys === []) {
            return ['created' => 0, 'skipped' => count($rows)];
        }
        $created = $this->ruleSets->enable($organization, $keys);

        return ['created' => $created, 'skipped' => count($rows) - $created];
    }
}
