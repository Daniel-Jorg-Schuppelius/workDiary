<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teilrückmeldung eines Fertigungsauftrags (Feature 047, MVP-065). Gut-,
 * Ausschuss- und Nacharbeitsmenge bleiben getrennt auswertbar. Mandantengrenze
 * transitiv über den Auftrag.
 *
 * @property int $id
 * @property int $manufacturing_order_id
 * @property numeric-string $produced_qty
 * @property numeric-string $good_qty
 * @property numeric-string $scrap_qty
 * @property numeric-string $rework_qty
 * @property int|null $reported_by
 */
class ManufacturingOrderReport extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'manufacturing_order_id',
        'stock_lot_id',
        'produced_qty',
        'good_qty',
        'scrap_qty',
        'rework_qty',
        'note',
        'reported_by',
        'reported_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'produced_qty' => 'decimal:4',
        'good_qty' => 'decimal:4',
        'scrap_qty' => 'decimal:4',
        'rework_qty' => 'decimal:4',
        'reported_at' => 'datetime',
    ];

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id');
    }
}
