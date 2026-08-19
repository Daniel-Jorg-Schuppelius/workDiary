<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyResponse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Survey;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Eingegangene Antwort (Feature 090). Bei anonymen Umfragen ist
 * `survey_invitation_id` NULL — der einzige Personenbezug fällt weg.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $survey_id
 * @property int|null $survey_invitation_id
 * @property string $context_kind
 */
class SurveyResponse extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'survey_id', 'survey_invitation_id', 'context_kind',
    ];

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo {
        return $this->belongsTo(Survey::class);
    }

    /** @return HasMany<SurveyAnswer, $this> */
    public function answers(): HasMany {
        return $this->hasMany(SurveyAnswer::class);
    }
}
