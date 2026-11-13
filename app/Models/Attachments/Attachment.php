<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Attachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Attachments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Support\MorphMap;
use Database\Factories\Attachments\AttachmentFactory;
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
 * @property \App\Enums\Media\MediaState|null $media_state
 * @property int|null $media_duration_seconds
 * @property int|null $media_width
 * @property int|null $media_height
 * @property string|null $media_error
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
    public const string META_LOGO = 'logo';

    public const string META_LOGO_DARK = 'logo_dark';

    public const string META_AVATAR = 'avatar';

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
        'customer_visible',
        'media_state',
        'media_duration_seconds',
        'media_width',
        'media_height',
        'media_error',
        'media_processed_at',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<AttachmentConfirmation, $this> */
    public function confirmations(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(AttachmentConfirmation::class);
    }

    /** @var array<string, string> */
    protected $casts = [
        'customer_visible' => 'boolean',
        'size' => 'integer',
        'media_state' => \App\Enums\Media\MediaState::class,
        'media_processed_at' => 'datetime',
    ];

    protected static function booted(): void {
        // Letzte Verteidigungslinie für organization_id (falls Kontext- und user_id-Fallback fehlten):
        // aus dem polymorphen Parent ableiten, damit Anhänge nicht als Waisen aus dem Org-Scope fallen.
        static::creating(function (Attachment $attachment): void {
            if (! empty($attachment->organization_id)) {
                return;
            }
            if (empty($attachment->attachable_type) || empty($attachment->attachable_id)) {
                return;
            }
            // In der Typspalte steht der Alias, nicht der Klassenname (MVP-860).
            $class = MorphMap::classFor($attachment->attachable_type);
            if ($class === null || ! is_subclass_of($class, Model::class)) {
                return;
            }
            $parent = $class::query()->withoutGlobalScopes()->find($attachment->attachable_id);
            if ($parent === null) {
                return;
            }

            if ($class === \App\Models\Platform\Organization::class) {
                $attachment->organization_id = $parent->getKey();

                return;
            }

            $parentOrg = $parent->getAttribute('organization_id');
            if (! empty($parentOrg)) {
                $attachment->organization_id = (int) $parentOrg;
            }
        });

        // Mail-Dateien (Feature 204): einmal im Hintergrund lesen — für jeden Weg,
        // der einen Anhang anlegt (Upload, Mail-Eingang, Übernahme). Beim Löschen
        // fallen die extrahierten Teile mit; die Zeile per Fremdschlüssel.
        static::created(function (Attachment $attachment): void {
            if ($attachment->mailFormat() !== null) {
                app(\App\Services\Attachments\Mail\MailAttachmentContents::class)->queue($attachment);
            }
        });
        static::deleting(function (Attachment $attachment): void {
            if ($attachment->mailFormat() !== null) {
                app(\App\Services\Attachments\Mail\MailAttachmentContents::class)->forget($attachment);
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo {
        return $this->morphTo();
    }

    /**
     * Abgeleitete Fassungen (Feature 150): Auflösungen, Vorschaubild,
     * Untertitel. Leer, solange nichts gerechnet wurde.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Media\MediaRendition, $this>
     */
    public function renditions(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(\App\Models\Media\MediaRendition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isImage(): bool {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** Mail-Datei (`.eml`/`.msg`, Feature 204) — dann gibt es die Ansicht „Mail“. */
    public function mailFormat(): ?\App\Enums\Attachments\MailFileFormat {
        return \App\Enums\Attachments\MailFileFormat::detect((string) $this->original_name, $this->mime);
    }

    /**
     * Gelesene Mail-Datei (Feature 204): Suchtext, Zusammenfassung, Teile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<AttachmentText, $this>
     */
    public function extractedText(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(AttachmentText::class);
    }

    public function humanSize(): string {
        // Toolkit-Formatter (Vollaudit 2026-07, N41); >= 1 GB erscheint nun als GB.
        return \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $this->size, 1);
    }
}
