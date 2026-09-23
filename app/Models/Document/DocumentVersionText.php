<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentVersionText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Document\DocumentTextFailure;
use Database\Factories\DocumentVersionTextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ausgelesener Text einer Dokumentversion (MVP-819) — abgeleitetes Material
 * für den Tätigkeitsindex, jederzeit neu berechenbar.
 *
 * Bewusst KEIN BelongsToOrganization: Kind der Version, die ihrerseits am
 * Dokument hängt; die Mandantengrenze läuft transitiv über
 * `documents.organization_id` (wie bei {@see DocumentVersion}).
 *
 * @property int $id
 * @property int $document_version_id
 * @property string|null $text
 * @property Carbon|null $extracted_at
 * @property DocumentTextFailure|null $failure_reason
 */
class DocumentVersionText extends Model {
    /** @use HasFactory<DocumentVersionTextFactory> */
    use HasFactory;

    protected $fillable = [
        'document_version_id',
        'text',
        'extracted_at',
        'failure_reason',
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
        'failure_reason' => DocumentTextFailure::class,
    ];

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
