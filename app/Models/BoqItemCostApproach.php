<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemCostApproach.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Kostenansatz einer Position (Feature 109, MVP-647) — was eine Kostenart
 * zu dieser Position beiträgt.
 *
 * Die dokumentierte Umrechnung lautet **`KW = Menge × Wert ÷ Leistung`**. Ohne
 * Leistungsangabe steht der Wert für sich; durch eine angenommene Leistung zu
 * teilen veränderte die Kalkulation still.
 *
 * Der Verweis auf die Kostenart läuft über deren **Schlüssel**, nicht über
 * einen Fremdschlüssel — die Datei tut es ebenso, und ein Fremdschlüssel
 * überlebte den Reimport nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $boq_item_id
 * @property string $cost_key
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $performance
 * @property string|null $value
 * @property int $position
 */
class BoqItemCostApproach extends Model {
    use BelongsToOrganization;

    protected $table = 'boq_item_cost_approaches';

    protected $fillable = [
        'organization_id', 'boq_item_id', 'cost_key',
        'quantity', 'unit', 'performance', 'value', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:3',
        'performance' => 'decimal:3',
        'value' => 'decimal:3',
        'position' => 'integer',
    ];

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }

    /**
     * Der kalkulierte Wert dieses Ansatzes: `Menge × Wert ÷ Leistung`.
     *
     * Ohne Menge oder Wert gibt es nichts zu rechnen; ohne Leistung entfällt
     * die Division, statt eine Eins zu unterstellen.
     */
    public function calculatedAmount(): ?float {
        if ($this->quantity === null || $this->value === null) {
            return null;
        }

        $amount = (float) $this->quantity * (float) $this->value;
        $performance = $this->performance === null ? null : (float) $this->performance;
        if ($performance !== null && $performance != 0.0) {
            $amount /= $performance;
        }

        return round($amount, 2);
    }
}
