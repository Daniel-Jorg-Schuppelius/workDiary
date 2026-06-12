<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{FindingKind, FindingStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsAuditFindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Auditfeststellung (Feature 046, Inkrement C): Nichtkonformität
 * (major/minor), Beobachtung oder Verbesserung mit laufender Nummer je
 * Audit (finding_no), optionalem Bezug auf die betroffene Normanforderung
 * und Statuskette open → inCorrection → effectivenessCheck → closed.
 * Abschlussregeln (alle Korrekturmaßnahmen done/effective; bei
 * Nichtkonformitäten mindestens EINE wirksame Maßnahme) erzwingt der
 * {@see \App\Services\Isms\AuditService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_audit_id
 * @property int $finding_no
 * @property FindingKind $kind
 * @property string $title
 * @property string|null $description
 * @property int|null $isms_requirement_id
 * @property FindingStatus $status
 * @property-read int|null $corrective_actions_count
 */
class IsmsAuditFinding extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsAuditFindingFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_audit_id',
        'finding_no',
        'kind',
        'title',
        'description',
        'isms_requirement_id',
        'status',
    ];

    protected $casts = [
        'finding_no' => 'integer',
        'kind' => FindingKind::class,
        'status' => FindingStatus::class,
    ];

    /** Anzeige-Kennung innerhalb des Audits, z. B. "F-3". */
    public function displayNo(): string {
        return 'F-' . $this->finding_no;
    }

    /**
     * Zugehöriges Audit — bewusst NICHT audit() benannt, das würde den
     * {@see \App\Models\Concerns\Auditable::audit()}-Helper überschreiben.
     *
     * @return BelongsTo<IsmsAudit, $this>
     */
    public function ismsAudit(): BelongsTo {
        return $this->belongsTo(IsmsAudit::class, 'isms_audit_id');
    }

    /** @return BelongsTo<IsmsRequirement, $this> */
    public function requirement(): BelongsTo {
        return $this->belongsTo(IsmsRequirement::class, 'isms_requirement_id');
    }

    /** @return HasMany<IsmsCorrectiveAction, $this> */
    public function correctiveActions(): HasMany {
        return $this->hasMany(IsmsCorrectiveAction::class, 'isms_audit_finding_id');
    }
}
