<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model {
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected function casts(): array {
        return [
            'size' => 'integer',
        ];
    }

    public function attachable(): MorphTo {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isImage(): bool {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string {
        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
