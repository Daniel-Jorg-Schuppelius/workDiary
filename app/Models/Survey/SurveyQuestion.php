<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyQuestion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Survey;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frage eines Fragebogens (Feature 090): nps (0–10), scale (1–5), choice
 * oder text.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $survey_id
 * @property string $type
 * @property string $label
 * @property list<string>|null $options
 * @property bool $required
 * @property int $position
 */
class SurveyQuestion extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Survey\SurveyQuestionFactory> */
    use HasFactory;
    use HasSqid;

    public const TYPES = ['nps', 'scale', 'choice', 'text'];

    protected $fillable = [
        'organization_id', 'survey_id', 'type', 'label', 'options',
        'required', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo {
        return $this->belongsTo(Survey::class);
    }
}
