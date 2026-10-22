<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCredit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Casts\MoneyCast;
use App\Enums\Invoicing\SelfBillingStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\HasDocumentLines;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\Billing\Dto\DocumentTotalsContext;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Gutschrift im Gutschriftsverfahren (MVP-1280): Die Organisation stellt als
 * Leistungsempfängerin die Rechnung über die Leistung eines Lieferanten aus
 * (§ 14 Abs. 2 Satz 2 UStG). Voraussetzung ist die Vereinbarung mit dem
 * Lieferanten; der Beleg trägt die Angabe „Gutschrift“ (§ 14 Abs. 4 Nr. 10)
 * und in der E-Rechnung den Typcode 389. Ausgestellte Belege sind
 * unveränderlich — eine Korrektur ist eine neue Gutschrift.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_id
 * @property string|null $number
 * @property SelfBillingStatus $status
 * @property Carbon|null $issued_on
 * @property Carbon|null $service_from
 * @property Carbon|null $service_until
 * @property CurrencyCode $currency
 * @property Money|null $net_amount
 * @property Money|null $tax_amount
 * @property Money|null $gross_amount
 * @property string|null $note
 * @property int|null $incoming_e_invoice_id
 * @property int|null $created_by
 * @property int|null $issuer_user_id
 * @property Carbon|null $issued_at
 * @property int|null $canceller_user_id
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SelfBillingCreditLine> $lines
 */
class SelfBillingCredit extends Model implements HasDocumentLines {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Invoicing\SelfBillingCreditFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'supplier_id', 'number', 'status', 'issued_on', 'service_from', 'service_until', 'currency',
        'net_amount', 'tax_amount', 'gross_amount', 'note', 'incoming_e_invoice_id', 'created_by',
        'issuer_user_id', 'issued_at', 'canceller_user_id', 'cancelled_at', 'cancel_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => SelfBillingStatus::class,
        'issued_on' => 'date',
        'service_from' => 'date',
        'service_until' => 'date',
        'currency' => CurrencyCode::class,
        'net_amount' => MoneyCast::class . ':currency',
        'tax_amount' => MoneyCast::class . ':currency',
        'gross_amount' => MoneyCast::class . ':currency',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'currency' => 'EUR'];

    protected static function booted(): void {
        // Ausgestellt heißt festgeschrieben: nur Storno und Verweis auf den Eingang dürfen sich noch ändern.
        static::updating(static function (self $credit): void {
            if ($credit->getRawOriginal('status') === SelfBillingStatus::Draft->value) {
                return;
            }
            $allowed = ['status', 'canceller_user_id', 'cancelled_at', 'cancel_reason', 'incoming_e_invoice_id', 'updated_at'];
            if (array_diff(array_keys($credit->getDirty()), $allowed) !== []) {
                throw new RuntimeException((string) __('invoicing.self_billing.errors.locked'));
            }
        });
        static::deleting(static function (self $credit): void {
            if ($credit->status !== SelfBillingStatus::Draft) {
                throw new RuntimeException((string) __('invoicing.self_billing.errors.locked'));
            }
        });
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SelfBillingCreditLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(SelfBillingCreditLine::class)->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incoming(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_e_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    public function isDraft(): bool {
        return $this->status === SelfBillingStatus::Draft;
    }

    public function documentCurrency(): CurrencyCode {
        return $this->currency;
    }

    /** @return array<string, mixed> */
    public function documentTotals(): array {
        $this->loadMissing('lines');

        return app(DocumentTotalsCalculator::class)->totals($this->lines, new DocumentTotalsContext(currency: $this->documentCurrency()));
    }
}
