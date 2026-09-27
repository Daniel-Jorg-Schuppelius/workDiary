<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StrategicKeyResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kennzahl eines strategischen Ziels (MVP-942): Ausgangs-, Ziel- und
 * aktueller Wert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $strategic_objective_id
 * @property string $label
 * @property string|null $unit
 * @property numeric-string|null $baseline_value
 * @property numeric-string $target_value
 * @property numeric-string|null $current_value
 * @property int $position
 */
class StrategicKeyResult extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'strategic_objective_id', 'label', 'unit', 'baseline_value', 'target_value', 'current_value', 'position'];

    /** @var array<string, string> */
    protected $casts = ['baseline_value' => 'decimal:4', 'target_value' => 'decimal:4', 'current_value' => 'decimal:4', 'position' => 'integer'];

    /** @return BelongsTo<StrategicObjective, $this> */
    public function objective(): BelongsTo {
        return $this->belongsTo(StrategicObjective::class, 'strategic_objective_id');
    }

    /** Fortschritt 0–100 zwischen Ausgangs- und Zielwert; ohne aktuellen Wert null. */
    public function progress(): ?int {
        if ($this->current_value === null) {
            return null;
        }
        $base = (float) ($this->baseline_value ?? 0);
        $span = (float) $this->target_value - $base;
        if ($span == 0.0) {
            return (float) $this->current_value >= (float) $this->target_value ? 100 : 0;
        }

        return (int) max(0, min(100, round(((float) $this->current_value - $base) / $span * 100)));
    }
}
