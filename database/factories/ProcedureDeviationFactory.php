<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\{ProcedureDeviationProposedAction, ProcedureDeviationSeverity, ProcedureDeviationType};
use App\Models\{Organization, ProcedureDeviation, ProcedureStepRun, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureDeviation>
 */
class ProcedureDeviationFactory extends Factory {
    protected $model = ProcedureDeviation::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'procedure_step_run_id' => ProcedureStepRun::factory(),
            'deviation_type' => ProcedureDeviationType::NotPossible->value,
            'severity' => ProcedureDeviationSeverity::High->value,
            'reason_text' => str_repeat('Begruendung für die Abweichung. ', 2),
            'proposed_action' => ProcedureDeviationProposedAction::None->value,
            'open_issue_id' => null,
            'follow_up_diary_entry_id' => null,
            'risk_accepted_by_user_id' => null,
            'risk_accepted_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
