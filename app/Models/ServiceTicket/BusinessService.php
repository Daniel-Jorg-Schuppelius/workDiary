<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BusinessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\ServiceTicket;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Sales\ServiceOffering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};

/**
 * Fachdienst (Feature 065, MVP-154) — oberste Ebene des Servicekatalogs.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property bool $active
 */
class BusinessService extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true];

    protected $fillable = ['organization_id', 'name', 'description', 'active'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean'];

    /** @return HasMany<ServiceOffering, $this> */
    public function offerings(): HasMany {
        return $this->hasMany(ServiceOffering::class, 'business_service_id');
    }

    /**
     * Services, ohne die dieser nicht läuft (MVP-1256).
     *
     * @return BelongsToMany<BusinessService, $this>
     */
    public function dependsOn(): BelongsToMany {
        return $this->belongsToMany(self::class, 'business_service_dependencies', 'business_service_id', 'depends_on_business_service_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<BusinessService, $this> */
    public function dependents(): BelongsToMany {
        return $this->belongsToMany(self::class, 'business_service_dependencies', 'depends_on_business_service_id', 'business_service_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    /** @return HasMany<ServiceTicket, $this> */
    public function tickets(): HasMany {
        return $this->hasMany(ServiceTicket::class);
    }
}
