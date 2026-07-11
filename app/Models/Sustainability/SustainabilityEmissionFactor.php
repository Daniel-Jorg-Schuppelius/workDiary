<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityEmissionFactor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Emissionsfaktor (Feature 071, MVP-228): kg CO2e je Aktivitätseinheit
 * mit Gültigkeit, Scope, Qualität und Quelle — Einheiten als Code-Strings
 * (Flexibilitätsplan D5). Mandantenschutz läuft über das Set.
 *
 * @property int $id
 * @property int $factor_set_id
 * @property string $activity_code
 * @property string $label
 * @property string $unit_code
 * @property string $factor
 * @property int $scope
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property string $quality
 * @property string|null $source_note
 */
class SustainabilityEmissionFactor extends Model {
    use HasSqid;

    protected $fillable = [
        'factor_set_id', 'activity_code', 'label', 'unit_code', 'factor',
        'scope', 'valid_from', 'valid_to', 'quality', 'source_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'factor' => 'decimal:6',
        'scope' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @return BelongsTo<SustainabilityFactorSet, $this> */
    public function set(): BelongsTo {
        return $this->belongsTo(SustainabilityFactorSet::class, 'factor_set_id');
    }
}
