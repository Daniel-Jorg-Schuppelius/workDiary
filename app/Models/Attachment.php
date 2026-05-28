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

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
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
class Attachment extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    use HasSqid;

    /** Spezialrollen-Diskriminator (siehe meta_type-Spalte). */
    public const META_LOGO = 'logo';

    public const META_LOGO_DARK = 'logo_dark';

    public const META_AVATAR = 'avatar';

    protected $fillable = [
        'organization_id',
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

    /** @var array<string, string> */
    protected $casts = [
        'size' => 'integer',
    ];

    protected static function booted(): void {
        // Letzte Verteidigungslinie für organization_id: weder ein
        // gesetzter currentOrganization-Kontext (Trait-Hook) noch ein
        // user_id-Fallback haben gegriffen — z. B. in Tests, die das
        // Attachment per Factory ohne Auth/Org-Kontext anlegen. Wir
        // leiten dann aus dem polymorphen Parent ab, damit Anhänge
        // niemals als „Waisen" über den Org-Scope-Filter fallen.
        static::creating(function (Attachment $attachment): void {
            if (! empty($attachment->organization_id)) {
                return;
            }
            if (empty($attachment->attachable_type) || empty($attachment->attachable_id)) {
                return;
            }
            $class = $attachment->attachable_type;
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                return;
            }
            $parent = $class::query()->withoutGlobalScopes()->find($attachment->attachable_id);
            if ($parent === null) {
                return;
            }

            if ($class === \App\Models\Organization::class) {
                $attachment->organization_id = $parent->getKey();

                return;
            }

            $parentOrg = $parent->getAttribute('organization_id');
            if (! empty($parentOrg)) {
                $attachment->organization_id = (int) $parentOrg;
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
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
