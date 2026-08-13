<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Database\Factories\CoverageRequirementFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soll-Besetzung pro Schichttyp, granuliert auf Wochentag oder Datum.
 *
 * Auflösungsreihenfolge bei der Berechnung von Soll-Werten für ein Datum:
 *  1. specific_date == Datum               (höchste Priorität)
 *  2. weekday      == ISO-Wochentag        (im selben DutyPlan)
 *  3. duty_plan_id IS NULL Fallback        (org-weit)
 *  4. DutyPlan.min_staff                   (Plan-Default)
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $duty_plan_id
 * @property int $shift_type_id
 * @property int|null $weekday 0=So, 1=Mo, …, 6=Sa
 * @property Carbon|null $specific_date
 * @property int $min_staff
 * @property int|null $max_staff
 * @property array<int, int>|null $required_qualification_ids
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CoverageRequirement extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<CoverageRequirementFactory> */
    use HasFactory;

    use HasSqid;

    public const WEEKDAY_SUNDAY = 0;

    public const WEEKDAY_MONDAY = 1;

    public const WEEKDAY_TUESDAY = 2;

    public const WEEKDAY_WEDNESDAY = 3;

    public const WEEKDAY_THURSDAY = 4;

    public const WEEKDAY_FRIDAY = 5;

    public const WEEKDAY_SATURDAY = 6;

    protected $fillable = [
        'organization_id',
        'duty_plan_id',
        'shift_type_id',
        'weekday',
        'specific_date',
        'min_staff',
        'max_staff',
        'ideal_staff',
        'required_qualification_ids',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'specific_date' => 'date',
        'min_staff' => 'integer',
        'max_staff' => 'integer',
        'ideal_staff' => 'integer',
        'weekday' => 'integer',
        'required_qualification_ids' => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /** @return BelongsTo<DutyPlan, $this> */
    public function dutyPlan(): BelongsTo {
        return $this->belongsTo(DutyPlan::class);
    }

    /** @return BelongsTo<ShiftType, $this> */
    public function shiftType(): BelongsTo {
        return $this->belongsTo(ShiftType::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** @param Builder<CoverageRequirement> $query */
    public function scopeForPlan(Builder $query, ?int $dutyPlanId): void {
        if ($dutyPlanId === null) {
            $query->whereNull('duty_plan_id');

            return;
        }
        $query->where(function (Builder $q) use ($dutyPlanId): void {
            $q->where('duty_plan_id', $dutyPlanId)->orWhereNull('duty_plan_id');
        });
    }

    /** @param Builder<CoverageRequirement> $query */
    public function scopeForDate(Builder $query, \DateTimeInterface $date): void {
        $weekday = (int) $date->format('w');
        $query->where(function (Builder $q) use ($date, $weekday): void {
            $q->where('specific_date', $date->format('Y-m-d'))
                ->orWhere(function (Builder $q2) use ($weekday): void {
                    $q2->whereNull('specific_date')->where('weekday', $weekday);
                });
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function appliesToDate(\DateTimeInterface $date): bool {
        if ($this->specific_date !== null) {
            return $this->specific_date->isSameDay($date);
        }
        if ($this->weekday !== null) {
            return (int) $date->format('w') === $this->weekday;
        }

        return false;
    }

    /**
     * Prio: specific_date (3) > weekday (2) > generisch (1).
     * Höhere Werte überschreiben niedrigere.
     */
    public function priority(): int {
        if ($this->specific_date !== null) {
            return 3;
        }
        if ($this->weekday !== null) {
            return 2;
        }

        return 1;
    }
}
