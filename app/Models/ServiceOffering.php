<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceOffering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Serviceangebot (Feature 065, MVP-154) — mittlere Katalog-Ebene.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $business_service_id
 * @property string $name
 * @property bool $active
 */
class ServiceOffering extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true];

    protected $fillable = ['organization_id', 'business_service_id', 'name', 'description', 'active'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<BusinessService, $this> */
    public function businessService(): BelongsTo {
        return $this->belongsTo(BusinessService::class, 'business_service_id');
    }

    /** @return HasMany<RequestItem, $this> */
    public function requestItems(): HasMany {
        return $this->hasMany(RequestItem::class, 'service_offering_id');
    }
}
