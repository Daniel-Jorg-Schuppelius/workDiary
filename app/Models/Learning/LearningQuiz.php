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
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

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
 * @property int|null $questions_per_attempt_percent
 * @property int|null $pass_points
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
        'questions_per_attempt_percent',
        'pass_points',
        'shuffle_questions',
        'shuffle_answers',
        'feedback_mode',
        'show_solutions',
        'display_mode',
        'allow_back',
        'allow_skip',
        'require_all_answered',
        'result_messages',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'pass_percent' => 'integer',
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'retry_wait_hours' => 'integer',
        'questions_per_attempt' => 'integer',
        'questions_per_attempt_percent' => 'integer',
        'pass_points' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_answers' => 'boolean',
        'feedback_mode' => LearningFeedbackMode::class,
        'show_solutions' => 'boolean',
        'allow_back' => 'boolean',
        'allow_skip' => 'boolean',
        'require_all_answered' => 'boolean',
        'result_messages' => 'array',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningQuestion, $this> */
    /**
     * Feste Fragen in Prüfungsreihenfolge (MVP-782: Zwischentabelle, die
     * Frage selbst gehört dem Katalog).
     *
     * @return BelongsToMany<LearningQuestion, $this>
     */
    public function questions(): BelongsToMany {
        return $this->belongsToMany(LearningQuestion::class, 'learning_quiz_question')
            ->withPivot(['position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return HasMany<LearningQuizDrawRule, $this> */
    public function drawRules(): HasMany {
        return $this->hasMany(LearningQuizDrawRule::class);
    }

    /** @return HasMany<LearningQuizAttempt, $this> */
    public function attempts(): HasMany {
        return $this->hasMany(LearningQuizAttempt::class);
    }

    /** 0 = unbegrenzt. */
    public function isSingleQuestionMode(): bool {
        return $this->display_mode === 'single';
    }

    /**
     * Ergebnistext zum erreichten Prozentwert (MVP-783): die Stufe mit der
     * höchsten Untergrenze, die noch erreicht wurde.
     */
    public function resultMessageFor(int $percent): ?string {
        $best = null;
        $bestFrom = -1;

        foreach ((array) ($this->result_messages ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $from = (int) ($entry['from_percent'] ?? 0);
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '' && $from <= $percent && $from > $bestFrom) {
                $best = $text;
                $bestFrom = $from;
            }
        }

        return $best;
    }

    public function allowsUnlimitedAttempts(): bool {
        return $this->max_attempts === 0;
    }
}
