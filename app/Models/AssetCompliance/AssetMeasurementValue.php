<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetMeasurementValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roh-Messwert einer Prüfung (MVP-286): bleibt als Snapshot an der Prüfung
 * gebunden — auch wenn sich Toleranzen später ändern.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_inspection_event_id
 * @property string $label
 * @property numeric-string $value
 */
class AssetMeasurementValue extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_inspection_event_id', 'label', 'value',
        'unit', 'measured_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:4',
        'measured_at' => 'datetime',
    ];

    /** @return BelongsTo<AssetInspectionEvent, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(AssetInspectionEvent::class, 'asset_inspection_event_id');
    }
}
