<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadAsset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadAssetStatus, LetterheadPageRole};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Firmenbogen-Asset (MVP-296): Original als administrativer Nachweis plus
 * normalisierte, nicht interaktive Rasterrepräsentation fürs Rendern.
 * Nur `ready`-Assets dürfen in Profilversionen verwendet werden.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property LetterheadPageRole $page_role
 * @property string $source_type
 * @property string $disk
 * @property string $original_path
 * @property string|null $normalized_path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property numeric-string|null $width_mm
 * @property numeric-string|null $height_mm
 * @property string $original_sha256
 * @property string|null $normalized_sha256
 * @property LetterheadAssetStatus $status
 * @property array<int, string>|null $review_notes
 * @property int|null $uploaded_by
 */
class LetterheadAsset extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'page_role',
        'page_format',
        'source_type',
        'disk',
        'original_path',
        'normalized_path',
        'original_name',
        'mime',
        'size',
        'width_mm',
        'height_mm',
        'original_sha256',
        'normalized_sha256',
        'status',
        'review_notes',
        'uploaded_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'page_role' => LetterheadPageRole::class,
        'page_format' => \App\Enums\DocumentDesign\PageFormat::class,
        'status' => LetterheadAssetStatus::class,
        'review_notes' => 'array',
        'size' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool {
        return $this->status === LetterheadAssetStatus::Ready && $this->normalized_path !== null;
    }

    /**
     * Trägt dieser Bogen eine Vektorquelle (PDF-Original)?
     */
    public function hasVectorSource(): bool {
        return $this->isReady() && $this->source_type === 'pdf';
    }

    /**
     * Original-PDF für den Vektor-Overlay (Audit 2026-08, W5.5).
     *
     * **Zur früheren Regel „das Original wandert nie in Ausgabedokumente":**
     * Sie zielte auf das EINBETTEN der Datei — mit Formularen, JavaScript,
     * externen Verweisen und Anhängen. Der Overlay bettet nichts ein: FPDIs
     * `importPage()` übernimmt ausschließlich den Zeichenstrom der Seite samt
     * der von ihm referenzierten Ressourcen (Schriften, Bilder). `/Annots`,
     * AcroForm, `/Names /JavaScript`, `/OpenAction` und eingebettete Dateien
     * hängen am Seiten- bzw. Katalogobjekt und werden dabei nicht mitgenommen.
     * Der Import ist damit selbst die Reduktion — nur eben ohne den
     * Qualitätsverlust der Rasterung.
     */
    public function vectorSourceBytes(): ?string {
        if (! $this->hasVectorSource()) {
            return null;
        }

        $disk = Storage::disk($this->disk);

        return $disk->exists($this->original_path) ? $disk->get($this->original_path) : null;
    }

    /**
     * Normalisierte Rasterdatei als Data-URI — dompdf kann keine signierten
     * URLs auflösen, daher wird der Firmenbogen (wie das Branding-Logo)
     * direkt ins HTML eingebettet.
     */
    public function normalizedDataUri(): ?string {
        if ($this->normalized_path === null) {
            return null;
        }
        $disk = Storage::disk($this->disk);
        if (! $disk->exists($this->normalized_path)) {
            return null;
        }
        $raw = $disk->get($this->normalized_path);

        return $raw === null ? null : 'data:image/png;base64,' . base64_encode($raw);
    }
}
