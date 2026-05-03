<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model {
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'changes',
        'ip',
        'user_agent',
    ];

    protected function casts(): array {
        return [
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo {
        return $this->morphTo();
    }

    public function eventLabel(): string {
        return match ($this->event) {
            'created' => __('Angelegt'),
            'updated' => __('Geändert'),
            'deleted' => __('Gelöscht'),
            'archived' => __('Archiviert'),
            'restored' => __('Wiederhergestellt'),
            default => $this->event,
        };
    }
}
