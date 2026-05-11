<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ScheduledShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledShift extends Model {
    /** @use HasFactory<ScheduledShiftFactory> */
    use HasFactory;
    use Auditable;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public static array $statuses = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
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

    protected function casts(): array {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ShiftType, $this> */
    public function shiftType(): BelongsTo {
        return $this->belongsTo(ShiftType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Resolved start_time: own value or ShiftType default.
     */
    public function resolvedStartTime(): ?string {
        return $this->start_time ?? $this->shiftType?->default_start_time;
    }

    /**
     * Resolved end_time: own value or ShiftType default.
     */
    public function resolvedEndTime(): ?string {
        return $this->end_time ?? $this->shiftType?->default_end_time;
    }

    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_DRAFT     => __('Entwurf'),
            self::STATUS_PUBLISHED => __('Veröffentlicht'),
            self::STATUS_CONFIRMED => __('Bestätigt'),
            self::STATUS_CANCELLED => __('Abgesagt'),
            default                => $this->status,
        };
    }

    public function statusTone(): string {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'info',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_CANCELLED => 'error',
            default                => 'ghost', // draft
        };
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForDate(Builder $query, \DateTimeInterface|string $date): Builder {
        return $query->whereDate('date', $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date);
    }

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForDateRange(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder {
        $fromStr = $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from;
        $toStr   = $to instanceof \DateTimeInterface   ? $to->format('Y-m-d')   : $to;

        return $query->whereBetween('date', [$fromStr, $toStr]);
    }

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopePublished(Builder $query): Builder {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopeDraft(Builder $query): Builder {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * @param Builder<ScheduledShift> $query
     * @return Builder<ScheduledShift>
     */
    public function scopeVisible(Builder $query): Builder {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_CONFIRMED]);
    }
}
