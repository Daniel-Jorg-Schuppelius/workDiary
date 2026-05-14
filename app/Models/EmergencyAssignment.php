<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Carbon\Carbon;
use Database\Factories\EmergencyAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon $start_at
 * @property Carbon $end_at
 */
class EmergencyAssignment extends Model
{
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<EmergencyAssignmentFactory> */
    use HasFactory;
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

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OnCallShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * @param  Builder<EmergencyAssignment>  $query
     * @return Builder<EmergencyAssignment>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
