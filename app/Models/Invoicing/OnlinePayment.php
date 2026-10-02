<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePayment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Casts\MoneyCast;
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Bezahlseite beim Zahlungsanbieter für eine Rechnung (MVP-1067). Der
 * Stand kommt nur aus der Nachfrage beim Anbieter; bezahlte Beträge abzüglich
 * Erstattungen decken die Rechnung ({@see settledSumFor()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property OnlinePaymentStatus $status
 * @property Money $gross_amount
 * @property Money|null $fee_amount
 * @property Money $refunded_amount
 * @property string $currency
 * @property string|null $method
 * @property string|null $checkout_url
 * @property Carbon|null $checkout_expires_at
 * @property Carbon|null $paid_at
 */
class OnlinePayment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'invoice_id', 'provider', 'provider_reference', 'status',
        'gross_amount', 'fee_amount', 'refunded_amount', 'currency', 'method',
        'checkout_url', 'checkout_expires_at', 'paid_at',
    ];

    protected $hidden = ['checkout_url'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => OnlinePaymentStatus::class,
        'gross_amount' => MoneyCast::class . ':currency,2',
        'fee_amount' => MoneyCast::class . ':currency,2',
        'refunded_amount' => MoneyCast::class . ':currency,2',
        'checkout_expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** Online gezahlt abzüglich Erstattungen — Teil der Deckung jeder Rechnung. */
    public static function settledSumFor(Invoice $invoice): float {
        return round((float) static::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [OnlinePaymentStatus::Paid->value, OnlinePaymentStatus::Refunded->value])
            ->selectRaw('COALESCE(SUM(gross_amount - refunded_amount), 0) AS settled')
            ->value('settled'), 2);
    }
}
