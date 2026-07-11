<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWarehouseMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Projektion einer JTL-Lagerstätte + Zuordnung auf ein WorkDiary-Lager
 * (Feature 078, MVP-319). Die Projektion (Name, Sperren, Aktivstatus) wird
 * beim Sync aktualisiert; die Zuordnung (`warehouse_id`) pflegt ein Admin.
 * Ohne Zuordnung bleibt der Schreibpfad für dieses Lager blockiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $jtl_warehouse_id
 * @property string $name
 * @property string|null $code
 * @property string|null $warehouse_type
 * @property bool $jtl_is_active
 * @property bool $lock_for_shipment
 * @property bool $lock_for_availability
 * @property int|null $warehouse_id
 * @property Carbon|null $last_seen_at
 */
class JtlWarehouseMapping extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $table = 'jtl_warehouse_mappings';

    protected $fillable = [
        'organization_id',
        'jtl_warehouse_id',
        'name',
        'code',
        'warehouse_type',
        'jtl_is_active',
        'lock_for_shipment',
        'lock_for_availability',
        'warehouse_id',
        'last_seen_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'jtl_is_active' => 'boolean',
        'lock_for_shipment' => 'boolean',
        'lock_for_availability' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    public function isMapped(): bool {
        return $this->warehouse_id !== null;
    }
}
