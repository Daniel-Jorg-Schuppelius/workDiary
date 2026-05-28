<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid, HasTags};
use App\Models\Contracts\HasTimeWindow;
use Carbon\Carbon;
use Database\Factories\EmergencyAssignmentFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $legacy_id
 * @property int $user_id
 * @property int|null $on_call_shift_id
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string|null $reason
 * @property bool $is_archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmergencyAssignment extends Model implements HasTimeWindow {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<EmergencyAssignmentFactory> */
    use HasFactory;

    use HasSqid;

    use HasTags;

    protected $fillable = [
        'organization_id',
        'legacy_id',
        'user_id',
        'on_call_shift_id',
        'start_at',
        'end_at',
        'reason',
        'is_archived',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OnCallShift, $this> */
    public function shift(): BelongsTo {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * @param  Builder<EmergencyAssignment>  $query
     * @return Builder<EmergencyAssignment>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    public function getStartAt(): ?Carbon {
        return $this->start_at;
    }

    public function getEndAt(): ?Carbon {
        return $this->end_at;
    }
}
