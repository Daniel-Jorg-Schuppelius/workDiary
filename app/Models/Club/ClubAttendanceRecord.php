<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceRecord.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubAttendanceStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Anwesenheitsnachweis eines Mitglieds am Termin (MVP-844): Stand, Minuten
 * (ganzzahlig, nie länger als die durchgeführte Dauer), optional Ankunft/
 * Abgang, spontan ergänzt, Überschneidung mit anderem Nachweis. Geschrieben
 * nur vom ClubAttendanceService; Korrekturen tragen Grund, Akteur, Zeitpunkt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_attendance_sheet_id
 * @property int $event_id
 * @property int $club_member_id
 * @property ClubAttendanceStatus $status
 * @property int|null $minutes
 * @property Carbon|null $arrived_at
 * @property Carbon|null $left_at
 * @property bool $spontaneous
 * @property int|null $overlap_event_id
 * @property Carbon|null $overlap_cleared_at
 * @property int|null $overlap_cleared_by_user_id
 * @property int|null $recorded_by_user_id
 * @property Carbon|null $recorded_at
 */
class ClubAttendanceRecord extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'club_attendance_sheet_id',
        'event_id',
        'club_member_id',
        'status',
        'minutes',
        'arrived_at',
        'left_at',
        'spontaneous',
        'overlap_event_id',
        'overlap_cleared_at',
        'overlap_cleared_by_user_id',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected $casts = [
        'status' => ClubAttendanceStatus::class,
        'minutes' => 'integer',
        'arrived_at' => 'datetime',
        'left_at' => 'datetime',
        'spontaneous' => 'boolean',
        'overlap_cleared_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubAttendanceSheet, $this> */
    public function sheet(): BelongsTo {
        return $this->belongsTo(ClubAttendanceSheet::class, 'club_attendance_sheet_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function overlapEvent(): BelongsTo {
        return $this->belongsTo(Event::class, 'overlap_event_id');
    }

    /** @return HasMany<ClubAttendanceRevision, $this> */
    public function revisions(): HasMany {
        return $this->hasMany(ClubAttendanceRevision::class)->orderByDesc('created_at');
    }

    public function hasUnresolvedOverlap(): bool {
        return $this->overlap_event_id !== null && $this->overlap_cleared_at === null;
    }

    /** Gutschrift: nur bestätigte Liste, anwesend/teilweise und keine offene Überschneidung. */
    public function creditableMinutes(ClubAttendanceSheet $sheet): int {
        if (! $sheet->isConfirmed() || ! $this->status->isCreditable() || $this->hasUnresolvedOverlap()) {
            return 0;
        }

        return max(0, (int) $this->minutes);
    }
}
