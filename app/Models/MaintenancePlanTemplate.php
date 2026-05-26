<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Asset\{AssetClass, MaintenanceIntervalKind};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $label
 * @property AssetClass|null $asset_class
 * @property string|null $category_code
 * @property MaintenanceIntervalKind $interval_kind
 * @property int $interval_value
 * @property int $tolerance_days
 * @property string|null $procedure_template_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MaintenancePlanTemplate extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'label',
        'asset_class',
        'category_code',
        'interval_kind',
        'interval_value',
        'tolerance_days',
        'procedure_template_code',
        'is_active',
    ];

    protected $casts = [
        'asset_class' => AssetClass::class,
        'interval_kind' => MaintenanceIntervalKind::class,
        'is_active' => 'bool',
        'interval_value' => 'int',
        'tolerance_days' => 'int',
    ];
}
