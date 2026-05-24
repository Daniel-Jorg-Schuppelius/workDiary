<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\{ProcedureDeviationProposedAction, ProcedureDeviationSeverity, ProcedureDeviationType};
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ProcedureDeviationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Strukturierte Abweichung eines {@see ProcedureStepRun} (MVP-029 §2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $procedure_step_run_id
 * @property ProcedureDeviationType $deviation_type
 * @property ProcedureDeviationSeverity $severity
 * @property string $reason_text
 * @property ProcedureDeviationProposedAction|null $proposed_action
 * @property int|null $open_issue_id
 * @property int|null $follow_up_diary_entry_id
 * @property int|null $risk_accepted_by_user_id
 * @property Carbon|null $risk_accepted_at
 * @property int $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProcedureDeviation extends Model {
    /** @use HasFactory<ProcedureDeviationFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'procedure_step_run_id',
        'deviation_type',
        'severity',
        'reason_text',
        'proposed_action',
        'open_issue_id',
        'follow_up_diary_entry_id',
        'risk_accepted_by_user_id',
        'risk_accepted_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'deviation_type' => ProcedureDeviationType::class,
        'severity' => ProcedureDeviationSeverity::class,
        'proposed_action' => ProcedureDeviationProposedAction::class,
        'risk_accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<ProcedureStepRun, $this> */
    public function stepRun(): BelongsTo {
        return $this->belongsTo(ProcedureStepRun::class, 'procedure_step_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function riskAcceptedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'risk_accepted_by_user_id');
    }

    /** @return BelongsTo<OpenIssue, $this> */
    public function openIssue(): BelongsTo {
        return $this->belongsTo(OpenIssue::class, 'open_issue_id');
    }
}
