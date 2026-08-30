<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimeSession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\{Attendance, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lernsitzung (Feature 149, MVP-749): Beginn, Ende und aktive Dauer einer
 * Lernphase, eingeordnet als innerhalb oder außerhalb der Arbeitszeit.
 *
 * Zweckgebunden — Arbeitszeitnachweis und Abschlusskriterium, **kein**
 * Verhaltensprofil. Deshalb gibt es hier weder Seitenaufrufe noch
 * Scrolltiefe, und die Führungskraft sieht Summen statt Verläufe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int|null $learning_unit_id
 * @property int|null $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $active_seconds
 * @property string $source
 * @property string|null $classification
 * @property int|null $attendance_id
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $approval_status
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property string|null $approval_note
 * @property-read User|null $user
 * @property-read LearningEnrollment|null $enrollment
 * @property-read Attendance|null $attendance
 */
class LearningTimeSession extends Model {
    /** Freigabestatus (nur bei Zeitpolitik „Freigabe nötig"). */
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_unit_id',
        'user_id',
        'started_at',
        'ended_at',
        'active_seconds',
        'source',
        'classification',
        'attendance_id',
        'last_heartbeat_at',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'approval_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'approved_at' => 'datetime',
        'active_seconds' => 'integer',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** Nachweis der außerhalb der Arbeitszeit geleisteten Lernzeit. */
    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    public function isOpen(): bool {
        return $this->ended_at === null;
    }

    public function activeMinutes(): int {
        return (int) ceil($this->active_seconds / 60);
    }
}
