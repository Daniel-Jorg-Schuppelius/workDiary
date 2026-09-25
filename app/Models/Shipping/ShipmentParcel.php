<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentParcel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Shipping;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Inventory\{StockDelivery, StockSerial};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * Packstück einer Auslieferung (Feature 059, MVP-900): Gewicht, Maße und die
 * Seriennummern darin. Liegen Packstücke vor, gehen sie als Pakete in den
 * Versandauftrag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $stock_delivery_id
 * @property int $position
 * @property int $weight_grams
 * @property int|null $length_cm
 * @property int|null $width_cm
 * @property int|null $height_cm
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockSerial> $serials
 */
class ShipmentParcel extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'stock_delivery_id', 'position', 'weight_grams', 'length_cm', 'width_cm', 'height_cm', 'created_by', 'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'weight_grams' => 'integer',
        'length_cm' => 'integer',
        'width_cm' => 'integer',
        'height_cm' => 'integer',
    ];

    /** @return BelongsTo<StockDelivery, $this> */
    public function delivery(): BelongsTo {
        return $this->belongsTo(StockDelivery::class, 'stock_delivery_id');
    }

    /** @return BelongsToMany<StockSerial, $this> */
    public function serials(): BelongsToMany {
        return $this->belongsToMany(StockSerial::class, 'shipment_parcel_serials')->withTimestamps();
    }
}
