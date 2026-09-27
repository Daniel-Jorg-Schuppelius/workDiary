<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionOrderItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionResult;
use App\Models\Asset\Asset;
use App\Models\Concerns\{BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Prüfmittel eines Prüfauftrags (MVP-938) mit der Rückmeldung des
 * Dienstleisters; übernommen wird sie als Prüfereignis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_inspection_order_id
 * @property int|null $asset_inspection_schedule_id
 * @property int $asset_compliance_assignment_id
 * @property int $asset_id
 * @property AssetInspectionResult|null $result
 * @property Carbon|null $performed_on
 * @property Carbon|null $valid_until
 * @property string|null $certificate_no
 * @property string|null $note
 * @property int|null $asset_inspection_event_id
 */
class AssetInspectionOrderItem extends Model {
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_inspection_order_id', 'asset_inspection_schedule_id', 'asset_compliance_assignment_id', 'asset_id',
        'result', 'performed_on', 'valid_until', 'certificate_no', 'note', 'asset_inspection_event_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['result' => AssetInspectionResult::class, 'performed_on' => 'date', 'valid_until' => 'date'];

    /** @return BelongsTo<AssetInspectionOrder, $this> */
    public function order(): BelongsTo {
        return $this->belongsTo(AssetInspectionOrder::class, 'asset_inspection_order_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<AssetComplianceAssignment, $this> */
    public function assignment(): BelongsTo {
        return $this->belongsTo(AssetComplianceAssignment::class, 'asset_compliance_assignment_id');
    }
}
