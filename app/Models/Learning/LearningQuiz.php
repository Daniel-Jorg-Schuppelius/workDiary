<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuiz.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningFeedbackMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Prüfung (Feature 149, MVP-738) — an einer Lerneinheit oder freistehend.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $learning_unit_id
 * @property string $title
 * @property string|null $description
 * @property int $pass_percent
 * @property int|null $time_limit_minutes
 * @property int $max_attempts
 * @property int $retry_wait_hours
 * @property int|null $questions_per_attempt
 * @property bool $shuffle_questions
 * @property bool $shuffle_answers
 * @property LearningFeedbackMode $feedback_mode
 * @property bool $show_solutions
 * @property-read LearningUnit|null $unit
 */
class LearningQuiz extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_unit_id',
        'title',
        'description',
        'pass_percent',
        'time_limit_minutes',
        'max_attempts',
        'retry_wait_hours',
        'questions_per_attempt',
        'shuffle_questions',
        'shuffle_answers',
        'feedback_mode',
        'show_solutions',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'pass_percent' => 'integer',
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'retry_wait_hours' => 'integer',
        'questions_per_attempt' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_answers' => 'boolean',
        'feedback_mode' => LearningFeedbackMode::class,
        'show_solutions' => 'boolean',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningQuestion, $this> */
    public function questions(): HasMany {
        return $this->hasMany(LearningQuestion::class)->orderBy('position');
    }

    /** @return HasMany<LearningQuizAttempt, $this> */
    public function attempts(): HasMany {
        return $this->hasMany(LearningQuizAttempt::class);
    }

    /** 0 = unbegrenzt. */
    public function allowsUnlimitedAttempts(): bool {
        return $this->max_attempts === 0;
    }
}
