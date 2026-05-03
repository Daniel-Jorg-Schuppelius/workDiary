<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Database\Factories\EmergencyAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyAssignment extends Model {
    /** @use HasFactory<EmergencyAssignmentFactory> */
    use HasFactory;
    use HasTags;
    use HasAttachments;
    use Auditable;

    protected $fillable = [
        'legacy_id',
        'user_id',
        'on_call_shift_id',
        'start_at',
        'end_at',
        'reason',
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

    public function shift(): BelongsTo {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
