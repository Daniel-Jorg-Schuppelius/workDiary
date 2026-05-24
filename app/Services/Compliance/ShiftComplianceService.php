<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftComplianceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\{Organization, ScheduledShift};
use App\Services\Compliance\Rules\{ConsecutiveDaysRule, HolidayDoubleBookRule, MaxDailyHoursRule, MaxWeeklyHoursRule, OverlapRule, QualificationMatchRule, RestPeriodRule, VacationConflictRule};

/**
 * Aggregiert die Compliance-Regeln und prüft eine geplante Schicht.
 */
final class ShiftComplianceService {
    /** @var list<ComplianceRule> */
    private array $rules;

    /** @param list<ComplianceRule>|null $rules */
    public function __construct(?array $rules = null) {
        $this->rules = $rules ?? [
            new OverlapRule,
            new RestPeriodRule,
            new MaxDailyHoursRule,
            new MaxWeeklyHoursRule,
            new ConsecutiveDaysRule,
            new VacationConflictRule,
            new QualificationMatchRule,
            new HolidayDoubleBookRule,
        ];
    }

    /**
     * Prüfe die Schicht gegen alle aktivierten Regeln der Organisation.
     */
    public function check(ScheduledShift $shift, ?Organization $organization = null): ComplianceReport {
        $organization ??= $shift->organization;
        $settings = $organization
            ? $organization->complianceSettings()
            : Organization::COMPLIANCE_DEFAULTS;

        if ($settings['mode'] === Organization::COMPLIANCE_OFF) {
            return new ComplianceReport([]);
        }

        $violations = [];
        foreach ($this->rules as $rule) {
            $key = $rule->key();
            if (! ($settings['rules'][$key] ?? true)) {
                continue;
            }
            foreach ($rule->check($shift, $settings) as $v) {
                $violations[] = $v;
            }
        }

        return new ComplianceReport($violations);
    }

    /**
     * Liste der vom Service unterstützten Regel-Keys (für Settings-UI).
     *
     * @return list<string>
     */
    public function ruleKeys(): array {
        return array_map(fn(ComplianceRule $r) => $r->key(), $this->rules);
    }
}
