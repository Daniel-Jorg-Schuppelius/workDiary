<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingOpenItemSettlement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Finance\SettlementKind;
use App\Models\Concerns\{AppendOnly, BelongsToOrganization, HasSqid};
use App\Models\Finance\PaymentAllocation;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausgleich an einem offenen Posten (Feature 125, MVP-674).
 *
 * Append-only: Eine Zahlung, die zurückkommt, wird nicht gelöscht — sie
 * bekommt eine Gegenbewegung. Nur so bleibt später erkennbar, dass Geld
 * geflossen UND zurückgegangen ist, statt dass beides nie passiert wäre.
 */
class AccountingOpenItemSettlement extends Model {
    use AppendOnly;
    use BelongsToOrganization;
    use HasSqid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'accounting_open_item_id',
        'accounting_entry_id',
        'kind',
        'amount',
        'currency',
        'booked_on',
        'payment_allocation_id',
        'reverses_settlement_id',
        'note',
        'created_by',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => SettlementKind::class,
        'amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
        'booked_on' => 'date',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingOpenItem, $this> */
    public function openItem(): BelongsTo {
        return $this->belongsTo(AccountingOpenItem::class, 'accounting_open_item_id');
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function entry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function paymentAllocation(): BelongsTo {
        return $this->belongsTo(PaymentAllocation::class, 'payment_allocation_id');
    }
}
