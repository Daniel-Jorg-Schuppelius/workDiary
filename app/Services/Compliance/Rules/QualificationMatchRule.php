<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationMatchRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\CoverageRequirement;
use App\Models\ScheduledShift;
use App\Models\User;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;

/**
 * Prüft, ob der Mitarbeiter die für diese Schicht/Datum nötigen Qualifikationen besitzt.
 * Quelle: CoverageRequirement.required_qualification_ids für (duty_plan_id, shift_type_id, Datum/weekday).
 */
final class QualificationMatchRule implements ComplianceRule {
    public function key(): string {
        return 'qualification_match';
    }

    public function check(ScheduledShift $shift, array $settings): array {
        if ($shift->shift_type_id === null) {
            return [];
        }

        $reqs = CoverageRequirement::query()
            ->forPlan($shift->duty_plan_id)
            ->where('shift_type_id', $shift->shift_type_id)
            ->get()
            ->filter(fn(CoverageRequirement $r) => $r->appliesToDate($shift->date))
            ->sortByDesc(fn(CoverageRequirement $r) => $r->priority());

        $top = $reqs->first();
        if (! $top || empty($top->required_qualification_ids)) {
            return [];
        }

        /** @var User|null $user */
        $user = User::query()->find($shift->user_id);
        if (! $user) {
            return [];
        }

        $userQualIds = $user->qualifications()->pluck('qualifications.id')->all();
        $missing = array_values(array_diff(
            array_map('intval', $top->required_qualification_ids),
            array_map('intval', $userQualIds),
        ));

        if ($missing === []) {
            return [];
        }

        return [
            new ComplianceViolation(
                code: 'qualification_match',
                severity: ComplianceViolation::SEVERITY_WARNING,
                message: __('Mitarbeiter fehlt :n erforderliche Qualifikation(en) für diese Schicht.', ['n' => count($missing)]),
                relatedShiftIds: [],
                context: ['missing_qualification_ids' => $missing],
            ),
        ];
    }
}
