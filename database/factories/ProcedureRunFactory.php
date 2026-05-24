<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\{DiaryEntry, Organization, ProcedureRun, ProcedureTemplateVersion, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureRun>
 */
class ProcedureRunFactory extends Factory {
    protected $model = ProcedureRun::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'procedure_template_version_id' => ProcedureTemplateVersion::factory()->published(),
            'subject_type' => DiaryEntry::class,
            'subject_id' => 0,
            'status' => ProcedureRunStatus::Open->value,
            'assigned_user_id' => null,
            'started_at' => null,
            'completed_at' => null,
            'aborted_at' => null,
            'abort_reason' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
