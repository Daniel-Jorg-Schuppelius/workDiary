<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceRevision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubAttendanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Begründete Korrektur eines Nachweises nach der ersten Bestätigung
 * (MVP-844): vorher/nachher, Grund, Akteur, Stand des Sperrzählers.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_attendance_record_id
 * @property int $sheet_version
 * @property ClubAttendanceStatus|null $previous_status
 * @property int|null $previous_minutes
 * @property ClubAttendanceStatus $status
 * @property int|null $minutes
 * @property string $reason
 * @property int|null $actor_user_id
 */
class ClubAttendanceRevision extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'club_attendance_record_id',
        'sheet_version',
        'previous_status',
        'previous_minutes',
        'status',
        'minutes',
        'reason',
        'actor_user_id',
    ];

    protected $casts = [
        'sheet_version' => 'integer',
        'previous_status' => ClubAttendanceStatus::class,
        'previous_minutes' => 'integer',
        'status' => ClubAttendanceStatus::class,
        'minutes' => 'integer',
    ];

    /** @return BelongsTo<ClubAttendanceRecord, $this> */
    public function record(): BelongsTo {
        return $this->belongsTo(ClubAttendanceRecord::class, 'club_attendance_record_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
