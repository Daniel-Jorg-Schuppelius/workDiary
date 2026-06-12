<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Tagesabschluss (MVP-015, docs/tagesabschluss.md §3).
 *
 * Eine Zeile pro Mitarbeitenden × Kalendertag; entsteht beim ersten Öffnen
 * der Tagesabschluss-Seite (Audit `dayClose.opened`). Statusmaschine:
 * open → closed → correction → open; der Anzeige-Status `locked` wird aus
 * der Monatsfreigabe (MVP-016) abgeleitet und nie persistiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property Carbon $day
 * @property DayClosureStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 * @property Carbon|null $reopened_at
 * @property int|null $reopened_by_user_id
 * @property string|null $reopen_reason
 * @property bool $attendance_locked
 */
class DayClosure extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<\Database\Factories\DayClosureFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'day',
        'status',
        'closed_at',
        'closed_by_user_id',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
        'attendance_locked',
    ];

    protected $casts = [
        'day' => 'date',
        'status' => DayClosureStatus::class,
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'attendance_locked' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    /** @return HasMany<DayCorrectionRequest, $this> */
    public function correctionRequests(): HasMany {
        return $this->hasMany(DayCorrectionRequest::class)->orderByDesc('id');
    }

    public function isOpen(): bool {
        return $this->status === DayClosureStatus::Open;
    }

    public function isClosed(): bool {
        return $this->status === DayClosureStatus::Closed;
    }

    public function inCorrection(): bool {
        return $this->status === DayClosureStatus::Correction;
    }

    /** Anzeige-Label des Tages, z. B. „12.06.2026". */
    public function dayLabel(): string {
        // CarbonFmt direkt statt ->fdate()-Macro: typsicher für PHPStan.
        return \App\Support\CarbonFmt::fdate($this->day);
    }
}
