<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRisk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{RiskCategory, RiskStatus, RiskTreatment};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsRiskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\Carbon;

/**
 * ISMS-Risiko (Feature 044, MVP 1): Eintrag im Risikoregister mit
 * 5x5-Bewertung (likelihood x impact = score, berechnet im RiskService),
 * Behandlungsentscheidung und Review-Termin. Maßnahmen-Zuordnung über
 * die Pivot-Tabelle isms_control_risk (ohne eigenes Model).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $risk_no
 * @property string $title
 * @property string|null $description
 * @property RiskCategory $category
 * @property string|null $asset_ref
 * @property string|null $threat
 * @property int $likelihood
 * @property int $impact
 * @property int $score
 * @property RiskTreatment $treatment
 * @property RiskStatus $status
 * @property int|null $owner_user_id
 * @property Carbon|null $review_due_on
 * @property-read int|null $risk_count count(*)-Alias aus {@see self::scopeMatrixCells()}
 */
class IsmsRisk extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsRiskFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'risk_no',
        'title',
        'description',
        'category',
        'asset_ref',
        'threat',
        'likelihood',
        'impact',
        'score',
        'treatment',
        'status',
        'owner_user_id',
        'review_due_on',
    ];

    protected $casts = [
        'risk_no' => 'integer',
        'category' => RiskCategory::class,
        'likelihood' => 'integer',
        'impact' => 'integer',
        'score' => 'integer',
        'treatment' => RiskTreatment::class,
        'status' => RiskStatus::class,
        'review_due_on' => 'date',
    ];

    /** Anzeige-Kennung im Register (z. B. "R-12"). */
    public function displayNo(): string {
        return 'R-' . $this->risk_no;
    }

    /**
     * Ampel-Ton für einen Score (1-25): grün ≤ 6, gelb ≤ 12, rot > 12.
     * Genutzt von Liste, Matrix-Widget und SoA (DaisyUI badge tone).
     */
    public static function scoreTone(int $score): string {
        return match (true) {
            $score > 12 => 'error',
            $score > 6 => 'warning',
            default => 'success',
        };
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<IsmsControl, $this> */
    public function controls(): BelongsToMany {
        return $this->belongsToMany(IsmsControl::class, 'isms_control_risk', 'risk_id', 'control_id');
    }

    /**
     * Offene Risiken (alles außer geschlossen).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', '!=', RiskStatus::Closed->value);
    }

    /**
     * Risiken mit fälligem Review (Termin erreicht/überschritten, nicht geschlossen).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReviewDue(Builder $query): Builder {
        return $query->open()
            ->whereNotNull('review_due_on')
            ->whereDate('review_due_on', '<=', now()->toDateString());
    }

    /**
     * Aggregation für das 5x5-Matrix-Widget: Anzahl offener Risiken je
     * Zelle (likelihood, impact).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatrixCells(Builder $query): Builder {
        return $query->open()
            ->selectRaw('likelihood, impact, count(*) as risk_count')
            ->groupBy('likelihood', 'impact');
    }
}
