<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Asset\{AssetClass, AssetHealth, AssetOwnership, AssetStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments};
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $asset_no
 * @property AssetClass $asset_class
 * @property string|null $category_code
 * @property string $name
 * @property string|null $manufacturer
 * @property string|null $model
 * @property string|null $serial_no
 * @property string|null $inventory_no
 * @property int|null $customer_id
 * @property int|null $room_id
 * @property AssetOwnership $owned_by
 * @property string|null $location_text
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property AssetStatus $status
 * @property AssetHealth $health
 * @property Carbon|null $commissioned_on
 * @property Carbon|null $decommissioned_on
 * @property Carbon|null $warranty_until
 * @property Carbon|null $next_maintenance_on
 * @property Carbon|null $next_inspection_on
 * @property string|null $notes
 * @property array<string, mixed>|null $custom
 */
class Asset extends Model {
    /** @use HasFactory<AssetFactory> */
    use Auditable, BelongsToOrganization, HasAttachments, HasFactory;

    protected $fillable = [
        'organization_id',
        'asset_no',
        'asset_class',
        'category_code',
        'name',
        'manufacturer',
        'model',
        'serial_no',
        'inventory_no',
        'customer_id',
        'room_id',
        'owned_by',
        'location_text',
        'location_lat',
        'location_lng',
        'status',
        'health',
        'commissioned_on',
        'decommissioned_on',
        'warranty_until',
        'next_maintenance_on',
        'next_inspection_on',
        'notes',
        'custom',
    ];

    protected $casts = [
        'asset_class' => AssetClass::class,
        'owned_by' => AssetOwnership::class,
        'status' => AssetStatus::class,
        'health' => AssetHealth::class,
        'commissioned_on' => 'date',
        'decommissioned_on' => 'date',
        'warranty_until' => 'date',
        'next_maintenance_on' => 'date',
        'next_inspection_on' => 'date',
        'custom' => 'array',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class)->latest('start_at')->latest('id');
    }

    /** @return HasMany<MaterialUsage, $this> */
    public function materialUsages(): HasMany {
        return $this->hasMany(MaterialUsage::class);
    }

    /** @return MorphMany<Protocol, $this> */
    public function protocols(): MorphMany {
        /** @var MorphMany<Protocol, $this> $relation */
        $relation = $this->morphMany(Protocol::class, 'subject')->orderByDesc('occurred_at')->orderByDesc('id');

        return $relation;
    }

    /** @return MorphMany<OpenIssue, $this> */
    public function openIssues(): MorphMany {
        /** @var MorphMany<OpenIssue, $this> $relation */
        $relation = $this->morphMany(OpenIssue::class, 'subject')->latest('id');

        return $relation;
    }

    /** @return HasMany<MaintenancePlan, $this> */
    public function maintenancePlans(): HasMany {
        return $this->hasMany(MaintenancePlan::class)->orderBy('next_due_on');
    }

    /** @return HasMany<SoftwareInstallation, $this> */
    public function softwareInstallations(): HasMany {
        return $this->hasMany(SoftwareInstallation::class)->latest('id');
    }

    /** @return HasOne<SoftwareInstallation, $this> */
    public function operatingSystem(): HasOne {
        return $this->hasOne(SoftwareInstallation::class)->where('is_operating_system', true);
    }
}
