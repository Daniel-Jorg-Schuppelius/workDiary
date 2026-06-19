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

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokaler Lagerort (Feature 048, MVP-067).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
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
        'is_default',
        'active',
        'blocked',
        'location_note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
        'blocked' => 'boolean',
    ];

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany {
        return $this->hasMany(StockMovement::class);
    }
}
