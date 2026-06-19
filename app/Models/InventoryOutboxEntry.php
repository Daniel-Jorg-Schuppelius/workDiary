<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryOutboxEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\OutboxStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbox-Eintrag zur externen Bestandsführung (Feature 048, MVP-072).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $plugin_id
 * @property string $operation
 * @property array<string, mixed> $payload
 * @property string $idempotency_key
 * @property OutboxStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property int|null $stock_movement_id
 */
class InventoryOutboxEntry extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'inventory_outbox';

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'operation',
        'payload',
        'idempotency_key',
        'status',
        'attempts',
        'last_error',
        'stock_movement_id',
        'confirmed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => OutboxStatus::class,
        'attempts' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo {
        return $this->belongsTo(StockMovement::class);
    }
}
