<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimateItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Costing;

use App\Models\Concerns\{Auditable, HasSqid, IsDocumentLine};
use App\Models\Contracts\DocumentLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Kostenelement einer Ermittlung (Feature 109, MVP-646) — im
 * Elementverfahren eine Kostengruppe, im Objektvergleich ein Kennwert.
 *
 * `code` trägt die Nummer, gegen die die Kostenverfolgung liest; ohne Nummer
 * ist die Zeile eine Position ohne Kostengruppen-Bezug und fällt in der
 * Auswertung unter „ohne Zuordnung".
 *
 * @property int $id
 * @property int $cost_estimate_id
 * @property string|null $code
 * @property string $label
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $unit_price
 * @property string|null $amount
 * @property int $level
 * @property string|null $parent_code
 * @property int $position
 */
class CostEstimateItem extends Model implements DocumentLine {
    use Auditable;
    /** @use HasFactory<\Database\Factories\Costing\CostEstimateItemFactory> */
    use HasFactory;
    use HasSqid;
    use IsDocumentLine;

    protected $table = 'cost_estimate_items';

    protected $fillable = [
        'cost_estimate_id', 'code', 'label', 'quantity', 'unit',
        'unit_price', 'amount', 'level', 'parent_code', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'amount' => 'decimal:2',
        'level' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<CostEstimate, $this> */
    public function estimate(): BelongsTo {
        return $this->belongsTo(CostEstimate::class, 'cost_estimate_id');
    }

    /** @return BelongsTo<CostEstimate, $this> */
    public function lineDocument(): BelongsTo {
        return $this->estimate();
    }

    /** @return array<string, string|null> */
    protected static function lineColumns(): array {
        return ['tax_rate' => null, 'discount_percent' => null, 'discount_amount' => null, 'net_amount' => 'amount'];
    }
}
