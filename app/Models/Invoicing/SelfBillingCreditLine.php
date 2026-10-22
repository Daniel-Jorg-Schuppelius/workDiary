<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCreditLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, IsDocumentLine};
use App\Models\Contracts\DocumentLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position einer Gutschrift im Gutschriftsverfahren (MVP-1280). Das
 * Zeilennetto wird gerechnet, nicht gespeichert; den Stand zum Ausstellen
 * hält der Kopf in seinen Summen fest.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $self_billing_credit_id
 * @property int $position
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 */
class SelfBillingCreditLine extends Model implements DocumentLine {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Invoicing\SelfBillingCreditLineFactory> */
    use HasFactory;
    use HasSqid;
    use IsDocumentLine;

    protected $fillable = [
        'organization_id', 'self_billing_credit_id', 'position', 'description', 'quantity', 'unit', 'unit_price', 'tax_rate',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'unit_price' => MoneyCast::class . ':credit.currency,4',
        'tax_rate' => PercentageCast::class,
    ];

    /** @return array<string, string|null> */
    protected static function lineColumns(): array {
        return [
            'net_amount' => null,
            'discount_percent' => null,
            'discount_amount' => null,
            'currency' => null,
            'labour_share_percent' => null,
            'line_kind' => null,
        ];
    }

    /** @return BelongsTo<SelfBillingCredit, $this> */
    public function credit(): BelongsTo {
        return $this->belongsTo(SelfBillingCredit::class, 'self_billing_credit_id');
    }

    /** @return BelongsTo<SelfBillingCredit, $this> */
    public function lineDocument(): BelongsTo {
        return $this->credit();
    }
}
