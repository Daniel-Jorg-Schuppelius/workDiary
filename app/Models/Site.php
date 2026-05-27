<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Site.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $name
 * @property string|null $code
 * @property string|null $address_street
 * @property string|null $address_zip
 * @property string|null $address_city
 * @property string|null $country
 * @property string|null $geo_lat
 * @property string|null $geo_lng
 * @property bool $is_active
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Site extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'name',
        'code',
        'address_street',
        'address_zip',
        'address_city',
        'country',
        'geo_lat',
        'geo_lng',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Building, $this> */
    public function buildings(): HasMany {
        return $this->hasMany(Building::class)->orderBy('name');
    }
}
