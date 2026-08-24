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
use Illuminate\Database\Eloquent\{Builder, MassPrunable};
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
    use MassPrunable;

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

    /**
     * Aufbewahrung der Outbox (Vollscan 2026-08-23, J9): bestätigte Einträge
     * nach `integration.delivery_retention_days`, endgültig gescheiterte/
     * kompensationspflichtige nach `integration.failed_retention_days`;
     * pending/processing bleiben (sie sind Arbeit, kein Protokoll).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder {
        $okDays = max(1, (int) config('integration.delivery_retention_days', 90));
        $failedDays = max(1, (int) config('integration.failed_retention_days', 180));

        return static::query()->where(function (Builder $q) use ($okDays, $failedDays): void {
            $q->where(fn (Builder $sub) => $sub->where('status', OutboxStatus::Confirmed->value)->where('updated_at', '<', now()->subDays($okDays)))
                ->orWhere(fn (Builder $sub) => $sub->whereIn('status', [OutboxStatus::Failed->value, OutboxStatus::CompensationRequired->value])->where('updated_at', '<', now()->subDays($failedDays)));
        });
    }
}
