<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessmentItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewertungs-Einzelkriterium (Feature 071): Score 0–5 mit Gewicht-
 * Snapshot, Datenqualität, Quelle und Begründung — der aggregierte Score
 * bleibt bis hierher erklärbar (Drilldown, kein Greenwashing-Label).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $assessment_id
 * @property int $criterion_id
 * @property int|null $score
 * @property int $weight
 * @property string $data_quality
 * @property string|null $source_note
 * @property string|null $justification
 */
class SustainabilityAssessmentItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'assessment_id', 'criterion_id', 'score', 'weight',
        'data_quality', 'source_note', 'justification',
    ];

    /** @var array<string, string> */
    protected $casts = ['score' => 'integer', 'weight' => 'integer'];

    /** @return BelongsTo<SustainabilityAssessment, $this> */
    public function assessment(): BelongsTo {
        return $this->belongsTo(SustainabilityAssessment::class, 'assessment_id');
    }

    /** @return BelongsTo<SustainabilityCriterion, $this> */
    public function criterion(): BelongsTo {
        return $this->belongsTo(SustainabilityCriterion::class, 'criterion_id');
    }
}
