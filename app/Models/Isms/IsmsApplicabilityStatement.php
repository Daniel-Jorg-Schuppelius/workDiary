<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsApplicabilityStatement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsApplicabilityStatementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SoA-Aussage je Geltungsbereich + Anforderung (Feature 044/046):
 * Anwendbarkeit, Begründung (Pflicht bei applicable = false — Regel im
 * {@see \App\Services\Isms\RequirementService}), Umsetzungsstatus und
 * Nachweisverweis. Unique je (Scope, Anforderung); Autorisierung läuft
 * über die {@see \App\Policies\Isms\IsmsRequirementPolicy}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property int $isms_requirement_id
 * @property bool $applicable
 * @property string|null $justification
 * @property ControlImplementationStatus $implementation_status
 * @property string|null $evidence_note
 */
class IsmsApplicabilityStatement extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsApplicabilityStatementFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'isms_requirement_id',
        'applicable',
        'justification',
        'implementation_status',
        'evidence_note',
    ];

    protected $casts = [
        'applicable' => 'boolean',
        'implementation_status' => ControlImplementationStatus::class,
    ];

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsTo<IsmsRequirement, $this> */
    public function requirement(): BelongsTo {
        return $this->belongsTo(IsmsRequirement::class, 'isms_requirement_id');
    }

    /**
     * Anwendbare Aussagen (SoA: applicable = true).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplicable(Builder $query): Builder {
        return $query->where('applicable', true);
    }
}
