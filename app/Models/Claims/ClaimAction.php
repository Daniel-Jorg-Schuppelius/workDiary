<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\{ClaimActionKind, ClaimActionStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Maßnahme (MVP-251): Nacharbeit/Reparatur/Ersatz/Serviceeinsatz usw.;
 * erzeugte Folgeobjekte (Auftrag, Ticket, Bestellung) hängen als Morph.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property ClaimActionKind $kind
 * @property ClaimActionStatus $status
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $done_at
 */
class ClaimAction extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'claim_case_id', 'kind', 'status', 'title', 'note',
        'assigned_user_id', 'due_at', 'done_at', 'follow_up_type',
        'follow_up_id', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ClaimActionKind::class,
        'status' => ClaimActionStatus::class,
        'due_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function followUp(): MorphTo {
        return $this->morphTo('follow_up');
    }
}
