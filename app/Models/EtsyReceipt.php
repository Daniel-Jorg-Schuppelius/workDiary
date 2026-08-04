<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyReceipt.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spiegelzeile einer Etsy-Bestellung (Feature 101, MVP-495). `receipt_id` =
 * stabiler Etsy-Schlüssel (Upsert-Anker für Sweep UND Webhook — beide Wege
 * laufen idempotent über dieselbe Zeile). Käufer-Daten datensparsam (Name,
 * E-Mail, Versandadresse — keine Nachrichten-Freitexte), Positionen als
 * reduzierte Transactions (SKU/listing_id/Menge/Preis). Kundenzuordnung
 * ausschließlich Inbox-First — nie blind. `shipped_pushed_at` stempelt den
 * Versand-Rückkanal (MVP-497, Duplikatschutz).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $receipt_id
 * @property string|null $status
 * @property bool $was_paid
 * @property bool $was_shipped
 * @property CurrencyCode|null $currency
 * @property \CommonToolkit\ValueObjects\Money|null $total_gross
 * @property \CommonToolkit\ValueObjects\Money|null $total_shipping
 * @property \CommonToolkit\ValueObjects\Money|null $total_tax
 * @property \CommonToolkit\ValueObjects\Money|null $discount
 * @property string|null $buyer_external_id
 * @property array<string, mixed>|null $buyer
 * @property array<int, mixed>|null $items
 * @property array<string, mixed>|null $raw
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property \Illuminate\Support\Carbon|null $etsy_modified_at
 * @property int|null $customer_id
 * @property string $inbox_status
 * @property \Illuminate\Support\Carbon|null $shipped_pushed_at
 */
class EtsyReceipt extends Model {
    use BelongsToOrganization;

    public const INBOX_OPEN = 'open';

    public const INBOX_LINKED = 'linked';

    protected $fillable = [
        'organization_id',
        'receipt_id',
        'status',
        'was_paid',
        'was_shipped',
        'currency',
        'total_gross',
        'total_shipping',
        'total_tax',
        'discount',
        'buyer_external_id',
        'buyer',
        'items',
        'raw',
        'ordered_at',
        'etsy_modified_at',
        'customer_id',
        'inbox_status',
        'shipped_pushed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'receipt_id' => 'integer',
        'was_paid' => 'boolean',
        'was_shipped' => 'boolean',
        'currency' => CurrencyCode::class,
        'total_gross' => MoneyCast::class . ':currency,2',
        'total_shipping' => MoneyCast::class . ':currency,2',
        'total_tax' => MoneyCast::class . ':currency,2',
        'discount' => MoneyCast::class . ':currency,2',
        'buyer' => 'array',
        'items' => 'array',
        'raw' => 'array',
        'ordered_at' => 'datetime',
        'etsy_modified_at' => 'datetime',
        'shipped_pushed_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }
}
