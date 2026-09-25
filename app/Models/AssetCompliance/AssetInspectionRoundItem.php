<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionRoundItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soll-Position einer Prüfmittelrunde (MVP-899); erledigt, sobald in der
 * Runde eine Prüfung zur Pflicht erfasst ist.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_inspection_round_id
 * @property int $asset_compliance_assignment_id
 * @property int $asset_id
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property int|null $asset_inspection_event_id
 */
class AssetInspectionRoundItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_inspection_round_id', 'asset_compliance_assignment_id', 'asset_id', 'due_on', 'asset_inspection_event_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_on' => 'date',
    ];

    public function isDone(): bool {
        return $this->asset_inspection_event_id !== null;
    }

    public function isOverdue(): bool {
        return ! $this->isDone() && $this->due_on !== null && $this->due_on->lessThan(today());
    }

    /** @return BelongsTo<AssetInspectionRound, $this> */
    public function round(): BelongsTo {
        return $this->belongsTo(AssetInspectionRound::class, 'asset_inspection_round_id');
    }

    /** @return BelongsTo<AssetComplianceAssignment, $this> */
    public function assignment(): BelongsTo {
        return $this->belongsTo(AssetComplianceAssignment::class, 'asset_compliance_assignment_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<AssetInspectionEvent, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(AssetInspectionEvent::class, 'asset_inspection_event_id');
    }
}
