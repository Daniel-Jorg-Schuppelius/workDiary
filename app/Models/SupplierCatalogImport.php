<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Import-Lauf-Protokoll einer Katalogquelle (Feature 050, MVP-091).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_source_id
 * @property string $trigger
 * @property string $status
 * @property int $rows
 * @property int $created
 * @property int $updated
 * @property int $unchanged
 * @property int $price_changed
 * @property int $discontinued
 * @property string|null $error
 * @property string|null $file_hash
 * @property \Illuminate\Support\Carbon $created_at
 */
class SupplierCatalogImport extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'organization_id',
        'supplier_catalog_source_id',
        'trigger',
        'status',
        'rows',
        'created',
        'updated',
        'unchanged',
        'price_changed',
        'discontinued',
        'error',
        'file_hash',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rows' => 'integer',
        'created' => 'integer',
        'updated' => 'integer',
        'unchanged' => 'integer',
        'price_changed' => 'integer',
        'discontinued' => 'integer',
    ];

    /** @return BelongsTo<SupplierCatalogSource, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(SupplierCatalogSource::class, 'supplier_catalog_source_id');
    }
}
