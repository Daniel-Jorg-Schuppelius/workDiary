<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityScenarioItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Einzelposten eines Liquiditätsszenarios (MVP-954).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $liquidity_scenario_id
 * @property string $label
 * @property string $direction
 * @property numeric-string $amount
 * @property Carbon $expected_on
 */
class LiquidityScenarioItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'liquidity_scenario_id', 'label', 'direction', 'amount', 'expected_on'];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'expected_on' => 'date',
    ];

    /** @return BelongsTo<LiquidityScenario, $this> */
    public function scenario(): BelongsTo {
        return $this->belongsTo(LiquidityScenario::class, 'liquidity_scenario_id');
    }
}
