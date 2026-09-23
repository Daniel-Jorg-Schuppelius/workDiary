<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubEventRoleKind;
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terminrolle (MVP-852): Schiedsrichter, Zeitnehmer, Fahrdienst … je Termin;
 * Mitglied, Benutzer oder externer Name. Zählt als Teilnahme, nicht als Kaderplatz.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property ClubEventRoleKind $role
 * @property int|null $club_member_id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $note
 */
class ClubEventRole extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'role', 'club_member_id', 'user_id', 'name', 'note'];

    protected $casts = ['role' => ClubEventRoleKind::class];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function holderName(): string {
        if ($this->member !== null) {
            return $this->member->fullName();
        }
        if ($this->user !== null) {
            return $this->user->name;
        }

        return (string) $this->name;
    }
}
