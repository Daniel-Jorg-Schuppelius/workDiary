<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchAvailability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubAvailabilityStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verfügbarkeit eines Mitglieds für einen Spieltag (MVP-852) — Zusage ist keine Nominierung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_member_id
 * @property ClubAvailabilityStatus $status
 * @property string|null $note
 * @property Carbon $responded_at
 * @property int|null $responded_by_user_id
 */
class ClubMatchAvailability extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_member_id', 'status', 'note', 'responded_at', 'responded_by_user_id'];

    protected $casts = ['status' => ClubAvailabilityStatus::class, 'responded_at' => 'datetime'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
