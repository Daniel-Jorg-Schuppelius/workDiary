<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsCorrectiveAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\CorrectiveActionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsCorrectiveActionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Korrekturmaßnahme zu einer Auditfeststellung (Feature 046, Inkrement C):
 * Ursachenanalyse, Maßnahmenplan, Verantwortlicher, Fälligkeit und
 * Wirksamkeitsprüfung (effective/ineffective NUR mit Pflicht-Notiz —
 * {@see \App\Services\Isms\AuditService::transitionAction()}; ineffective
 * setzt die Feststellung zurück auf inCorrection). Überfällige Maßnahmen
 * meldet der Fristen-Scanner (isms.correctiveActionOverdue).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_audit_finding_id
 * @property string $title
 * @property string|null $root_cause
 * @property string|null $action_plan
 * @property int|null $owner_user_id
 * @property Carbon|null $due_on
 * @property CorrectiveActionStatus $status
 * @property string|null $effectiveness_note
 * @property Carbon|null $completed_on
 */
class IsmsCorrectiveAction extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsCorrectiveActionFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_audit_finding_id',
        'title',
        'root_cause',
        'action_plan',
        'owner_user_id',
        'due_on',
        'status',
        'effectiveness_note',
        'completed_on',
    ];

    protected $casts = [
        'status' => CorrectiveActionStatus::class,
        'due_on' => 'date',
        'completed_on' => 'date',
    ];

    /** @return BelongsTo<IsmsAuditFinding, $this> */
    public function finding(): BelongsTo {
        return $this->belongsTo(IsmsAuditFinding::class, 'isms_audit_finding_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Überfällige Maßnahmen (due_on überschritten, noch open/inProgress) —
     * Grundlage des Fristen-Scanners (isms.correctiveActionOverdue).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder {
        return $query
            ->whereIn('status', [
                CorrectiveActionStatus::Open->value,
                CorrectiveActionStatus::InProgress->value,
            ])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', Carbon::today());
    }
}
