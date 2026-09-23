<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceClearance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Einweisungs-/Eignungsfreigabe eines Mitglieds für eine Ressource (MVP-853),
 * z. B. Boot oder Gerät; befristbar, von der Leitung erteilt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_resource_id
 * @property int $club_member_id
 * @property Carbon $granted_on
 * @property Carbon|null $valid_to
 * @property int|null $granted_by_user_id
 * @property string|null $note
 */
class ClubResourceClearance extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_resource_id', 'club_member_id', 'granted_on', 'valid_to', 'granted_by_user_id', 'note'];

    protected $casts = ['granted_on' => 'date', 'valid_to' => 'date'];

    /** @return BelongsTo<ClubResource, $this> */
    public function resource(): BelongsTo {
        return $this->belongsTo(ClubResource::class, 'club_resource_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isValidOn(CarbonInterface $day): bool {
        return ! $day->lt($this->granted_on->startOfDay()) && ($this->valid_to === null || ! $day->gt($this->valid_to->endOfDay()));
    }
}
