<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Asset\MaintenanceIntervalKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $asset_id
 * @property string $code
 * @property string $label
 * @property MaintenanceIntervalKind $interval_kind
 * @property int $interval_value
 * @property int $tolerance_days
 * @property string|null $procedure_template_code
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_due_on
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MaintenancePlan extends Model {
    /** @use HasFactory<MaintenancePlanFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'asset_id',
        'code',
        'label',
        'interval_kind',
        'interval_value',
        'tolerance_days',
        'procedure_template_code',
        'last_run_at',
        'next_due_on',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'interval_kind' => MaintenanceIntervalKind::class,
        'last_run_at' => 'datetime',
        'next_due_on' => 'date',
        'is_active' => 'bool',
        'interval_value' => 'int',
        'tolerance_days' => 'int',
        'subject_id' => 'int',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    public function subjectIsAsset(): bool {
        return $this->subject_type === Asset::class;
    }

    public function subjectIsRoom(): bool {
        return $this->subject_type === Room::class;
    }

    public function isDue(?Carbon $reference = null): bool {
        if (! $this->is_active || $this->next_due_on === null) {
            return false;
        }
        $ref = $reference ?? Carbon::now();
        $threshold = $this->next_due_on->copy()->addDays($this->tolerance_days);

        return $ref->startOfDay()->greaterThanOrEqualTo($threshold->startOfDay());
    }
}
