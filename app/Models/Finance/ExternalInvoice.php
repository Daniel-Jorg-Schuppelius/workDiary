<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\SupportingDocumentCarrier;
use App\Models\Customer\Customer;
use App\Models\Invoicing\InvoiceSupportingDocument;
use App\Models\Platform\{Organization, User};
use Database\Factories\Finance\ExternalInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Carbon;

/**
 * Extern ausgestellte Rechnung (Feature 196, Nachtrag 07.11.2026): das führende
 * Fakturasystem vergibt Nummer und Inhalt, WorkDiary hält den abgerufenen Stand,
 * das unveränderte Original mit Hash, die Zuordnung der eingefrorenen
 * Übergabepositionen und die Nachweise. Keine lokale Rechnung, keine Nummer.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $customer_id
 * @property string $plugin_id
 * @property string $external_id
 * @property string|null $external_number
 * @property string|null $remote_status_code
 * @property ExternalInvoiceStatus $status
 * @property string $kind invoice|credit_note
 * @property Carbon|null $issued_on
 * @property string $currency
 * @property string|null $net_amount
 * @property string|null $gross_amount
 * @property list<array<string, mixed>>|null $lines
 * @property string|null $lines_hash
 * @property ExternalInvoiceFormat|null $original_format
 * @property string|null $original_path
 * @property string|null $original_mime
 * @property string|null $original_sha256
 * @property Carbon|null $original_fetched_at
 * @property ProviderSendState $provider_send_state
 * @property Carbon|null $provider_sent_at
 * @property string|null $reconciled_hash
 * @property Carbon|null $reconciled_at
 * @property int|null $reconciled_by
 * @property string|null $note
 * @property Carbon|null $synced_at
 * @property string|null $sync_error
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Customer|null $customer
 * @property-read Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExternalInvoicePosition> $positionLinks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingTransferPosition> $positions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExternalInvoicePackage> $packages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceSupportingDocument> $supportingDocuments
 */
class ExternalInvoice extends Model implements SupportingDocumentCarrier {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ExternalInvoiceFactory> */
    use HasFactory;

    use HasSqid;

    public const string KIND_INVOICE = 'invoice';

    public const string KIND_CREDIT_NOTE = 'credit_note';

    protected $fillable = [
        'organization_id', 'customer_id', 'plugin_id', 'external_id', 'external_number', 'remote_status_code',
        'status', 'kind', 'issued_on', 'currency', 'net_amount', 'gross_amount', 'lines', 'lines_hash',
        'original_format', 'original_path', 'original_mime', 'original_sha256', 'original_fetched_at',
        'provider_send_state', 'provider_sent_at', 'reconciled_hash', 'reconciled_at', 'reconciled_by',
        'note', 'synced_at', 'sync_error', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => ExternalInvoiceStatus::class,
        'original_format' => ExternalInvoiceFormat::class,
        'provider_send_state' => ProviderSendState::class,
        'issued_on' => 'date',
        'net_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'lines' => 'array',
        'original_fetched_at' => 'datetime',
        'provider_sent_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<ExternalInvoicePosition, $this> */
    public function positionLinks(): HasMany {
        return $this->hasMany(ExternalInvoicePosition::class);
    }

    /** @return BelongsToMany<BillingTransferPosition, $this> */
    public function positions(): BelongsToMany {
        return $this->belongsToMany(BillingTransferPosition::class, 'external_invoice_positions')
            ->orderBy('billing_transfer_positions.billing_transfer_id')
            ->orderBy('billing_transfer_positions.position');
    }

    /** @return HasMany<ExternalInvoicePackage, $this> */
    public function packages(): HasMany {
        return $this->hasMany(ExternalInvoicePackage::class)->orderBy('id');
    }

    /** @return HasMany<InvoiceSupportingDocument, $this> */
    public function supportingDocuments(): HasMany {
        return $this->hasMany(InvoiceSupportingDocument::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function reconciler(): BelongsTo {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isCreditNote(): bool {
        return $this->kind === self::KIND_CREDIT_NOTE;
    }

    public function displayNumber(): string {
        return $this->external_number !== null && $this->external_number !== ''
            ? $this->external_number
            : (string) __('finance.external_invoice.no_number');
    }

    public function supportingReferenceNumber(): string {
        return $this->external_number !== null && $this->external_number !== '' ? $this->external_number : 'EXT-' . $this->getKey();
    }

    public function supportingDocumentsLockedReason(): ?string {
        return $this->status === ExternalInvoiceStatus::Voided ? (string) __('finance.external_invoice.error.voided') : null;
    }

    public function carrierOrganizationId(): int {
        return (int) $this->organization_id;
    }

    public function carrierCustomer(): ?Customer {
        return $this->customer;
    }

    public function carrierOrganization(): ?Organization {
        return $this->organization;
    }

    protected static function newFactory(): ExternalInvoiceFactory {
        return ExternalInvoiceFactory::new();
    }
}
