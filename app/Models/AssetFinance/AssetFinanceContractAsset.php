<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceContractAsset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Models\Asset;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asset-Bezug einer Leasingakte — das Asset bleibt im Asset-Modul führend.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property int $asset_id
 */
class AssetFinanceContractAsset extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'asset_id', 'note',
    ];

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }
}
