<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSupplierAssessment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Supplier, User};
use Database\Factories\Isms\IsmsSupplierAssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lieferantenbewertung (Feature 044, MVP 2/3 „Lieferanten und Verträge"):
 * Kritikalität ({@see IncidentSeverity}), geforderte Sicherheitsanforderungen,
 * Vertragsmerkmale (NDA/AVV/Prüfungsrecht), Risikoeinstufung und
 * wiederkehrende Reviews. State-Machine im SupplierAssessmentService.
 *
 * Supplier-Bezug ist OPTIONAL: entweder loser FK auf {@see Supplier}
 * (Stammdaten) ODER der Freitext supplier_name als Fallback. Die
 * AVV-Kopplung zum Datenschutzmanagement bleibt BEWUSST lose (Flag has_dpa +
 * Freitext dpa_ref) — KEIN FK auf die Privacy-WIP-Tabellen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $assessment_no
 * @property int|null $supplier_id
 * @property string $supplier_name
 * @property IncidentSeverity $criticality
 * @property string|null $service_description
 * @property int|null $isms_scope_id
 * @property string|null $security_requirements
 * @property bool $has_nda
 * @property bool $has_dpa
 * @property string|null $dpa_ref
 * @property bool $audit_right
 * @property Carbon|null $last_review_on
 * @property Carbon|null $next_review_on
 * @property IncidentSeverity $risk_rating
 * @property SupplierAssessmentStatus $status
 * @property string|null $findings
 * @property int|null $owner_user_id
 */
class IsmsSupplierAssessment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsSupplierAssessmentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $table = 'isms_supplier_assessments';

    protected $fillable = [
        'organization_id',
        'assessment_no',
        'supplier_id',
        'supplier_name',
        'criticality',
        'service_description',
        'isms_scope_id',
        'security_requirements',
        'has_nda',
        'has_dpa',
        'dpa_ref',
        'audit_right',
        'last_review_on',
        'next_review_on',
        'risk_rating',
        'status',
        'findings',
        'owner_user_id',
    ];

    protected $casts = [
        'assessment_no' => 'integer',
        'criticality' => IncidentSeverity::class,
        'risk_rating' => IncidentSeverity::class,
        'status' => SupplierAssessmentStatus::class,
        'has_nda' => 'boolean',
        'has_dpa' => 'boolean',
        'audit_right' => 'boolean',
        'last_review_on' => 'date',
        'next_review_on' => 'date',
    ];

    /** Anzeige-Kennung im Register (z. B. "SA-12"). */
    public function displayNo(): string {
        return 'SA-' . $this->assessment_no;
    }

    /** Anzeigename: verknüpfter Lieferant, sonst der Freitext-Name. */
    public function displayName(): string {
        return (string) ($this->supplier?->name ?: $this->supplier_name);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Bewertungen mit überfälligem Review (next_review_on überschritten),
     * die NICHT abschließend freigegeben sind — die Kennzahl „ungeprüfte
     * Lieferanten" des Auditbereitschafts-Dashboards.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReviewOverdue(Builder $query): Builder {
        return $query->whereNotNull('next_review_on')
            ->whereDate('next_review_on', '<', now()->toDateString())
            ->where('status', '!=', SupplierAssessmentStatus::Approved->value);
    }

    /** next_review_on überschritten und nicht freigegeben? (Listen-Badge) */
    public function isReviewOverdue(): bool {
        return $this->next_review_on !== null
            && ! $this->status->isApproved()
            && $this->next_review_on->startOfDay()->lt(now()->startOfDay());
    }
}
