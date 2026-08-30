<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningQuestionKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Prüfungsfrage (Feature 149, MVP-738). `settings` trägt das
 * Typspezifische: Musterlösungen, Lücken, Teilpunkte-Regel.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_quiz_id
 * @property LearningQuestionKind $kind
 * @property string $prompt
 * @property string|null $explanation
 * @property int $points
 * @property int $position
 * @property array<string, mixed>|null $settings
 */
class LearningQuestion extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasAttachments;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_quiz_id',
        'kind',
        'prompt',
        'explanation',
        'points',
        'position',
        'settings',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => LearningQuestionKind::class,
        'points' => 'integer',
        'position' => 'integer',
        'settings' => 'array',
    ];

    /** @return BelongsTo<LearningQuiz, $this> */
    public function quiz(): BelongsTo {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }

    /** @return HasMany<LearningQuestionOption, $this> */
    public function options(): HasMany {
        return $this->hasMany(LearningQuestionOption::class)->orderBy('position');
    }
}
