<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupMembership.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubGroupMembershipStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zuordnung Mitglied ↔ Gruppe mit Gültigkeit (MVP-842). `valid_to` ist der
 * letzte gültige Tag; beendete und abgelehnte Zuordnungen bleiben als
 * Historie. Geschrieben ausschließlich vom ClubGroupService.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_group_id
 * @property int $club_member_id
 * @property ClubGroupMembershipStatus $status
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $note
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 */
class ClubGroupMembership extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'club_group_id',
        'club_member_id',
        'status',
        'valid_from',
        'valid_to',
        'note',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'status' => ClubGroupMembershipStatus::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** Aktiv und am Stichtag gültig. */
    public function isCurrentOn(CarbonInterface $date): bool {
        $day = $date->copy()->startOfDay();

        return $this->status === ClubGroupMembershipStatus::Active
            && $this->valid_from->lessThanOrEqualTo($day)
            && ($this->valid_to === null || $this->valid_to->greaterThanOrEqualTo($day));
    }
}
