<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Carbon\Carbon;
use Database\Factories\OnCallShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon $start_at
 * @property Carbon $end_at
 */
class OnCallShift extends Model
{
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<OnCallShiftFactory> */
    use HasFactory;
    use HasTags;

    protected $fillable = [
        'organization_id',
        'legacy_id',
        'user_id',
        'start_at',
        'end_at',
        'note',
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

    /** @return HasMany<EmergencyAssignment, $this> */
    public function emergencyAssignments(): HasMany
    {
        return $this->hasMany(EmergencyAssignment::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * Shifts that overlap a given period.
     *
     * @param  Builder<OnCallShift>  $query
     * @return Builder<OnCallShift>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
