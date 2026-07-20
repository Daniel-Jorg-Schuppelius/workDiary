<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureRunEventType;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Audit-Event eines {@see ProcedureRun}
 * (MVP-026 §7 / Vorlagen §3.6).
 *
 * @property int $id
 * @property int $procedure_run_id
 * @property int|null $procedure_step_run_id
 * @property ProcedureRunEventType $event_type
 * @property array<string, mixed>|null $payload
 * @property int|null $actor_user_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class ProcedureRunEvent extends Model {
    // Append-only jetzt technisch erzwungen statt nur dokumentiert (Vollaudit 2026-07, M52).
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'procedure_run_id',
        'procedure_step_run_id',
        'event_type',
        'payload',
        'actor_user_id',
        'created_at',
    ];

    protected $casts = [
        'event_type' => ProcedureRunEventType::class,
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ProcedureRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(ProcedureRun::class, 'procedure_run_id');
    }

    /** @return BelongsTo<ProcedureStepRun, $this> */
    public function stepRun(): BelongsTo {
        return $this->belongsTo(ProcedureStepRun::class, 'procedure_step_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
