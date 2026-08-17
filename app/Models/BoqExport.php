<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Gaeb\GaebPhase;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll eines GAEB-Exports (Feature 049, MVP-085).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property GaebPhase $phase
 * @property string $gaeb_version
 * @property string $file_hash
 * @property int $item_count
 */
class BoqExport extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'format',
        'losses',
        'organization_id',
        'bill_of_quantity_id',
        'phase',
        'gaeb_version',
        'file_hash',
        'item_count',
        'created_by',
    ];

    protected $casts = [
        'losses' => 'array',
        'phase' => GaebPhase::class,
        'item_count' => 'integer',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }
}
