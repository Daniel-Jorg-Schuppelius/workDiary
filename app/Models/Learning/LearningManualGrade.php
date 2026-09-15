<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningManualGrade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Manuelle Note (Feature 149, MVP-790) — additiv: eine Korrektur ist ein
 * neuer Satz, die jüngste zählt; die frühere bleibt als Verlauf stehen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_gradebook_component_id
 * @property int $points
 * @property int $max_points
 * @property string|null $note
 * @property int|null $graded_by_user_id
 * @property Carbon $graded_at
 * @property-read LearningGradebookComponent|null $component
 * @property-read User|null $gradedBy
 */
class LearningManualGrade extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_gradebook_component_id',
        'points',
        'max_points',
        'note',
        'graded_by_user_id',
        'graded_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'max_points' => 'integer',
        'graded_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningGradebookComponent, $this> */
    public function component(): BelongsTo {
        return $this->belongsTo(LearningGradebookComponent::class, 'learning_gradebook_component_id');
    }

    /** @return BelongsTo<User, $this> */
    public function gradedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }
}
