<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeTariff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubFeeTariffKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Beitragstarif (MVP-849): frei benennbar, Einzel- oder Familientarif,
 * optional Altersgrenzen; Beträge und Rhythmus liegen versioniert in den Sätzen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property ClubFeeTariffKind $kind
 * @property string|null $description
 * @property int|null $min_age
 * @property int|null $max_age
 * @property bool $is_active
 * @property int $sort_order
 */
class ClubFeeTariff extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'kind', 'description', 'min_age', 'max_age', 'is_active', 'sort_order'];

    protected $casts = ['kind' => ClubFeeTariffKind::class, 'min_age' => 'integer', 'max_age' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    /** @return HasMany<ClubFeeTariffRate, $this> */
    public function rates(): HasMany {
        return $this->hasMany(ClubFeeTariffRate::class)->orderByDesc('valid_from');
    }

    /** @return HasMany<ClubFeeAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(ClubFeeAssignment::class);
    }

    public function isFamily(): bool {
        return $this->kind === ClubFeeTariffKind::Family;
    }

    public function hasAgeCriteria(): bool {
        return $this->min_age !== null || $this->max_age !== null;
    }

    /** Satz, der am Stichtag gilt: der jüngste mit valid_from ≤ Stichtag. */
    public function rateOn(CarbonInterface $day): ?ClubFeeTariffRate {
        $rates = $this->relationLoaded('rates') ? $this->rates : $this->rates()->get();
        /** @var ClubFeeTariffRate|null $rate */
        $rate = $rates->first(fn(ClubFeeTariffRate $rate): bool => $rate->valid_from->lessThanOrEqualTo($day));

        return $rate;
    }

    public function fitsAge(?int $age): bool {
        if (! $this->hasAgeCriteria()) {
            return true;
        }
        if ($age === null) {
            return false;
        }

        return ($this->min_age === null || $age >= $this->min_age) && ($this->max_age === null || $age <= $this->max_age);
    }
}
