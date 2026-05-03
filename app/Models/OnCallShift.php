<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Database\Factories\OnCallShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnCallShift extends Model {
    /** @use HasFactory<OnCallShiftFactory> */
    use HasFactory;
    use HasTags;
    use HasAttachments;
    use Auditable;

    protected $fillable = [
        'legacy_id',
        'user_id',
        'start_at',
        'end_at',
        'note',
        'is_archived',
    ];

    protected function casts(): array {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function emergencyAssignments(): HasMany {
        return $this->hasMany(EmergencyAssignment::class);
    }

    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * Shifts that overlap a given period.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
