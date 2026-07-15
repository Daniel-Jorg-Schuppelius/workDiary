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
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid, HasTags};
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
 * @property int|null $product_id
 * @property string|null $model
 * @property string|null $serial_no
 * @property string|null $inventory_no
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property int|null $sla_contract_id
 * @property bool $shared_remote
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
 * @property-read Product|null $product
 */
class Asset extends Model {
    /** @use HasFactory<AssetFactory> */
    use Auditable, BelongsToOrganization, HasAttachments, HasFactory, HasSqid, HasTags;

    protected $fillable = [
        'organization_id',
        'asset_no',
        'asset_class',
        'category_code',
        'name',
        'manufacturer',
        'model',
        'product_id',
        'serial_no',
        'inventory_no',
        'customer_id',
        'foreign_customer_id',
        'sla_contract_id',
        'shared_remote',
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
        'acquisition_cost',
        'acquired_on',
        'acquired_from_supplier_id',
    ];

    protected $casts = [
        'asset_class' => AssetClass::class,
        'shared_remote' => 'boolean',
        'owned_by' => AssetOwnership::class,
        'status' => AssetStatus::class,
        'health' => AssetHealth::class,
        'commissioned_on' => 'date',
        'decommissioned_on' => 'date',
        'warranty_until' => 'date',
        'next_maintenance_on' => 'date',
        'next_inspection_on' => 'date',
        'custom' => 'array',
        'acquired_on' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Typ-Ebene Hersteller-Modell (produktmodell-konzept.md, MVP-369).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo {
        return $this->belongsTo(Product::class);
    }

    /**
     * Direkt zugeordneter SLA-Vertrag (Override der Kunden-/Default-Auflösung,
     * Feature 027 → Rang 48).
     *
     * @return BelongsTo<SlaContract, $this>
     */
    public function slaContract(): BelongsTo {
        return $this->belongsTo(SlaContract::class);
    }

    /**
     * Unveränderliche Eigentümerwechsel-Historie (Feature 027 → Rang 49),
     * jüngste zuerst.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AssetOwnershipChange, $this>
     */
    public function ownershipChanges(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(AssetOwnershipChange::class)->latest('changed_at');
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
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

    /** @return HasMany<AssetAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(AssetAssignment::class)->latest('checked_out_at')->latest('id');
    }

    /** @return HasOne<AssetAssignment, $this> */
    public function currentAssignment(): HasOne {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latest('checked_out_at')->latest('id');
    }

    /** @return HasMany<AssetDefect, $this> */
    public function defects(): HasMany {
        return $this->hasMany(AssetDefect::class)->latest('reported_at')->latest('id');
    }

    /** @return HasOne<\App\Models\Rental\RentalProfile, $this> */
    public function rentalProfile(): HasOne {
        return $this->hasOne(\App\Models\Rental\RentalProfile::class);
    }

    /** @return HasMany<AssetBlock, $this> */
    public function blocks(): HasMany {
        return $this->hasMany(AssetBlock::class)->latest('blocked_from');
    }

    /** @return HasMany<AssetBlock, $this> */
    public function activeBlocks(): HasMany {
        return $this->hasMany(AssetBlock::class)->active();
    }

    /** @return HasMany<\App\Models\AssetCompliance\AssetComplianceAssignment, $this> */
    public function complianceAssignments(): HasMany {
        return $this->hasMany(\App\Models\AssetCompliance\AssetComplianceAssignment::class);
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
