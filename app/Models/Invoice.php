<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Invoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_id
 * @property int|null $project_id
 * @property string $number
 * @property string $status
 * @property Carbon|null $issued_on
 * @property Carbon|null $due_on
 * @property Carbon|null $paid_on
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Customer $customer
 * @property-read Project|null $project
 */
class Invoice extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_CANCELLED];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'project_id',
        'number',
        'status',
        'issued_on',
        'due_on',
        'paid_on',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'paid_on' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function recalculate(): void {
        $sub = 0.0;
        foreach ($this->items as $item) {
            $sub += (float) $item->amount;
        }
        $tax = round($sub * ((float) $this->tax_rate) / 100, 2);
        $this->subtotal = (string) round($sub, 2);
        $this->tax_amount = (string) $tax;
        $this->total = (string) round($sub + $tax, 2);
    }
}
