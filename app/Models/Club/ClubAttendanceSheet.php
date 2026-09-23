<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceSheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubAttendanceSheetStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Anwesenheitsliste eines Vereinstermins (MVP-844): genau eine je Termin,
 * offen oder bestätigt, mit Sperrzähler gegen paralleles Überschreiben und
 * der tatsächlich durchgeführten Dauer als Obergrenze je Nachweis. Bestätigte
 * Stände liegen als Schnappschuss in {@see ClubAttendanceConfirmation}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property ClubAttendanceSheetStatus $status
 * @property int|null $conducted_minutes
 * @property int $version
 * @property bool $changed_since_confirmation
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by_user_id
 * @property Carbon|null $first_confirmed_at
 * @property string|null $note
 */
class ClubAttendanceSheet extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'event_id',
        'status',
        'conducted_minutes',
        'version',
        'changed_since_confirmation',
        'confirmed_at',
        'confirmed_by_user_id',
        'first_confirmed_at',
        'note',
    ];

    protected $casts = [
        'status' => ClubAttendanceSheetStatus::class,
        'conducted_minutes' => 'integer',
        'version' => 'integer',
        'changed_since_confirmation' => 'boolean',
        'confirmed_at' => 'datetime',
        'first_confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<ClubAttendanceRecord, $this> */
    public function records(): HasMany {
        return $this->hasMany(ClubAttendanceRecord::class);
    }

    /** @return HasMany<ClubAttendanceConfirmation, $this> */
    public function confirmations(): HasMany {
        return $this->hasMany(ClubAttendanceConfirmation::class)->orderByDesc('version');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function isConfirmed(): bool {
        return $this->status === ClubAttendanceSheetStatus::Confirmed;
    }

    /** Nach der ersten Bestätigung braucht jede Änderung eine Begründung. */
    public function requiresReason(): bool {
        return $this->first_confirmed_at !== null;
    }

    /** Dauer des Termins laut Kalender in ganzen Minuten. */
    public function eventMinutes(Event $event): int {
        return (int) CarbonImmutable::instance($event->started_at)->diffInMinutes(CarbonImmutable::instance($event->ended_at), true);
    }

    /** Obergrenze je Nachweis: durchgeführte Dauer, sonst die Kalenderdauer. */
    public function maxMinutes(Event $event): int {
        return $this->conducted_minutes ?? $this->eventMinutes($event);
    }
}
