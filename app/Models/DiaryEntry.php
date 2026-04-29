<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryEntry extends Model {
    protected $fillable = [
        'legacy_id',
        'user_id',
        'content',
        'response',
        'status',
        'start_at',
        'end_at',
    ];

    protected function casts(): array {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string {
        return match ($this->status) {
            -1 => 'Erledigt',
            1 => 'Bestätigt',
            2 => 'Offen',
            3 => 'Problem',
            default => 'Unbekannt',
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
