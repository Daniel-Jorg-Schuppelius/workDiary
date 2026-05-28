<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shift\{DutyPlanPeriodType, DutyPlanStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Database\Factories\DutyPlanFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property DutyPlanStatus $status
 * @property DutyPlanPeriodType $period_type
 */
class DutyPlan extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<DutyPlanFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'title',
        'period_type',
        'from_date',
        'to_date',
        'status',
        'min_staff',
        'note',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'min_staff' => 'integer',
        'status' => DutyPlanStatus::class,
        'period_type' => DutyPlanPeriodType::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<ScheduledShift, $this> */
    public function shifts(): HasMany {
        return $this->hasMany(ScheduledShift::class);
    }

    /** @return HasMany<CoverageRequirement, $this> */
    public function coverageRequirements(): HasMany {
        return $this->hasMany(CoverageRequirement::class);
    }

    public function isDraft(): bool {
        return $this->status === DutyPlanStatus::Draft;
    }

    public function isPublished(): bool {
        return $this->status === DutyPlanStatus::Published;
    }

    /**
     * Nur Entwürfe.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopeDraft(Builder $query): Builder {
        return $query->where('status', DutyPlanStatus::Draft->value);
    }

    /**
     * Nur veröffentlichte Pläne.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopePublished(Builder $query): Builder {
        return $query->where('status', DutyPlanStatus::Published->value);
    }

    /**
     * Pläne die einen bestimmten Datumsbereich überschneiden.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopeInPeriod(Builder $query, string $from, string $to): Builder {
        return $query->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from);
    }
}
