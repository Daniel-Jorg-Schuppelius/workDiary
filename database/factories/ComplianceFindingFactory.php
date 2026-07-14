<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Models\{ComplianceFinding, Organization, User};
use App\Services\Compliance\AttendanceComplianceChecker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ComplianceFinding> */
class ComplianceFindingFactory extends Factory {
    protected $model = ComplianceFinding::class;

    public function definition(): array {
        $date = now()->subDays(3)->toDateString();

        return [
            'organization_id' => Organization::factory(),
            'category' => 'arbzg',
            'rule_code' => AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS,
            'severity' => 'error',
            'subject_type' => User::class,
            'subject_id' => User::factory(),
            'scope_date' => $date,
            'detected_value' => 660,
            'threshold_value' => 600,
            'dedup_key' => 'arbzg|' . AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS . '|User#0|' . $date,
            'status' => ComplianceFindingStatus::Open->value,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ];
    }
}
