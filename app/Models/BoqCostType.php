<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCostType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Kostenart der Kalkulationsdaten (Feature 109, MVP-647) — Lohn,
 * Material, Gerät, Fremdleistung.
 *
 * **Der Zuschlag hängt an der Art, nicht am Ansatz:** Ein Betrieb schlägt auf
 * Lohn anders zu als auf Material, aber nicht je Position. Der Schlüssel
 * stammt aus dem kalkulierenden System — GAEB schreibt hier keinen Katalog
 * vor, deshalb wird er übernommen, wie er kommt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property string $cost_key
 * @property string|null $description
 * @property string|null $unit
 * @property string|null $markup_percent
 * @property int $position
 */
class BoqCostType extends Model {
    use BelongsToOrganization;

    protected $table = 'boq_cost_types';

    protected $fillable = [
        'organization_id', 'bill_of_quantity_id', 'cost_key',
        'description', 'unit', 'markup_percent', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'markup_percent' => 'decimal:6',
        'position' => 'integer',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }
}
