<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentFinancingVariant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Enums\Investments\InvestmentFinancingKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Finanzierungsvariante einer Investitionsoption (MVP-907): Kauf aus
 * Eigenmitteln, Kredit (Annuität) oder Leasing (Rate + Restwert).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_option_id
 * @property InvestmentFinancingKind $kind
 * @property numeric-string $down_payment_amount
 * @property numeric-string|null $interest_rate
 * @property int|null $term_months
 * @property numeric-string|null $rate_amount
 * @property numeric-string $residual_amount
 * @property numeric-string $fee_amount
 * @property string $currency
 * @property string|null $note
 */
class InvestmentFinancingVariant extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'investment_option_id', 'kind', 'down_payment_amount', 'interest_rate', 'term_months',
        'rate_amount', 'residual_amount', 'fee_amount', 'currency', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => InvestmentFinancingKind::class,
        'down_payment_amount' => 'decimal:2',
        'interest_rate' => 'decimal:3',
        'term_months' => 'integer',
        'rate_amount' => 'decimal:2',
        'residual_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<InvestmentOption, $this> */
    public function option(): BelongsTo {
        return $this->belongsTo(InvestmentOption::class, 'investment_option_id');
    }
}
