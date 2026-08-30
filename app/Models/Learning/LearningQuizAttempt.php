<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizAttempt.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Prüfungsversuch (Feature 149, MVP-738) — die Prüfungsakte.
 *
 * `questions_snapshot` friert die gestellten Fragen samt Optionen ein.
 * Ohne ihn ließe sich ein Ergebnis nach einer Fragenänderung nicht mehr
 * erklären. Versuche werden **nie gelöscht**.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_quiz_id
 * @property int $learning_enrollment_id
 * @property int $attempt_no
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $expires_at
 * @property string $questions_snapshot
 * @property int $score_points
 * @property int $max_points
 * @property int|null $score_percent
 * @property bool|null $passed
 * @property string|null $client_ip
 * @property string|null $user_agent
 * @property-read LearningQuiz|null $quiz
 * @property-read LearningEnrollment|null $enrollment
 */
class LearningQuizAttempt extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_quiz_id',
        'learning_enrollment_id',
        'attempt_no',
        'started_at',
        'submitted_at',
        'expires_at',
        'questions_snapshot',
        'score_points',
        'max_points',
        'score_percent',
        'passed',
        'client_ip',
        'user_agent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'attempt_no' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
        'score_points' => 'integer',
        'max_points' => 'integer',
        'score_percent' => 'integer',
        'passed' => 'boolean',
    ];

    /** @return BelongsTo<LearningQuiz, $this> */
    public function quiz(): BelongsTo {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return HasMany<LearningAnswer, $this> */
    public function answers(): HasMany {
        return $this->hasMany(LearningAnswer::class, 'learning_quiz_attempt_id');
    }

    /**
     * Eingefrorene Fragen dieses Versuchs.
     *
     * @return list<array<string, mixed>>
     */
    public function questions(): array {
        $decoded = json_decode($this->questions_snapshot, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function isOpen(): bool {
        return $this->submitted_at === null;
    }

    /** Zeitlimit abgelaufen? Der Versuch wird dann mit Ist-Stand gewertet. */
    public function isExpired(?Carbon $now = null): bool {
        $now ??= Carbon::now();

        return $this->expires_at !== null && $this->expires_at->lessThan($now);
    }
}
