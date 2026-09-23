<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zustellversuch eines Ausgangsbelegs (Feature 066, MVP-168; generisch seit
 * Feature 128, MVP-692): Kanal, Empfänger, Format und Dateihash je
 * Versand/Download — ein erneuter Versand ist ein WEITERER Zustellversuch,
 * nie ein neuer Beleg. Adressiert über document_kind + document_id
 * (RenderDocumentKind-Werte); Rechnungszeilen tragen zusätzlich den
 * invoice_id-FK (Cascade + bestehende Abfragen). Technischer Status
 * (queued/sent/failed) bleibt vom fachlichen Empfang getrennt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $invoice_id
 * @property string|null $document_kind
 * @property int|null $document_id
 * @property string $channel
 * @property string|null $format
 * @property string $status
 * @property string|null $recipient
 * @property string|null $sha256
 * @property array<string, mixed>|null $meta
 * @property int|null $created_by
 */
class DocumentDispatch extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_DOWNLOAD = 'download';

    public const CHANNEL_PEPPOL = 'peppol';

    public const CHANNEL_STORAGE = 'storage';
    // Zugangsnachweis ausserhalb der App (Einschreiben, Bote, persoenliche
    // Uebergabe, Fax) — Details stehen in `meta` (H23, MVP-728).
    public const CHANNEL_MANUAL = 'manual';

    protected $fillable = [
        'organization_id', 'invoice_id', 'document_kind', 'document_id',
        'channel', 'format', 'status', 'recipient', 'sha256', 'meta', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'meta' => 'array',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Zustellversuche eines konkreten Belegs (z. B. Quote #5 als Angebot).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDocument(Builder $query, RenderDocumentKind $kind, int $documentId): Builder {
        return $query->where('document_kind', $kind->value)->where('document_id', $documentId);
    }
}
