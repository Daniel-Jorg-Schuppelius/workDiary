<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Safety;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Safety\HazardAssessmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Gefährdungs-Position einer Gefährdungsbeurteilung (Feature 132): Gefahr,
 * Maßnahme und Risiko vor/nach Maßnahme als Schwere × Wahrscheinlichkeit
 * (1–5, Produkt im HazardAssessmentService persistiert). Positionen folgen
 * dem Einfrieren des Kopfes: nach der Freigabe weder änder- noch löschbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $hazard_assessment_id
 * @property int $position
 * @property string $hazard
 * @property string|null $measure
 * @property int $severity_before
 * @property int $likelihood_before
 * @property int $risk_before
 * @property int|null $severity_after
 * @property int|null $likelihood_after
 * @property int|null $risk_after
 */
class HazardAssessmentItem extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<HazardAssessmentItemFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'hazard_assessment_id',
        'position',
        'hazard',
        'measure',
        'severity_before',
        'likelihood_before',
        'risk_before',
        'severity_after',
        'likelihood_after',
        'risk_after',
    ];

    protected $casts = [
        'position' => 'integer',
        'severity_before' => 'integer',
        'likelihood_before' => 'integer',
        'risk_before' => 'integer',
        'severity_after' => 'integer',
        'likelihood_after' => 'integer',
        'risk_after' => 'integer',
    ];

    /** Positionen eines eingefrorenen Standes sind unveränderlich. */
    protected static function booted(): void {
        $guard = static function (self $item): void {
            $assessment = $item->assessment()->withTrashed()->first();
            if ($assessment !== null && ! $assessment->status->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => __('safety.register.error.assessment_frozen'),
                ]);
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * Ampel-Ton für ein Risiko (1–25): grün ≤ 6, gelb ≤ 12, rot > 12 —
     * dieselbe Skala wie das ISMS-Risikoregister.
     */
    public static function riskTone(?int $risk): string {
        return match (true) {
            $risk === null => 'ghost',
            $risk > 12 => 'error',
            $risk > 6 => 'warning',
            default => 'success',
        };
    }

    /** @return BelongsTo<HazardAssessment, $this> */
    public function assessment(): BelongsTo {
        return $this->belongsTo(HazardAssessment::class, 'hazard_assessment_id');
    }
}
