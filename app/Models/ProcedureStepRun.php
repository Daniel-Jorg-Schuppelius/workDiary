<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureStepRunStatus;
use Database\Factories\ProcedureStepRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausfuehrung einer {@see ProcedureStepDef} innerhalb eines
 * {@see ProcedureRun} (MVP-026 §3 / Vorlagen §3.5).
 *
 * @property int $id
 * @property int $procedure_run_id
 * @property int $procedure_step_def_id
 * @property ProcedureStepRunStatus $status
 * @property array<string, mixed>|null $value_json
 * @property int|null $executed_by_user_id
 * @property \Illuminate\Support\Carbon|null $executed_at
 * @property int|null $second_person_user_id
 * @property \Illuminate\Support\Carbon|null $second_person_signed_at
 * @property int|null $proof_attachment_id
 * @property string|null $note
 * @property int|null $deviation_id
 */
class ProcedureStepRun extends Model {
    /** @use HasFactory<ProcedureStepRunFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_run_id',
        'procedure_step_def_id',
        'status',
        'value_json',
        'executed_by_user_id',
        'executed_at',
        'second_person_user_id',
        'second_person_signed_at',
        'proof_attachment_id',
        'note',
        'deviation_id',
        'wait_started_at',
        'wait_until',
    ];

    protected $casts = [
        'status' => ProcedureStepRunStatus::class,
        'value_json' => 'array',
        'executed_at' => 'datetime',
        'second_person_signed_at' => 'datetime',
        'wait_started_at' => 'datetime',
        'wait_until' => 'datetime',
    ];

    /** @return BelongsTo<ProcedureRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(ProcedureRun::class, 'procedure_run_id');
    }

    /** @return BelongsTo<ProcedureStepDef, $this> */
    public function stepDef(): BelongsTo {
        return $this->belongsTo(ProcedureStepDef::class, 'procedure_step_def_id');
    }

    /** @return BelongsTo<User, $this> */
    public function executedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function secondPerson(): BelongsTo {
        return $this->belongsTo(User::class, 'second_person_user_id');
    }
}
