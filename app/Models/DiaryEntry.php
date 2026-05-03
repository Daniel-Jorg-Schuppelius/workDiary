<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiaryEntry extends Model {
    use HasFactory;
    use HasTags;
    use HasAttachments;
    use Auditable;

    protected $fillable = [
        'legacy_id',
        'user_id',
        'project_id',
        'on_call_shift_id',
        'emergency_assignment_id',
        'content',
        'response',
        'status',
        'start_at',
        'end_at',
        'is_archived',
        'archived_at',
    ];

    protected function casts(): array {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'archived_at' => 'datetime',
            'status' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    public function shift(): BelongsTo {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    public function emergency(): BelongsTo {
        return $this->belongsTo(EmergencyAssignment::class, 'emergency_assignment_id');
    }

    public function comments(): HasMany {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function statusLabel(): string {
        return match ($this->status) {
            -1 => __('Erledigt'),
            1 => __('Bestätigt'),
            2 => __('Offen'),
            3 => __('Problem'),
            default => __('Unbekannt'),
        };
    }

    public function statusTone(): string {
        return match ($this->status) {
            -1 => 'done',
            1 => 'progress',
            2 => 'open',
            3 => 'alert',
            default => 'neutral',
        };
    }
}
