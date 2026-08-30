<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningScormState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laufzeitzustand eines SCORM-Inhalts je Einschreibung (Feature 149,
 * MVP-743).
 *
 * `suspend_data` gehört dem Inhalt — wir interpretieren es nicht, wir
 * bewahren es auf. Ohne das beginnt jede Unterbrechung von vorn.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_scorm_package_id
 * @property int $learning_enrollment_id
 * @property string|null $lesson_status
 * @property string|null $success_status
 * @property string|null $score_scaled
 * @property string|null $suspend_data
 * @property string|null $location
 * @property int $session_seconds
 * @property Carbon|null $last_commit_at
 */
class LearningScormState extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_scorm_package_id',
        'learning_enrollment_id',
        'lesson_status',
        'success_status',
        'score_scaled',
        'suspend_data',
        'location',
        'session_seconds',
        'last_commit_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'session_seconds' => 'integer',
        'last_commit_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningScormPackage, $this> */
    public function package(): BelongsTo {
        return $this->belongsTo(LearningScormPackage::class, 'learning_scorm_package_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }
}
