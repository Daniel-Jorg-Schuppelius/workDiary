<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAnswer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gegebene Antwort in einem Prüfungsversuch (Feature 149, MVP-738).
 *
 * Eine nachträgliche Korrektur ist **additiv**: `corrected_points`
 * überschreibt `points_awarded` nicht, sondern tritt daneben — sonst
 * ließe sich der ursprüngliche Automatik-Wert nicht mehr nachvollziehen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_quiz_attempt_id
 * @property int $learning_question_id
 * @property array<string, mixed>|null $payload
 * @property bool|null $is_correct
 * @property int $points_awarded
 * @property int|null $corrected_points
 * @property string|null $correction_note
 * @property int|null $graded_by_user_id
 * @property Carbon|null $graded_at
 */
class LearningAnswer extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_quiz_attempt_id',
        'learning_question_id',
        'payload',
        'is_correct',
        'points_awarded',
        'corrected_points',
        'correction_note',
        'graded_by_user_id',
        'graded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'is_correct' => 'boolean',
        'points_awarded' => 'integer',
        'corrected_points' => 'integer',
        'graded_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningQuizAttempt, $this> */
    public function attempt(): BelongsTo {
        return $this->belongsTo(LearningQuizAttempt::class, 'learning_quiz_attempt_id');
    }

    /** @return BelongsTo<LearningQuestion, $this> */
    public function question(): BelongsTo {
        return $this->belongsTo(LearningQuestion::class, 'learning_question_id');
    }

    /** @return BelongsTo<User, $this> */
    public function gradedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }

    /** Gültige Punktzahl: die Korrektur gewinnt, der Erstwert bleibt sichtbar. */
    public function effectivePoints(): int {
        return $this->corrected_points ?? $this->points_awarded;
    }
}
