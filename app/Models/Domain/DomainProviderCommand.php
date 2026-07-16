<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Enums\Domain\DomainProviderCommandStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, User};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Schreibender Provider-Befehl als dedizierte Outbox-Zeile (Feature 083,
 * MVP-388/390/391). Trägt idempotente `command_id`, Preflight-Snapshot,
 * Payload-Hash, Freigaben (Vier-Augen für Hochrisiko: `approved_by` ≠
 * `requested_by`), Status und die REDIGIERTE Providerantwort. Ausgang ohne
 * vollständiges `EOF` bleibt `Unknown` und wird reconciled, nie blind
 * wiederholt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $command_id
 * @property string $capability
 * @property string $command
 * @property string|null $target
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $preflight_snapshot
 * @property string $payload_hash
 * @property DomainProviderCommandStatus $status
 * @property bool $requires_second_approval
 * @property int|null $requested_by_user_id
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $confirmed_at
 * @property string|null $provider_code
 * @property string|null $provider_response
 * @property Carbon|null $reconciled_at
 * @property string|null $reconciliation_note
 * @property int $attempts
 * @property string|null $last_error
 */
class DomainProviderCommand extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'command_id',
        'capability',
        'command',
        'target',
        'subject_type',
        'subject_id',
        'customer_id',
        'payload',
        'preflight_snapshot',
        'payload_hash',
        'status',
        'requires_second_approval',
        'requested_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'dispatched_at',
        'confirmed_at',
        'provider_code',
        'provider_response',
        'reconciled_at',
        'reconciliation_note',
        'attempts',
        'last_error',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'preflight_snapshot' => 'array',
        'status' => DomainProviderCommandStatus::class,
        'requires_second_approval' => 'boolean',
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** Vier-Augen erfüllt: Freigeber gesetzt und ≠ Antragsteller. */
    public function hasFourEyesApproval(): bool {
        return $this->approved_by_user_id !== null
            && $this->approved_by_user_id !== $this->requested_by_user_id;
    }

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** Nicht-nullbare Verbindung (FK ist NOT NULL). */
    public function providerConnection(): DomainProviderConnection {
        $connection = $this->getRelationValue('connection');
        if (! $connection instanceof DomainProviderConnection) {
            throw new \RuntimeException('DomainProviderCommand ohne Verbindung.');
        }

        return $connection;
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
