<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceConfirmation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bestätigter Stand einer Anwesenheitsliste (MVP-844): laufende Nummer je
 * Liste und Schnappschuss aller Nachweise — spätere Korrekturen ändern den
 * Schnappschuss nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_attendance_sheet_id
 * @property int $version
 * @property int|null $conducted_minutes
 * @property list<array{member_id:int, status:string, minutes:int|null}> $snapshot
 * @property int|null $confirmed_by_user_id
 * @property Carbon $confirmed_at
 */
class ClubAttendanceConfirmation extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'club_attendance_sheet_id',
        'version',
        'conducted_minutes',
        'snapshot',
        'confirmed_by_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'conducted_minutes' => 'integer',
        'snapshot' => 'array',
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubAttendanceSheet, $this> */
    public function sheet(): BelongsTo {
        return $this->belongsTo(ClubAttendanceSheet::class, 'club_attendance_sheet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
