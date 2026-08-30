<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaRendition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Media;

use App\Enums\Media\{MediaRenditionKind, SubtitleSource};
use App\Models\Attachment;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abgeleitete Fassung einer Mediendatei (Feature 150).
 *
 * Das Original bleibt der Anhang; hier liegen die daraus gerechneten
 * Auflösungen, das Vorschaubild und die Untertitelspuren.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $attachment_id
 * @property MediaRenditionKind $kind
 * @property string|null $variant
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $locale
 * @property SubtitleSource $source
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 */
class MediaRendition extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'attachment_id',
        'kind',
        'variant',
        'disk',
        'path',
        'mime',
        'size_bytes',
        'width',
        'height',
        'locale',
        'source',
        'reviewed_at',
        'reviewed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => MediaRenditionKind::class,
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'source' => SubtitleSource::class,
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo {
        return $this->belongsTo(Attachment::class);
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function reviewer(): BelongsTo {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    /**
     * Steht die Durchsicht noch aus?
     *
     * Nur maschinelle Spuren brauchen sie — eine von Hand erstellte Datei
     * ist bereits das Ergebnis menschlicher Arbeit.
     */
    public function awaitsReview(): bool {
        return $this->source->needsReview() && $this->reviewed_at === null;
    }
}
