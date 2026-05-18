<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Attachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int|null $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property string|null $meta_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attachment extends Model
{
    use Auditable;

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /** Spezialrollen-Diskriminator (siehe meta_type-Spalte). */
    public const META_LOGO = 'logo';

    public const META_LOGO_DARK = 'logo_dark';

    public const META_AVATAR = 'avatar';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'meta_type',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
