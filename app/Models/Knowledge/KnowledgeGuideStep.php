<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGuideStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Attachments\Attachment;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Schritt einer Bildanleitung (Feature 191, MVP-1668): Text, Bildschirmfoto
 * (Anhang der Seite) und Markierungen (Pfeil, Nummer, Rahmen, Text) als
 * Koordinaten relativ zum Bild (0–1). `redacted_at`: das Bild wurde
 * geschwärzt, das ungeschwärzte Original ist verworfen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_page_id
 * @property int $position
 * @property string|null $body
 * @property int|null $attachment_id
 * @property list<array{kind: string, x: float, y: float, x2?: float, y2?: float, w?: float, h?: float, label?: string}>|null $markers
 * @property Carbon|null $redacted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Attachment|null $image
 */
class KnowledgeGuideStep extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const int MAX_MARKERS = 30;

    public const int MAX_STEPS = 60;

    protected $fillable = [
        'organization_id',
        'knowledge_page_id',
        'position',
        'body',
        'attachment_id',
        'markers',
        'redacted_at',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'markers' => 'array',
        'redacted_at' => 'datetime',
    ];

    /** @return BelongsTo<KnowledgePage, $this> */
    public function page(): BelongsTo {
        return $this->belongsTo(KnowledgePage::class, 'knowledge_page_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function image(): BelongsTo {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }
}
