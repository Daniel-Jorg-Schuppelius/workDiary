<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shift\ShiftExchangeStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Database\Factories\ShiftExchangeFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Schichttausch-Antrag mit Freigabe-Workflow (Feature 007).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $scheduled_shift_id
 * @property int $requested_by_user_id
 * @property int|null $target_user_id
 * @property int|null $offered_shift_id
 * @property ShiftExchangeStatus $status
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShiftExchange extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ShiftExchangeFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'scheduled_shift_id',
        'requested_by_user_id',
        'target_user_id',
        'offered_shift_id',
        'status',
        'decided_by_user_id',
        'decided_at',
        'reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ShiftExchangeStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ScheduledShift, $this> */
    public function scheduledShift(): BelongsTo {
        return $this->belongsTo(ScheduledShift::class);
    }

    /** @return BelongsTo<ScheduledShift, $this> */
    public function offeredShift(): BelongsTo {
        return $this->belongsTo(ScheduledShift::class, 'offered_shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function targetUser(): BelongsTo {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function statusLabel(): string {
        return $this->status->label();
    }

    public function statusTone(): string {
        return $this->status->tone();
    }

    /** Echter Tausch (mit Gegenschicht) vs. reine Abgabe. */
    public function isSwap(): bool {
        return $this->offered_shift_id !== null;
    }

    /**
     * @param  Builder<ShiftExchange>  $query
     * @return Builder<ShiftExchange>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->whereIn('status', [
            ShiftExchangeStatus::Requested->value,
            ShiftExchangeStatus::Accepted->value,
        ]);
    }

    /**
     * @param  Builder<ShiftExchange>  $query
     * @return Builder<ShiftExchange>
     */
    public function scopeForStatus(Builder $query, ShiftExchangeStatus $status): Builder {
        return $query->where('status', $status->value);
    }
}
