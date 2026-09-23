<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Integration\IntegrationOutboxStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\{Builder, MassPrunable};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eintrag der generischen Integrations-Outbox (Feature 055, MVP-114):
 * idempotent über (Organisation, idempotency_key); `last_error` trägt nur
 * die gekürzte Fehlerklasse.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $plugin_id
 * @property string $operation
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed> $payload
 * @property string $idempotency_key
 * @property IntegrationOutboxStatus $status
 * @property int $attempts
 * @property string|null $last_error
 */
class IntegrationOutboxEntry extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use MassPrunable;

    protected $table = 'integration_outbox';

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'operation',
        'subject_type',
        'subject_id',
        'payload',
        'idempotency_key',
        'status',
        'attempts',
        'last_error',
        'confirmed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'status' => IntegrationOutboxStatus::class,
        'confirmed_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
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
            $q->where(fn (Builder $sub) => $sub->where('status', IntegrationOutboxStatus::Confirmed->value)->where('updated_at', '<', now()->subDays($okDays)))
                ->orWhere(fn (Builder $sub) => $sub->whereIn('status', [IntegrationOutboxStatus::Failed->value, IntegrationOutboxStatus::CompensationRequired->value])->where('updated_at', '<', now()->subDays($failedDays)));
        });
    }
}
