<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskAssessment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{AssessmentKind, AssessmentStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsRiskAssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Historisierter Bewertungsstand eines ISMS-Risikos (Feature 046,
 * Inkrement D): Brutto- (gross), Netto- (net) oder Zielrisiko (target)
 * mit 5x5-Bewertung (likelihood x impact = score, berechnet im
 * RiskService) und laufender Nummer je Risiko (assessment_no).
 *
 * Freigegebene Bewertungen (status approved) sind UNVERÄNDERLICH:
 * Model-Guards werfen bei update/delete eine ValidationException —
 * historische Stände werden nie überschrieben, sondern durch neue
 * Bewertungen abgelöst (046-Prinzip). Entwürfe bleiben löschbar.
 * Das jüngste freigegebene net-Assessment ist die maßgebliche aktuelle
 * Bewertung des Risikos ({@see \App\Services\Isms\RiskService::approveAssessment()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_risk_id
 * @property int $assessment_no
 * @property AssessmentKind $kind
 * @property int $likelihood
 * @property int $impact
 * @property int $score
 * @property string|null $rationale
 * @property AssessmentStatus $status
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $valid_until
 * @property int|null $created_by_user_id
 */
class IsmsRiskAssessment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsRiskAssessmentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_risk_id',
        'assessment_no',
        'kind',
        'likelihood',
        'impact',
        'score',
        'rationale',
        'status',
        'approved_by_user_id',
        'approved_at',
        'valid_until',
        'created_by_user_id',
    ];

    protected $casts = [
        'assessment_no' => 'integer',
        'kind' => AssessmentKind::class,
        'likelihood' => 'integer',
        'impact' => 'integer',
        'score' => 'integer',
        'status' => AssessmentStatus::class,
        'approved_at' => 'datetime',
        'valid_until' => 'date',
    ];

    /**
     * Unveränderlichkeits-Guards: freigegebene Bewertungen können weder
     * geändert noch (soft-)gelöscht werden — Historie statt Überschreiben.
     */
    protected static function booted(): void {
        static::updating(function (self $assessment): void {
            if ($assessment->getOriginal('status') === AssessmentStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.assessment_already_approved'),
                ]);
            }
        });

        static::deleting(function (self $assessment): void {
            if ($assessment->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.assessment_already_approved'),
                ]);
            }
        });
    }

    /** Anzeige-Kennung in der Historie (z. B. "B-3"). */
    public function displayNo(): string {
        return 'B-' . $this->assessment_no;
    }

    /** Freigegeben und damit unveränderlich? */
    public function isApproved(): bool {
        return $this->status === AssessmentStatus::Approved;
    }

    /** Ablauf-/Reviewdatum überschritten? */
    public function isReviewOverdue(): bool {
        return $this->valid_until !== null && $this->valid_until->isPast() && ! $this->valid_until->isToday();
    }

    /** @return BelongsTo<IsmsRisk, $this> */
    public function risk(): BelongsTo {
        return $this->belongsTo(IsmsRisk::class, 'isms_risk_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Freigegebene Nettobewertungen (maßgebliche Stände) — Basis für den
     * Fristen-Scanner (isms.riskReviewDue).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovedNet(Builder $query): Builder {
        return $query
            ->where('kind', AssessmentKind::Net->value)
            ->where('status', AssessmentStatus::Approved->value);
    }
}
