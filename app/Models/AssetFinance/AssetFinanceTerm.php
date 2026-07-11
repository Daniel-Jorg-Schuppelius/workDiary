<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceTerm.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Enums\AssetFinance\AssetFinanceTermKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Strukturierte Vertragskondition (MVP-272): Servicepakete, Versicherung,
 * Gebühren, Indexierung u. a. — vertrauliche Finanzdaten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property AssetFinanceTermKind $kind
 * @property string $label
 * @property numeric-string|null $amount
 */
class AssetFinanceTerm extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'kind', 'label',
        'amount', 'unit', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AssetFinanceTermKind::class,
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }
}
