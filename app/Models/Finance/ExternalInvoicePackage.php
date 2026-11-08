<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoicePackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Finance\{ExternalInvoiceFormat, ExternalPackageKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document\DocumentDispatch;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Versandfassung einer extern ausgestellten Rechnung (Feature 196): die Dateien,
 * die genau so das Haus verlassen, mit Original- und Versandhash, den
 * Nachweisversionen und dem Prüfbericht. Unveränderlich; ein Zustellversuch ist
 * ein {@see DocumentDispatch}, eine Wiederholung sendet dieselben Bytes.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $external_invoice_id
 * @property ExternalPackageKind $kind
 * @property ExternalInvoiceFormat $format
 * @property string $channel
 * @property string $recipient
 * @property string $storage_path
 * @property list<array{name: string, path: string, mime: string, size: int, sha256: string, role: string}> $files
 * @property string $sha256
 * @property string|null $original_sha256
 * @property list<array<string, mixed>>|null $evidence
 * @property array<string, mixed>|null $report
 * @property int|null $created_by
 * @property-read ExternalInvoice $externalInvoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocumentDispatch> $dispatches
 */
class ExternalInvoicePackage extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Belegart im Versandprotokoll (document_dispatches.document_kind). */
    public const string DOCUMENT_KIND = 'external_invoice';

    /** Zustellausgang offen: der Versuch kann angekommen sein, eine Wiederholung wartet auf Klärung. */
    public const string OUTCOME_UNCLEAR = 'unclear';

    protected $fillable = [
        'organization_id', 'external_invoice_id', 'kind', 'format', 'channel', 'recipient', 'storage_path', 'files',
        'sha256', 'original_sha256', 'evidence', 'report', 'created_by',
    ];

    protected $casts = [
        'kind' => ExternalPackageKind::class,
        'format' => ExternalInvoiceFormat::class,
        'files' => 'array',
        'evidence' => 'array',
        'report' => 'array',
    ];

    protected static function booted(): void {
        static::updating(static function (): void {
            throw new \RuntimeException('Versandfassungen sind unveränderlich.');
        });
    }

    /** @return BelongsTo<ExternalInvoice, $this> */
    public function externalInvoice(): BelongsTo {
        return $this->belongsTo(ExternalInvoice::class);
    }

    /** @return HasMany<DocumentDispatch, $this> */
    public function dispatches(): HasMany {
        return $this->hasMany(DocumentDispatch::class, 'document_id')
            ->where('document_kind', self::DOCUMENT_KIND)
            ->orderByDesc('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function latestDispatch(): ?DocumentDispatch {
        return $this->dispatches()->first();
    }

    public function isDelivered(): bool {
        return $this->dispatches()->where('status', DocumentDispatchStatus::Sent->value)->exists();
    }
}
