<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventParticipation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubParticipationSource, ClubParticipationStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Event, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Teilnahme eines Mitglieds an einem Vereinstermin (MVP-843): Anmeldung,
 * Warteliste, Einladung, Absage — genau eine Zeile je Termin und Mitglied,
 * auch wenn das Mitglied ein Konto hat. Geschrieben nur vom ClubEventService.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_member_id
 * @property ClubParticipationStatus $status
 * @property ClubParticipationSource $source
 * @property Carbon|null $registered_at
 * @property int|null $registered_by_user_id
 * @property int|null $club_guardian_id
 * @property Carbon|null $promoted_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property string|null $note
 */
class ClubEventParticipation extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'event_id',
        'club_member_id',
        'status',
        'source',
        'registered_at',
        'registered_by_user_id',
        'club_guardian_id',
        'promoted_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'note',
    ];

    protected $casts = [
        'status' => ClubParticipationStatus::class,
        'source' => ClubParticipationSource::class,
        'registered_at' => 'datetime',
        'promoted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    /** @return BelongsTo<ClubGuardian, $this> */
    public function guardian(): BelongsTo {
        return $this->belongsTo(ClubGuardian::class, 'club_guardian_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isActive(): bool {
        return $this->status->isActive();
    }
}
