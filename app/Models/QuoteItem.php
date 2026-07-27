<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Angebotsposition (Feature 066, MVP-170) — optional = Eventualposition,
 * accepted = Teilannahme-Entscheidung je Position.
 *
 * @property int $id
 * @property int $quote_id
 * @property bool $optional
 * @property bool|null $accepted
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $discount_percent
 * @property \CommonToolkit\ValueObjects\Money|null $discount_amount
 */
class QuoteItem extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'quote_id', 'position', 'description',
        'quantity', 'unit', 'unit_price', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_category', 'optional', 'accepted',
    ];

    /** Zeilennetto inkl. Positionsrabatt (MVP-416) — Quelle für Quote::recalculate(). */
    public function netAmount(): \CommonToolkit\ValueObjects\Money {
        return \App\Services\Invoicing\InvoiceTotalsCalculator::lineNet(
            (float) $this->quantity,
            $this->unit_price,
            $this->discount_percent,
            $this->discount_amount,
            \CommonToolkit\Enums\CurrencyCode::Euro,
        );
    }

    /** @var array<string, string> */
    protected $casts = [
        'optional' => 'boolean',
        'accepted' => 'boolean',
        // Angebote rechnen in Euro (s. Quote::recalculate()).
        'unit_price' => MoneyCast::class,
        'discount_percent' => PercentageCast::class . ':2',
        'discount_amount' => MoneyCast::class,
        'tax_rate' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }
}
