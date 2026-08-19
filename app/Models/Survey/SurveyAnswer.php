<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyAnswer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Survey;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelantwort auf eine Frage (Feature 090).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $survey_response_id
 * @property int $survey_question_id
 * @property int|null $value_int
 * @property string|null $value_text
 */
class SurveyAnswer extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'survey_response_id', 'survey_question_id',
        'value_int', 'value_text',
    ];

    /** @return BelongsTo<SurveyQuestion, $this> */
    public function question(): BelongsTo {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
