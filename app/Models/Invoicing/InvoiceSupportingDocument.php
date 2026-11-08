<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSupportingDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Invoicing\SupportingDocumentKind;
use App\Models\Attachments\Attachment;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\SupportingDocumentCarrier;
use App\Models\Finance\ExternalInvoice;
use App\Models\Platform\User;
use Database\Factories\Invoicing\InvoiceSupportingDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Rechnungsbegründende Unterlage einer Rechnung (EN 16931 BG-24, Feature 196):
 * Kennung (BT-122), Beschreibung (BT-123), optional ein Link (BT-124) und die
 * eingebettete Datei (BT-125), die als Anhang an dieser Zeile liegt. Träger ist
 * eine lokale Rechnung (`invoice_id`) oder eine extern ausgestellte
 * (`external_invoice_id`), nie beides.
 *
 * Eingefroren mit der Ausstellung bzw. der ersten Versandfassung: danach ändert
 * sich nur noch der Übertragungsvermerk an ein Buchhaltungsprogramm.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $invoice_id
 * @property int|null $external_invoice_id
 * @property int|null $attachment_id
 * @property int $position
 * @property SupportingDocumentKind $kind
 * @property string $reference
 * @property string|null $description
 * @property string|null $external_url
 * @property string|null $filename
 * @property string|null $filename_key
 * @property string|null $mime
 * @property int $size
 * @property string|null $sha256
 * @property bool $is_embedded
 * @property Carbon|null $frozen_at
 * @property Carbon|null $transferred_at
 * @property string|null $transfer_note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Invoice|null $invoice
 * @property-read ExternalInvoice|null $externalInvoice
 * @property-read Attachment|null $attachment
 */
class InvoiceSupportingDocument extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<InvoiceSupportingDocumentFactory> */
    use HasFactory;

    use HasSqid;

    /** Felder, die nach dem Einfrieren noch geschrieben werden dürfen. */
    public const MUTABLE_AFTER_FREEZE = ['transferred_at', 'transfer_note', 'updated_by', 'updated_at'];

    protected $fillable = [
        'organization_id', 'invoice_id', 'external_invoice_id', 'attachment_id', 'position', 'kind', 'reference', 'description',
        'external_url', 'filename', 'filename_key', 'mime', 'size', 'sha256', 'is_embedded', 'frozen_at',
        'transferred_at', 'transfer_note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'kind' => SupportingDocumentKind::class,
        'position' => 'integer',
        'size' => 'integer',
        'is_embedded' => 'boolean',
        'frozen_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(function (self $document): void {
            // Im Modell statt als CHECK: SQLite kann ihn nachträglich nur per Tabellenneubau.
            if (($document->invoice_id === null) === ($document->external_invoice_id === null)) {
                throw new \RuntimeException('Eine Unterlage gehört genau einer Rechnung: einer lokalen oder einer extern ausgestellten.');
            }
            $document->filename_key = $document->filename !== null ? mb_strtolower($document->filename) : null;
        });

        static::updating(function (self $document): void {
            if ($document->getRawOriginal('frozen_at') === null) {
                return;
            }
            $blocked = array_diff(array_keys($document->getDirty()), self::MUTABLE_AFTER_FREEZE);
            if ($blocked !== []) {
                throw new \RuntimeException('Eingefrorene Unterlagen sind unveränderlich (Felder: ' . implode(', ', $blocked) . ').');
            }
        });

        static::deleting(function (self $document): void {
            if ($document->frozen_at !== null && ($document->invoice()->exists() || $document->externalInvoice()->exists())) {
                throw new \RuntimeException('Eingefrorene Unterlagen einer bestehenden Rechnung lassen sich nicht löschen.');
            }
        });
    }

    public function isFrozen(): bool {
        return $this->frozen_at !== null;
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<ExternalInvoice, $this> */
    public function externalInvoice(): BelongsTo {
        return $this->belongsTo(ExternalInvoice::class);
    }

    public function carrier(): ?SupportingDocumentCarrier {
        return $this->invoice_id !== null ? $this->invoice : $this->externalInvoice;
    }

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo {
        return $this->belongsTo(Attachment::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
