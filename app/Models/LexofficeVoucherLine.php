<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position einer gespiegelten Lexoffice-Rechnung (Feature 152 / 140 Schnitt 2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $voucher_id
 * @property int $position
 * @property string|null $type
 * @property string|null $external_article_id
 * @property int|null $lexoffice_article_id
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property string|null $unit_name
 * @property Money $unit_net
 * @property Money $total_net
 * @property string|null $tax_rate
 * @property CurrencyCode $currency
 * @property-read LexofficeVoucher $voucher
 * @property-read LexofficeArticle|null $article
 */
class LexofficeVoucherLine extends Model {
    use BelongsToOrganization;

    protected $table = 'lexoffice_voucher_lines';

    protected $fillable = [
        'organization_id',
        'voucher_id',
        'position',
        'type',
        'external_article_id',
        'lexoffice_article_id',
        'name',
        'description',
        'quantity',
        'unit_name',
        'unit_net',
        'total_net',
        'tax_rate',
        'currency',
    ];

    protected $casts = [
        'position' => 'integer',
        'quantity' => 'decimal:3',
        'currency' => CurrencyCode::class,
        'unit_net' => MoneyCast::class . ':currency,4',
        'total_net' => MoneyCast::class . ':currency,2',
        'tax_rate' => 'decimal:2',
    ];

    /** @return BelongsTo<LexofficeVoucher, $this> */
    public function voucher(): BelongsTo {
        return $this->belongsTo(LexofficeVoucher::class, 'voucher_id');
    }

    /** @return BelongsTo<LexofficeArticle, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(LexofficeArticle::class, 'lexoffice_article_id');
    }

    public function text(): string {
        return trim($this->name . ' ' . (string) $this->description);
    }
}
