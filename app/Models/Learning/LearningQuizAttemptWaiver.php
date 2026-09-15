<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizAttemptWaiver.php
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
 * Versuchsfreigabe (Feature 149, MVP-785): erlaubt genau einen weiteren
 * Versuch trotz Versuchsgrenze oder Sperrfrist.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_quiz_id
 * @property int|null $granted_by_user_id
 * @property string $reason
 * @property Carbon|null $used_at
 */
class LearningQuizAttemptWaiver extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_quiz_id',
        'granted_by_user_id',
        'reason',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningQuiz, $this> */
    public function quiz(): BelongsTo {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
