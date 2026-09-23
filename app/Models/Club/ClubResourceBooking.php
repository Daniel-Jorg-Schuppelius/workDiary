<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Belegung einer Ressource durch einen Termin (MVP-853) mit Menge, Fenster und
 * Auf-/Abbaupuffer; eine Sperrzeit markiert sie zur Neuplanung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_resource_id
 * @property int $event_id
 * @property int|null $club_member_id
 * @property int $quantity
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $setup_minutes
 * @property int $teardown_minutes
 * @property string|null $note
 * @property Carbon|null $flagged_at
 * @property string|null $flag_reason
 * @property int|null $created_by_user_id
 */
class ClubResourceBooking extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_resource_id', 'event_id', 'club_member_id', 'quantity', 'starts_at', 'ends_at',
        'setup_minutes', 'teardown_minutes', 'note', 'flagged_at', 'flag_reason', 'created_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'setup_minutes' => 'integer',
        'teardown_minutes' => 'integer',
        'flagged_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubResource, $this> */
    public function resource(): BelongsTo {
        return $this->belongsTo(ClubResource::class, 'club_resource_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Belegtes Fenster einschließlich Puffer. */
    public function blockStart(): Carbon {
        return $this->starts_at->copy()->subMinutes($this->setup_minutes);
    }

    public function blockEnd(): Carbon {
        return $this->ends_at->copy()->addMinutes($this->teardown_minutes);
    }

    public function isFlagged(): bool {
        return $this->flagged_at !== null;
    }
}
