<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizDrawRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ziehregel einer Prüfung (Feature 149, MVP-782): je Versuch `count`
 * zufällige Katalogfragen der Kategorie — zusätzlich zur festen Liste.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_quiz_id
 * @property int $learning_question_category_id
 * @property int $count
 */
class LearningQuizDrawRule extends Model {
    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_quiz_id',
        'learning_question_category_id',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];

    /** @return BelongsTo<LearningQuiz, $this> */
    public function quiz(): BelongsTo {
        return $this->belongsTo(LearningQuiz::class, 'learning_quiz_id');
    }

    /** @return BelongsTo<LearningQuestionCategory, $this> */
    public function category(): BelongsTo {
        return $this->belongsTo(LearningQuestionCategory::class, 'learning_question_category_id');
    }
}
