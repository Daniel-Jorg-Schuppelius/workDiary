<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemPriceSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Gaeb\GaebPhase;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preis-Snapshot einer LV-Position je GAEB-Phase/Import (Feature 049, MVP-082).
 * Hält Einheits-/Gesamtpreis historisiert, ohne den führenden Faktura-Preis zu
 * ersetzen.
 *
 * @property int $id
 * @property int $boq_item_id
 * @property int|null $gaeb_import_id
 * @property GaebPhase|null $phase
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Money|null $total_price
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property \Illuminate\Support\Carbon $captured_at
 */
class BoqItemPriceSnapshot extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'boq_item_id',
        'gaeb_import_id',
        'phase',
        'unit_price',
        'total_price',
        'currency',
        'captured_at',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'phase' => GaebPhase::class,
        'unit_price' => MoneyCast::class . ':currency,4',
        'total_price' => MoneyCast::class . ':currency,4',
        'captured_at' => 'datetime',
    ];

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }

    /** @return BelongsTo<GaebImport, $this> */
    public function import(): BelongsTo {
        return $this->belongsTo(GaebImport::class, 'gaeb_import_id');
    }
}
