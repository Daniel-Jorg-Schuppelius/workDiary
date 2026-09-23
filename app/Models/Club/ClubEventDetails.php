<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubEventKind, ClubEventVisibility};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vereinsdetails eines Termins (MVP-843): Art, Sichtbarkeit, Abteilung und
 * Fristen relativ zum Beginn. Genau eine Zeile je `Event`; Zielgruppen liegen
 * in `club_event_groups`, Teilnahmen in `club_event_participations`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property ClubEventKind $kind
 * @property ClubEventVisibility $visibility
 * @property int|null $club_department_id
 * @property string|null $discipline
 * @property int|null $registration_lead_hours
 * @property int|null $cancellation_lead_hours
 */
class ClubEventDetails extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'club_event_details';

    protected $fillable = [
        'organization_id',
        'event_id',
        'kind',
        'visibility',
        'club_department_id',
        'discipline',
        'registration_lead_hours',
        'cancellation_lead_hours',
    ];

    protected $casts = [
        'kind' => ClubEventKind::class,
        'visibility' => ClubEventVisibility::class,
        'registration_lead_hours' => 'integer',
        'cancellation_lead_hours' => 'integer',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubDepartment, $this> */
    public function department(): BelongsTo {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    /** Anmeldeschluss (UTC) oder null ohne Frist. */
    public function registrationClosesAt(Event $event): ?CarbonImmutable {
        return $this->registration_lead_hours === null
            ? null
            : CarbonImmutable::instance($event->started_at)->subHours($this->registration_lead_hours);
    }

    /** Abmeldeschluss (UTC) oder null ohne Frist. */
    public function cancellationClosesAt(Event $event): ?CarbonImmutable {
        return $this->cancellation_lead_hours === null
            ? null
            : CarbonImmutable::instance($event->started_at)->subHours($this->cancellation_lead_hours);
    }
}
