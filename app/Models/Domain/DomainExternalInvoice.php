<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainExternalInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Customer, Document};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Capability-gegatete Rechnungsprojektion (Feature 083, MVP-393). Wird NUR
 * befüllt, wenn ein realer Providervertrag Rechnungsliste/-PDF eindeutig
 * belegt. Ohne Nachweis bleibt die Tabelle leer und die UI erklärt die
 * API-Grenze (Blocked-State). Keine synthetische Rechnung aus Accounting.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_invoice_id
 * @property int|null $reseller_account_id
 * @property int|null $customer_id
 * @property Carbon|null $invoice_date
 * @property string|null $status
 * @property float|string|null $net_amount
 * @property float|string|null $tax_amount
 * @property float|string|null $gross_amount
 * @property CurrencyCode|null $currency
 * @property int|null $document_id
 * @property string|null $origin
 * @property string|null $content_hash
 * @property Carbon|null $fetched_at
 * @property array<string, mixed>|null $metadata
 */
class DomainExternalInvoice extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_invoice_id',
        'reseller_account_id',
        'customer_id',
        'invoice_date',
        'status',
        'net_amount',
        'tax_amount',
        'gross_amount',
        'currency',
        'document_id',
        'origin',
        'content_hash',
        'fetched_at',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'invoice_date' => 'date',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'fetched_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
