<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningUnitProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningProgressStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fortschritt je Lerneinheit (Feature 149). `progress_percent` ist ein
 * Abschlusskriterium (z. B. gesehener Videoanteil), kein Verhaltensprofil —
 * die Zweckbindung steht im Konzept, Abschnitt 26.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_unit_id
 * @property LearningProgressStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $attempts
 * @property int $progress_percent
 */
class LearningUnitProgress extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'learning_unit_progress';

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_unit_id',
        'status',
        'started_at',
        'completed_at',
        'attempts',
        'progress_percent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningProgressStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
        'progress_percent' => 'integer',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }
}
