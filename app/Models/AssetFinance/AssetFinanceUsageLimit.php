<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceUsageLimit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Enums\AssetFinance\AssetFinanceUsageLimitKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nutzungslimit mit referenziertem Ist-Wert (MVP-275): Kilometer,
 * Betriebsstunden oder Nutzungstage — Überschreitung ist ein Hinweiswert,
 * keine Buchung (D11).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property AssetFinanceUsageLimitKind $kind
 * @property numeric-string $limit_value
 * @property string $period
 * @property numeric-string|null $actual_value
 */
class AssetFinanceUsageLimit extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const PERIODS = ['total', 'yearly'];

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'kind', 'limit_value',
        'period', 'overrun_fee_per_unit', 'actual_value', 'actual_recorded_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AssetFinanceUsageLimitKind::class,
        'limit_value' => 'decimal:2',
        'overrun_fee_per_unit' => 'decimal:4',
        'actual_value' => 'decimal:2',
        'actual_recorded_at' => 'datetime',
    ];

    public function overrun(): float {
        if ($this->actual_value === null) {
            return 0.0;
        }

        return max(0.0, (float) $this->actual_value - (float) $this->limit_value);
    }

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }
}
