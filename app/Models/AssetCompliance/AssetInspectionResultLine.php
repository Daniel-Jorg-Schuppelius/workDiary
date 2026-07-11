<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionResultLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ergebniszeile je Anforderung (MVP-286): Messwert gegen die zum
 * Prüfzeitpunkt eingefrorenen Grenzwerte (P2-Snapshot in limit_min/max).
 * Tabelle: asset_inspection_results.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_inspection_event_id
 * @property string $label
 * @property numeric-string|null $value
 * @property bool $passed
 */
class AssetInspectionResultLine extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'asset_inspection_results';

    protected $fillable = [
        'organization_id', 'asset_inspection_event_id',
        'asset_compliance_requirement_id', 'label', 'value', 'unit',
        'limit_min', 'limit_max', 'passed', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:4',
        'limit_min' => 'decimal:4',
        'limit_max' => 'decimal:4',
        'passed' => 'boolean',
    ];

    /** @return BelongsTo<AssetInspectionEvent, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(AssetInspectionEvent::class, 'asset_inspection_event_id');
    }

    /** @return BelongsTo<AssetComplianceRequirement, $this> */
    public function requirement(): BelongsTo {
        return $this->belongsTo(AssetComplianceRequirement::class, 'asset_compliance_requirement_id');
    }
}
