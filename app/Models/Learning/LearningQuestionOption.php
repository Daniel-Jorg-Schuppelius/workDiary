<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionOption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antwortoption (Feature 149, MVP-738). Bei Zuordnungsfragen verbindet
 * `match_key` die zusammengehörigen Optionen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_question_id
 * @property string $label
 * @property bool $is_correct
 * @property int $position
 * @property string|null $match_key
 */
class LearningQuestionOption extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_question_id',
        'label',
        'is_correct',
        'position',
        'match_key',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_correct' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<LearningQuestion, $this> */
    public function question(): BelongsTo {
        return $this->belongsTo(LearningQuestion::class, 'learning_question_id');
    }
}
