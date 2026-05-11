<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $id
 * @property int                             $user_id
 * @property \Carbon\Carbon                  $start_date
 * @property \Carbon\Carbon                  $end_date
 * @property string                          $type
 * @property string                          $status
 * @property string|null                     $note
 * @property string|null                     $reject_reason
 * @property int|null                        $decided_by
 * @property \Carbon\Carbon|null             $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Vacation extends Model {
    use Auditable;

    public const TYPE_VACATION   = 'vacation';
    public const TYPE_SICK       = 'sick';
    public const TYPE_SPECIAL    = 'special';
    public const TYPE_UNPAID     = 'unpaid';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public static array $types = [
        self::TYPE_VACATION,
        self::TYPE_SICK,
        self::TYPE_SPECIAL,
        self::TYPE_UNPAID,
    ];

    /** @var list<string> */
    public static array $statuses = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
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

    protected function casts(): array {
        return [
            'start_date'  => 'date',
            'end_date'    => 'date',
            'decided_at'  => 'datetime',
        ];
    }

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
        $query->where('status', self::STATUS_PENDING);
    }

    /** @param Builder<Vacation> $query */
    public function scopeApproved(Builder $query): void {
        $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Einträge, die sich mit dem Zeitraum überschneiden.
     *
     * @param Builder<Vacation> $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void {
        $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Anzahl Werktage (Mo–Fr, ohne Feiertage). */
    public function workingDays(\App\Services\HolidayService $holidayService): int {
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
        return match ($this->type) {
            self::TYPE_VACATION => __('Urlaub'),
            self::TYPE_SICK     => __('Krank'),
            self::TYPE_SPECIAL  => __('Sonderurlaub'),
            self::TYPE_UNPAID   => __('Unbezahlt'),
            default             => $this->type,
        };
    }

    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_PENDING   => __('Ausstehend'),
            self::STATUS_APPROVED  => __('Genehmigt'),
            self::STATUS_REJECTED  => __('Abgelehnt'),
            self::STATUS_CANCELLED => __('Storniert'),
            default                => $this->status,
        };
    }

    /** DaisyUI badge tone */
    public function statusTone(): string {
        return match ($this->status) {
            self::STATUS_APPROVED  => 'success',
            self::STATUS_REJECTED  => 'error',
            self::STATUS_CANCELLED => 'ghost',
            default                => 'warning',
        };
    }
}
