<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepRunFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Models\{ProcedureRun, ProcedureStepDef, ProcedureStepRun};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureStepRun>
 */
class ProcedureStepRunFactory extends Factory {
    protected $model = ProcedureStepRun::class;

    public function definition(): array {
        return [
            'procedure_run_id' => ProcedureRun::factory(),
            'procedure_step_def_id' => ProcedureStepDef::factory(),
            'status' => ProcedureStepRunStatus::Pending->value,
            'value_json' => null,
            'executed_by_user_id' => null,
            'executed_at' => null,
            'second_person_user_id' => null,
            'second_person_signed_at' => null,
            'proof_attachment_id' => null,
            'note' => null,
            'deviation_id' => null,
        ];
    }
}
