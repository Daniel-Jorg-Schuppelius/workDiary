<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unveränderliche Datei-Version eines Dokuments (MVP-031). Append-only:
 * Die Versionshistorie IST die Dokument-Historie — Versionen werden nie
 * bearbeitet, nur über neue Versionen abgelöst.
 *
 * Bewusst KEIN BelongsToOrganization-Trait: Kind-Tabelle von Document,
 * Mandantengrenze wird transitiv über `documents.organization_id`
 * durchgesetzt (Allow-List in TenantTraitCoverageTest, Begründung in
 * docs/security/tenant-audit-2026.md).
 *
 * @property int $id
 * @property int $document_id
 * @property int $version_no
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property int $uploaded_by_user_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class DocumentVersion extends Model {
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'version_no',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by_user_id',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version_no' => 'integer',
        'size' => 'integer',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
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
