<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\OvertimeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Überstunden-Antrag (MVP-519): Mehrzeit über die Rahmenzeit hinaus wird
 * beantragt und durch die Teamleitung genehmigt oder abgelehnt. Die
 * Genehmigung ist Governance/Dokumentation — die Zeitkonten rechnen
 * unverändert über FlexCalculator; der Antrag legitimiert die Mehrzeit
 * und quittiert den zugehörigen Plausibilitäts-Befund.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $requested_by_user_id
 * @property Carbon $scope_date
 * @property int $minutes
 * @property string $reason
 * @property OvertimeRequestStatus $status
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OvertimeRequest extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<OvertimeRequestFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'requested_by_user_id',
        'scope_date',
        'minutes',
        'reason',
        'status',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scope_date' => 'date',
        'minutes' => 'integer',
        'status' => OvertimeRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
