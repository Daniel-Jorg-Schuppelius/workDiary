<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\MoneyCast;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\{AuditsChanges, HasDocumentLines};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\Billing\Dto\DocumentTotalsContext;
use App\Services\Invoicing\TaxResolver;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Auftragsbestätigung (Phase 145, MVP-1162): eigener Beleg mit Nummernkreis,
 * meist aus den angenommenen Positionen eines Angebots. Ab der Bestätigung
 * unveränderlich; die Nummer vergibt workDiary oder das Buchhaltungsprogramm.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $project_id
 * @property int|null $quote_id
 * @property string $number
 * @property OrderConfirmationStatus $status
 * @property Carbon|null $confirmed_on
 * @property string|null $note
 * @property CurrencyCode $currency
 * @property Money|null $subtotal
 * @property Money|null $tax_amount
 * @property Money|null $total
 * @property int|null $created_by
 * @property-read Customer|null $customer
 * @property-read Quote|null $quote
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrderConfirmationItem> $items
 */
class OrderConfirmation extends Model implements AuditsChanges, HasDocumentLines {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Sales\OrderConfirmationFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'customer_id', 'project_id', 'quote_id', 'number', 'status',
        'confirmed_on', 'note', 'currency', 'subtotal', 'tax_amount', 'total', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => OrderConfirmationStatus::class,
        'confirmed_on' => 'date',
        'currency' => CurrencyCode::class,
        'subtotal' => MoneyCast::class . ':currency,2',
        'tax_amount' => MoneyCast::class . ':currency,2',
        'total' => MoneyCast::class . ':currency,2',
    ];

    /** @return HasMany<OrderConfirmationItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(OrderConfirmationItem::class)->orderBy('position');
    }

    /** @return HasMany<OrderConfirmationItem, $this> */
    public function lines(): HasMany {
        return $this->items();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentCurrency(): CurrencyCode {
        return $this->currency ?? CurrencyCode::Euro;
    }

    public function documentTotals(): array {
        $lines = $this->items->filter(fn (OrderConfirmationItem $item): bool => $item->lineKind()->isPriced())->values();
        $fallback = $lines->contains(fn (OrderConfirmationItem $item): bool => $item->taxRate() === null)
            ? Percentage::of(NumberHelper::toUSFormat($this->defaultTaxRate(), 2))
            : null;

        return app(DocumentTotalsCalculator::class)->totals($lines, new DocumentTotalsContext(currency: $this->documentCurrency(), fallbackTaxRate: $fallback));
    }

    public function recalculate(): void {
        $totals = $this->documentTotals();
        $this->subtotal = $totals['subtotal'];
        $this->tax_amount = $totals['tax_amount'];
        $this->total = $totals['total'];
    }

    private function defaultTaxRate(): float {
        $customer = $this->customer;
        $organization = $this->organization;

        return $customer !== null && $organization !== null
            ? (float) app(TaxResolver::class)->resolve($organization, $customer)['rate']
            : 19.0;
    }
}
