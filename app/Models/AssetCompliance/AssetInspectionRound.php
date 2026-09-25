<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionRound.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionRoundStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Prüfmittelrunde (MVP-899): Soll-Liste der bis `due_until` fälligen
 * Prüfpflichten eines Standorts oder einer Gruppe, beim Anlegen eingefroren.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $location_text
 * @property string|null $category_code
 * @property int|null $asset_compliance_profile_id
 * @property int|null $customer_id
 * @property \Illuminate\Support\Carbon $due_until
 * @property AssetInspectionRoundStatus $status
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AssetInspectionRoundItem> $items
 */
class AssetInspectionRound extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'location_text', 'category_code', 'asset_compliance_profile_id',
        'customer_id', 'due_until', 'status', 'closed_at', 'created_by', 'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AssetInspectionRoundStatus::class,
        'due_until' => 'date',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<AssetInspectionRoundItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(AssetInspectionRoundItem::class);
    }

    /** @return BelongsTo<AssetComplianceProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(AssetComplianceProfile::class, 'asset_compliance_profile_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }
}
