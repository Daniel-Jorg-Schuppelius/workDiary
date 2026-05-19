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

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Database\Factories\DutyPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon $from_date
 * @property Carbon $to_date
 */
class DutyPlan extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<DutyPlanFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    /** @var list<string> */
    public static array $statuses = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    /** @var list<string> */
    public static array $periodTypes = [
        self::PERIOD_DAILY,
        self::PERIOD_WEEKLY,
        self::PERIOD_MONTHLY,
    ];

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
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<ScheduledShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(ScheduledShift::class);
    }

    /** @return HasMany<CoverageRequirement, $this> */
    public function coverageRequirements(): HasMany
    {
        return $this->hasMany(CoverageRequirement::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Nur Entwürfe.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Nur veröffentlichte Pläne.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Pläne die einen bestimmten Datumsbereich überschneiden.
     *
     * @param  Builder<DutyPlan>  $query
     * @return Builder<DutyPlan>
     */
    public function scopeInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from);
    }
}
