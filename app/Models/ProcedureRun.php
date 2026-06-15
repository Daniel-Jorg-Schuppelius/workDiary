<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\ProcedureRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

/**
 * Instanz einer {@see ProcedureTemplateVersion} fuer ein Subjekt
 * (Auftrag/Asset), MVP-026 §3 / Vorlagen §3.4.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $procedure_template_version_id
 * @property string $subject_type
 * @property int $subject_id
 * @property ProcedureRunStatus $status
 * @property int|null $assigned_user_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $aborted_at
 * @property string|null $abort_reason
 * @property int $created_by_user_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProcedureStepRun> $stepRuns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProcedureRunEvent> $events
 */
class ProcedureRun extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ProcedureRunFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'procedure_template_version_id',
        'subject_type',
        'subject_id',
        'status',
        'assigned_user_id',
        'started_at',
        'completed_at',
        'aborted_at',
        'abort_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'status' => ProcedureRunStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'aborted_at' => 'datetime',
    ];

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function templateVersion(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ProcedureStepRun, $this> */
    public function stepRuns(): HasMany {
        return $this->hasMany(ProcedureStepRun::class)->orderBy('id');
    }

    /** @return HasMany<ProcedureRunEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(ProcedureRunEvent::class)->orderBy('id');
    }
}
