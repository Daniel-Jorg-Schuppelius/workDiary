<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lokaler Cache eines Lexoffice-Belegs (voucherlist-Eintrag).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property ?string $contact_external_id
 * @property ?int $customer_id
 * @property ?int $supplier_id
 * @property ?string $voucher_type
 * @property ?string $voucher_status
 * @property ?string $voucher_number
 * @property ?Carbon $voucher_date
 * @property ?Carbon $due_date
 * @property ?Carbon $paid_date
 * @property \CommonToolkit\ValueObjects\Money|null $total_amount
 * @property \CommonToolkit\ValueObjects\Money|null $open_amount
 * @property \CommonToolkit\ValueObjects\Money|null $net_amount
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property bool $archived
 * @property ?array<string, mixed> $payload
 * @property ?Carbon $synced_at
 */
class LexofficeVoucher extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'external_id',
        'contact_external_id',
        'customer_id',
        'supplier_id',
        'voucher_type',
        'voucher_status',
        'voucher_number',
        'voucher_date',
        'due_date',
        'paid_date',
        'total_amount',
        'open_amount',
        'net_amount',
        'currency',
        'archived',
        'payload',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'customer_id' => 'integer',
        'supplier_id' => 'integer',
        'voucher_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'total_amount' => MoneyCast::class . ':currency,2',
        'open_amount' => MoneyCast::class . ':currency,2',
        // Nur der Nettobetrag der voucherlist-Belege ist NICHT enthalten — er
        // wird per Detailabruf nachgeladen (siehe LexofficeVoucherNetAmount).
        'net_amount' => MoneyCast::class . ':currency,2',
        'archived' => 'boolean',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('archived', false);
    }
}
