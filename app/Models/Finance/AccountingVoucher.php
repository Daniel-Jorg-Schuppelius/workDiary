<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Beleg aus einem Buchhaltungssystem (Feature 122, MVP-611).
 *
 * Spiegel, kein Original: Was hier steht, gehört dem Fremdsystem. workDiary
 * liest es, um den Belegfluss vollständig zu zeigen — und schreibt es nie
 * zurück.
 *
 * @property AccountingVoucherState|null $voucher_state
 * @property int|null $sales_user_id
 * @property int|null $sales_agent_id
 * @property Carbon|null $voucher_date
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_date
 * @property Carbon|null $source_deleted_at
 * @property Carbon|null $lines_synced_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AccountingVoucherLine> $lines
 * @property string|null $foreign_amount
 * @property string|null $foreign_currency
 * @property array<string, mixed>|null $payload
 */
class AccountingVoucher extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Finance\AccountingVoucherFactory> */
    use HasFactory;
    use HasSqid;

    /** `external_type` der Auslagen-Verknüpfung mit einem gespiegelten Beleg (MVP-1498); `plugin_id` ist das Programm. */
    public const string EXPENSE_LINK = 'accounting_voucher';

    protected $fillable = [
        'organization_id', 'plugin_id', 'external_id', 'contact_external_id',
        'customer_id', 'supplier_id', 'sales_user_id', 'sales_agent_id', 'voucher_type', 'voucher_status',
        'voucher_state', 'direction', 'document_kind', 'is_cancellation',
        'cancels_external_id',
        'voucher_number', 'voucher_date', 'due_date', 'paid_date',
        'total_amount', 'net_amount', 'open_amount', 'currency', 'foreign_amount', 'foreign_currency',
        'archived', 'payload', 'synced_at', 'lines_synced_at', 'source_changed_at', 'source_deleted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'voucher_state' => AccountingVoucherState::class,
        'voucher_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'total_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'open_amount' => 'decimal:2',
        'foreign_amount' => 'decimal:2',
        'archived' => 'boolean',
        'is_cancellation' => 'boolean',
        'source_changed_at' => 'datetime',
        'source_deleted_at' => 'datetime',
        'payload' => 'array',
        'synced_at' => 'datetime',
        'lines_synced_at' => 'datetime',
    ];

    /**
     * Eingehende Einkaufsbelege ohne Entwürfe, Stornos, Stornodokumente und im
     * Fremdsystem gelöschte (MVP-1129, MVP-1497). Gutschriften bleiben drin;
     * Altzeilen vor MVP-731 zählen über `voucher_type` C (sevDesk: Ausgabe) und den sevDesk-Status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePurchases(Builder $query): Builder {
        $kinds = [DocumentKind::Invoice->value, DocumentKind::Expense->value, DocumentKind::Other->value, DocumentKind::CreditNote->value];

        return $query
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->whereNull('accounting_vouchers.source_deleted_at')
            ->where(static fn (Builder $q) => $q
                ->where('accounting_vouchers.direction', DocumentDirection::Incoming->value)
                ->orWhere(static fn (Builder $w) => $w->whereNull('accounting_vouchers.direction')->where('accounting_vouchers.voucher_type', 'C')))
            ->where(static fn (Builder $q) => $q->whereNull('accounting_vouchers.document_kind')->orWhereIn('accounting_vouchers.document_kind', $kinds))
            ->where(static fn (Builder $q) => $q
                ->whereIn('accounting_vouchers.voucher_state', [AccountingVoucherState::Open->value, AccountingVoucherState::Paid->value])
                ->orWhere(static fn (Builder $w) => $w->whereNull('accounting_vouchers.voucher_state')->where(static fn (Builder $x) => $x
                    ->whereNull('accounting_vouchers.voucher_status')
                    ->orWhere('accounting_vouchers.voucher_status', '!=', '50'))));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    /** @return HasMany<AccountingVoucherLine, $this> Positionen (Feature 185, MVP-1424) */
    public function lines(): HasMany {
        return $this->hasMany(AccountingVoucherLine::class, 'voucher_id')->orderBy('position');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
