<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCorrectionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeApproval\DayCorrectionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Korrekturantrag zu einem Tagesabschluss (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §5).
 *
 * Org-scoped (konsistent mit {@see TimeCorrectionRequest}): eigene
 * organization_id + OrganizationScope, damit Anträge nie mandanten-
 * übergreifend sichtbar werden. Workflow: pending → approved|rejected,
 * Entscheidung durch dayClose.approveCorrection-Berechtigte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $day_closure_id
 * @property int $requested_by_user_id
 * @property string $reason
 * @property DayCorrectionStatus $status
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 */
class DayCorrectionRequest extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<\Database\Factories\DayCorrectionRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'day_closure_id',
        'requested_by_user_id',
        'reason',
        'status',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
    ];

    protected $casts = [
        'status' => DayCorrectionStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<DayClosure, $this> */
    public function dayClosure(): BelongsTo {
        return $this->belongsTo(DayClosure::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool {
        return $this->status === DayCorrectionStatus::Pending;
    }
}
