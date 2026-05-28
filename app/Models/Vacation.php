<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Vacation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\HolidayService;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property VacationType $type
 * @property VacationStatus $status
 * @property string|null $note
 * @property string|null $reject_reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Vacation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'start_date',
        'end_date',
        'type',
        'status',
        'note',
        'reject_reason',
        'decided_by',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'decided_at' => 'datetime',
        'type' => VacationType::class,
        'status' => VacationStatus::class,
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** @param Builder<Vacation> $query */
    public function scopePending(Builder $query): void {
        $query->where('status', VacationStatus::Pending);
    }

    /** @param Builder<Vacation> $query */
    public function scopeApproved(Builder $query): void {
        $query->where('status', VacationStatus::Approved);
    }

    /**
     * Einträge, die sich mit dem Zeitraum überschneiden.
     *
     * @param  Builder<Vacation>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void {
        $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Anzahl Werktage (Mo–Fr, ohne Feiertage). */
    public function workingDays(HolidayService $holidayService): int {
        $count = 0;
        $cursor = $this->start_date->copy();
        while ($cursor->lte($this->end_date)) {
            if ($cursor->isWeekday() && ! $holidayService->isHoliday($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function typeLabel(): string {
        return $this->type->label();
    }

    public function statusLabel(): string {
        return $this->status->label();
    }

    /** DaisyUI badge tone */
    public function statusTone(): string {
        return $this->status->tone();
    }
}
