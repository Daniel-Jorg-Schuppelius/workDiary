<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Monatsfreigabe pro Mitarbeitender × Kalendermonat (MVP-016).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $period_year
 * @property int $period_month
 * @property MonthClosureStatus $status
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by_user_id
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 * @property Carbon|null $locked_at
 * @property int|null $locked_by_user_id
 * @property array<string, mixed>|null $totals
 * @property int $days_total
 * @property int $days_with_attendance
 * @property int $days_closed
 * @property int $days_open
 * @property int $warnings_count
 */
class MonthClosure extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'period_year',
        'period_month',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
        'locked_at',
        'locked_by_user_id',
        'totals',
        'days_total',
        'days_with_attendance',
        'days_closed',
        'days_open',
        'warnings_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'status' => MonthClosureStatus::class,
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'locked_at' => 'datetime',
        'totals' => 'array',
        'days_total' => 'integer',
        'days_with_attendance' => 'integer',
        'days_closed' => 'integer',
        'days_open' => 'integer',
        'warnings_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    /** @return HasMany<MonthClosureEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(MonthClosureEvent::class)->orderBy('created_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPeriod(Builder $query, int $year, int $month): Builder {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function periodLabel(): string {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }

    public function isLocked(): bool {
        return $this->status->isLocked();
    }
}
