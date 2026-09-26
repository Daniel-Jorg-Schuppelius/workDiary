<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Plugins\Lexoffice;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategoriezeile eines Lexoffice-Einkaufsbelegs (MVP-905): Nettobetrag je
 * Buchungskategorie aus `voucherItems`. Getrennt von den Verkaufspositionen
 * (`lexoffice_voucher_lines`), deren Nutzer Erlösbelege erwarten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $voucher_id
 * @property int $position
 * @property string|null $category_external_id
 * @property Money $net_amount
 * @property CurrencyCode $currency
 * @property string|null $tax_rate
 * @property-read LexofficeVoucher $voucher
 */
class LexofficeVoucherCategory extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'voucher_id', 'position', 'category_external_id', 'net_amount', 'currency', 'tax_rate'];

    protected $casts = [
        'position' => 'integer',
        'currency' => CurrencyCode::class,
        'net_amount' => MoneyCast::class . ':currency,2',
        'tax_rate' => 'decimal:2',
    ];

    /** @return BelongsTo<LexofficeVoucher, $this> */
    public function voucher(): BelongsTo {
        return $this->belongsTo(LexofficeVoucher::class, 'voucher_id');
    }
}
