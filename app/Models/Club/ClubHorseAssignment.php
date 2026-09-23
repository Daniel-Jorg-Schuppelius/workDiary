<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseAssignment.php
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
 * Zuordnung Reiter–Pferd je Reitstunde (MVP-854): genau eine je Mitglied und
 * Termin; das Pferd ist über seine Ressourcenbelegung gegen Doppelvergabe
 * geschützt. Teilnahme mit eigenem Pferd ohne Vereinsprofil ist ausdrücklich.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_member_id
 * @property int|null $club_horse_id
 * @property int|null $club_resource_booking_id
 * @property bool $own_horse
 * @property string|null $override_note
 * @property Carbon|null $needs_review_at
 * @property string|null $review_reason
 * @property int|null $assigned_by_user_id
 */
class ClubHorseAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_member_id', 'club_horse_id', 'club_resource_booking_id', 'own_horse', 'override_note', 'needs_review_at', 'review_reason', 'assigned_by_user_id'];

    protected $casts = ['own_horse' => 'boolean', 'needs_review_at' => 'datetime'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubHorse, $this> */
    public function horse(): BelongsTo {
        return $this->belongsTo(ClubHorse::class, 'club_horse_id');
    }

    /** @return BelongsTo<ClubResourceBooking, $this> */
    public function booking(): BelongsTo {
        return $this->belongsTo(ClubResourceBooking::class, 'club_resource_booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function needsReview(): bool {
        return $this->needs_review_at !== null;
    }
}
