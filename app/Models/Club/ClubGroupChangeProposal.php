<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupChangeProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubCriteriaResult, ClubProposalStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Wechsel-/Prüfvorschlag (MVP-842): entsteht, wenn ein Mitglied die
 * Kriterien seiner Gruppe nicht mehr erfüllt (Geburtstag, geänderte
 * Grenzen, fehlendes Geburtsdatum). Die Leitung bestätigt mit
 * Wirksamkeitsdatum oder verwirft — nichts wird automatisch entfernt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property int $club_group_id
 * @property ClubCriteriaResult $reason
 * @property ClubProposalStatus $status
 * @property int|null $suggested_group_id
 * @property Carbon|null $effective_on
 * @property string|null $note
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 */
class ClubGroupChangeProposal extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'club_member_id',
        'club_group_id',
        'reason',
        'status',
        'suggested_group_id',
        'effective_on',
        'note',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'reason' => ClubCriteriaResult::class,
        'status' => ClubProposalStatus::class,
        'effective_on' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<ClubGroup, $this> */
    public function suggestedGroup(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'suggested_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isOpen(): bool {
        return $this->status === ClubProposalStatus::Open;
    }
}
