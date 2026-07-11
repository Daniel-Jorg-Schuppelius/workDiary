<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Ziel/Zielpfad (Feature 071, MVP-231): Basisjahr → Zieljahr mit
 * linearer Pfadinterpolation im Bericht.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $metric
 * @property string $label
 * @property string $baseline_value
 * @property int $baseline_year
 * @property string $target_value
 * @property int $target_year
 * @property string $unit
 * @property string|null $note
 */
class SustainabilityTarget extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'metric', 'label', 'baseline_value', 'baseline_year',
        'target_value', 'target_year', 'unit', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'baseline_value' => 'decimal:3',
        'target_value' => 'decimal:3',
        'baseline_year' => 'integer',
        'target_year' => 'integer',
    ];

    /** Linear interpolierter Sollwert für ein Jahr (Zielpfad). */
    public function expectedFor(int $year): float {
        $b = (float) $this->baseline_value;
        $t = (float) $this->target_value;
        if ($year <= $this->baseline_year) {
            return $b;
        }
        if ($year >= $this->target_year || $this->target_year === $this->baseline_year) {
            return $t;
        }

        $progress = ($year - $this->baseline_year) / ($this->target_year - $this->baseline_year);

        return round($b + ($t - $b) * $progress, 3);
    }
}
