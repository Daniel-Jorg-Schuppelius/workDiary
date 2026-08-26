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

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, Supplier};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Beleg aus einem Buchhaltungssystem (Feature 122, MVP-611).
 *
 * Spiegel, kein Original: Was hier steht, gehört dem Fremdsystem. workDiary
 * liest es, um den Belegfluss vollständig zu zeigen — und schreibt es nie
 * zurück.
 *
 * @property Carbon|null $voucher_date
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_date
 * @property array<string, mixed>|null $payload
 */
class AccountingVoucher extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Finance\AccountingVoucherFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'plugin_id', 'external_id', 'contact_external_id',
        'customer_id', 'supplier_id', 'voucher_type', 'voucher_status',
        'voucher_state', 'direction', 'document_kind', 'is_cancellation',
        'cancels_external_id',
        'voucher_number', 'voucher_date', 'due_date', 'paid_date',
        'total_amount', 'net_amount', 'open_amount', 'currency',
        'archived', 'payload', 'synced_at', 'source_changed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'voucher_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'total_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'open_amount' => 'decimal:2',
        'archived' => 'boolean',
        'is_cancellation' => 'boolean',
        'source_changed_at' => 'datetime',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
