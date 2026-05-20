<?php

/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShift.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Database\Factories\ScheduledShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $duty_plan_id
 * @property int $user_id
 * @property int|null $shift_type_id
 * @property Carbon $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $note
 * @property ScheduledShiftStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScheduledShift extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ScheduledShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'duty_plan_id',
        'user_id',
        'shift_type_id',
        'date',
        'start_time',
        'end_time',
        'note',
        'status',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'status' => ScheduledShiftStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DutyPlan, $this> */
    public function dutyPlan(): BelongsTo
    {
        return $this->belongsTo(DutyPlan::class);
    }

    /** @return BelongsTo<ShiftType, $this> */
    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

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

    /**
     * Resolved start_time: own value or ShiftType default.
     */
    public function resolvedStartTime(): ?string
    {
        return $this->start_time ?? $this->shiftType?->default_start_time;
    }

    /**
     * Resolved end_time: own value or ShiftType default.
     */
    public function resolvedEndTime(): ?string
    {
        return $this->end_time ?? $this->shiftType?->default_end_time;
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function statusTone(): string
    {
        return $this->status->tone();
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForDate(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('date', $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date);
    }

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForDateRange(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        $fromStr = $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from;
        $toStr = $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to;

        return $query->whereBetween('date', [$fromStr, $toStr]);
    }

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ScheduledShiftStatus::Published->value);
    }

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ScheduledShiftStatus::Draft->value);
    }

    /**
     * @param  Builder<ScheduledShift>  $query
     * @return Builder<ScheduledShift>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ScheduledShiftStatus::Published->value,
            ScheduledShiftStatus::Confirmed->value,
        ]);
    }
}
