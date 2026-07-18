<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spiegelzeile einer Billbee-Multichannel-Bestellung (Feature 093, MVP-433).
 * `billbee_order_id` = Billbee-interne Order-ID (stabiler Schlüssel),
 * `external_order_id` = Marktplatz-ID, `channel` = Plattformherkunft
 * (Amazon/eBay/…). Kundenzuordnung ausschließlich Inbox-First — nie blind.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $billbee_order_id
 * @property string|null $external_order_id
 * @property string|null $order_number
 * @property string|null $channel
 * @property int $state
 * @property CurrencyCode|null $currency
 * @property string $total_gross
 * @property array<string, mixed>|null $buyer
 * @property array<int, mixed>|null $items
 * @property array<string, mixed>|null $raw
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property \Illuminate\Support\Carbon|null $billbee_modified_at
 * @property int|null $customer_id
 * @property string $inbox_status
 */
class BillbeeOrder extends Model {
    use BelongsToOrganization;

    public const INBOX_OPEN = 'open';

    public const INBOX_LINKED = 'linked';

    /** Dokumentierte Billbee-Bestellstatus (Anzeige; unbekannte Werte → #<int>). */
    private const STATE_LABELS = [
        1 => 'bestellt', 2 => 'bestätigt', 3 => 'bezahlt', 4 => 'versandt',
        5 => 'reklamation', 6 => 'gelöscht', 7 => 'abgeschlossen', 8 => 'storniert',
        9 => 'archiviert', 11 => '1. mahnung', 12 => '2. mahnung', 13 => 'gepackt',
        14 => 'angeboten', 15 => 'zahlungserinnerung', 16 => 'im fulfillment',
    ];

    protected $fillable = [
        'organization_id',
        'billbee_order_id',
        'external_order_id',
        'order_number',
        'channel',
        'state',
        'currency',
        'total_gross',
        'buyer_external_id',
        'buyer',
        'items',
        'raw',
        'ordered_at',
        'billbee_modified_at',
        'customer_id',
        'inbox_status',
    ];

    protected $casts = [
        'state' => 'integer',
        'currency' => CurrencyCode::class,
        'total_gross' => 'decimal:2',
        'buyer' => 'array',
        'items' => 'array',
        'raw' => 'array',
        'ordered_at' => 'datetime',
        'billbee_modified_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    public function stateLabel(): string {
        return self::STATE_LABELS[$this->state] ?? ('#' . $this->state);
    }
}
