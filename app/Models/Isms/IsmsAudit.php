<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAudit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{AuditKind, AuditStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Audit (Feature 046, Inkrement C): geplantes/durchgeführtes Audit je
 * Geltungsbereich mit laufender Nummer je Organisation (audit_no, Muster
 * risk_no), Kriterien/Umfang, Auditoren inkl. Unabhängigkeitsprüfung und
 * Statuskette planned → inPreparation → inProgress → reportIssued →
 * closed. Übergänge und Feststellungs-Regeln erzwingt der
 * {@see \App\Services\Isms\AuditService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property int $audit_no
 * @property string $title
 * @property string|null $norm
 * @property string|null $edition
 * @property AuditKind $kind
 * @property AuditStatus $status
 * @property Carbon|null $planned_on
 * @property Carbon|null $performed_from
 * @property Carbon|null $performed_to
 * @property int|null $lead_auditor_user_id
 * @property string|null $auditors
 * @property string|null $criteria
 * @property string|null $independence_note
 * @property string|null $summary
 * @property-read int|null $findings_count
 * @property-read int|null $open_findings_count
 */
class IsmsAudit extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsAuditFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'isms_audit_program_id',
        'audit_no',
        'title',
        'norm',
        'edition',
        'kind',
        'status',
        'planned_on',
        'performed_from',
        'performed_to',
        'lead_auditor_user_id',
        'auditors',
        'criteria',
        'independence_note',
        'summary',
    ];

    protected $casts = [
        'audit_no' => 'integer',
        'kind' => AuditKind::class,
        'status' => AuditStatus::class,
        'planned_on' => 'date',
        'performed_from' => 'date',
        'performed_to' => 'date',
    ];

    /** Anzeige-Kennung, z. B. "A-12" (analog IsmsRisk::displayNo()). */
    public function displayNo(): string {
        return 'A-' . $this->audit_no;
    }

    /** Anzeige der geprüften Norm, z. B. "ISO/IEC 27001:2022" (optional). */
    public function normLabel(): ?string {
        if ($this->norm === null || $this->norm === '') {
            return null;
        }

        return $this->edition !== null && $this->edition !== ''
            ? $this->norm . ':' . $this->edition
            : $this->norm;
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsTo<User, $this> */
    public function leadAuditor(): BelongsTo {
        return $this->belongsTo(User::class, 'lead_auditor_user_id');
    }

    /** @return HasMany<IsmsAuditFinding, $this> */
    public function findings(): HasMany {
        return $this->hasMany(IsmsAuditFinding::class, 'isms_audit_id');
    }

    /** @return HasMany<IsmsAuditFinding, $this> */
    public function openFindings(): HasMany {
        return $this->findings()->where('status', '!=', \App\Enums\Isms\FindingStatus::Closed->value);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<IsmsAuditProgram, $this> */
    public function program(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(IsmsAuditProgram::class, 'isms_audit_program_id');
    }
}
