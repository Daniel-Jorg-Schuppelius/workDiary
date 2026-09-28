<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRuleTier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stufe einer Provisionsstaffel (MVP-988): ab dem Umsatz `threshold_amount` im
 * Staffelzeitraum gilt `rate_percent`. Währung ist die der Regel.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $commission_rule_id
 * @property Money $threshold_amount
 * @property Percentage $rate_percent
 */
class CommissionRuleTier extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'commission_rule_id', 'threshold_amount', 'rate_percent'];

    /** @var array<string, string> */
    protected $casts = [
        'threshold_amount' => MoneyCast::class . ':rule.currency,2',
        'rate_percent' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<CommissionRule, $this> */
    public function rule(): BelongsTo {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
