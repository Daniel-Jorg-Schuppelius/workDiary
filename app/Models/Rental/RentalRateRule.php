<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalRateRuleKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mietpreisregel einer Preisliste (MVP-950): Auf- oder Abschlag in Prozent
 * auf Tages- und Stundensätze für ein Saisonfenster, bestimmte Wochentage oder
 * ab einer Auslastung der Gerätegruppe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_rate_card_id
 * @property RentalRateRuleKind $kind
 * @property string $label
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property list<int>|null $weekdays
 * @property int|null $utilization_min_percent
 * @property numeric-string $adjust_percent
 */
class RentalRateRule extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'rental_rate_card_id', 'kind', 'label', 'valid_from', 'valid_until', 'weekdays', 'utilization_min_percent', 'adjust_percent'];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RentalRateRuleKind::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'weekdays' => 'array',
        'utilization_min_percent' => 'integer',
        'adjust_percent' => 'decimal:2',
    ];

    /** @return BelongsTo<RentalRateCard, $this> */
    public function rateCard(): BelongsTo {
        return $this->belongsTo(RentalRateCard::class, 'rental_rate_card_id');
    }

    /** @return array{kind: string, label: string, valid_from: ?string, valid_until: ?string, weekdays: list<int>, utilization_min_percent: ?int, adjust_percent: string} */
    public function toSnapshot(): array {
        return [
            'kind' => $this->kind->value,
            'label' => $this->label,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'weekdays' => array_map('intval', (array) $this->weekdays),
            'utilization_min_percent' => $this->utilization_min_percent,
            'adjust_percent' => (string) $this->adjust_percent,
        ];
    }
}
