<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetSpecialDepreciation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sonderabschreibung nach § 7g EStG für ein Geschäftsjahr (MVP-981), Startjahr
 * des Geschäftsjahres als `fiscal_year`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $fixed_asset_id
 * @property int $fiscal_year
 * @property Money $depreciation_amount
 * @property CurrencyCode $currency
 * @property string|null $note
 * @property int|null $created_by
 */
class FixedAssetSpecialDepreciation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'fixed_asset_id', 'fiscal_year', 'depreciation_amount', 'currency', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'fiscal_year' => 'integer',
        'currency' => CurrencyCode::class,
        'depreciation_amount' => MoneyCast::class . ':currency,2',
    ];

    /** @return BelongsTo<FixedAsset, $this> */
    public function fixedAsset(): BelongsTo {
        return $this->belongsTo(FixedAsset::class);
    }
}
