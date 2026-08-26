<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Warehouse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\WarehouseKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Lokaler Lagerort (Feature 048, MVP-067). Seit MVP-706 mit Art (fest,
 * Fahrzeug, Standort, Team), optionalem Bezug und Lagerplätzen (bins).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property WarehouseKind $kind
 * @property int|null $site_id
 * @property int|null $vehicle_id
 * @property int|null $team_id
 * @property bool $active
 * @property bool $blocked
 */
class Warehouse extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'kind',
        'is_default',
        'active',
        'blocked',
        'location_note',
        'site_id',
        'vehicle_id',
        'team_id',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => WarehouseKind::class,
        'is_default' => 'boolean',
        'active' => 'boolean',
        'blocked' => 'boolean',
    ];

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<WarehouseBin, $this> */
    public function bins(): HasMany {
        return $this->hasMany(WarehouseBin::class)->orderBy('sort_order')->orderBy('code');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }

    /** Anzeigename des Bezugs (Standort/Fahrzeug/Team) je nach Art; null bei festem Lager. */
    public function referenceLabel(): ?string {
        return match ($this->kind) {
            WarehouseKind::Site => $this->site?->name,
            WarehouseKind::Vehicle => $this->vehicle?->displayName(),
            WarehouseKind::Team => $this->team?->name,
            WarehouseKind::Fixed => null,
        };
    }
}
