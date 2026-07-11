<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * orgaMAX-Buchhaltung-Verbindung (Feature 077, MVP-306): genau eine je
 * Organisation. API-Key, Secret, ownershipId und Bearer-Token sind
 * verschlüsselt und `$hidden` — sie erscheinen nie in Audit-Payloads,
 * Logs, Supportdiagnosen oder Fehlermeldungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $mode
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property string|null $ownership_id
 * @property string|null $bearer_token
 * @property Carbon|null $token_expires_at
 * @property array<int, string>|null $granted_scopes
 * @property array<string, mixed>|null $account_snapshot
 * @property string $status
 * @property string|null $blocked_reason
 * @property string|null $intent_token_hash
 * @property Carbon|null $intent_expires_at
 * @property int|null $connected_by
 * @property Carbon|null $confirmed_at
 * @property array<string, array<string, mixed>>|null $capabilities
 * @property array<string, int|string>|null $checkpoints
 * @property Carbon|null $last_sync_at
 * @property array<string, int>|null $last_sync_counters
 * @property string|null $last_error
 * @property array<int, string>|null $contract_notes
 */
class OrgaMaxConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const MODE_PRIVATE = 'private';

    public const MODE_MARKETPLACE = 'marketplace';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_CALLBACK = 'pending_callback';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DISCONNECTED = 'disconnected';

    /** Capability-Schlüssel der Datenführerschafts-Matrix (MVP-305). */
    public const CAPABILITIES = [
        'customers',
        'suppliers',
        'articles',
        'billing',
        'payments',
        'expenses',
        'documents',
    ];

    protected $table = 'orgamax_connections';

    /** @var list<string> Secrets nie in Array-/JSON-/Audit-Output. */
    protected $hidden = ['api_key', 'api_secret', 'ownership_id', 'bearer_token', 'intent_token_hash'];

    protected $fillable = [
        'organization_id',
        'mode',
        'api_key',
        'api_secret',
        'ownership_id',
        'bearer_token',
        'token_expires_at',
        'granted_scopes',
        'account_snapshot',
        'status',
        'blocked_reason',
        'intent_token_hash',
        'intent_expires_at',
        'connected_by',
        'confirmed_at',
        'capabilities',
        'checkpoints',
        'last_sync_at',
        'last_sync_counters',
        'last_error',
        'contract_notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'ownership_id' => 'encrypted',
        'bearer_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'granted_scopes' => 'array',
        'account_snapshot' => 'array',
        'intent_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'capabilities' => 'array',
        'checkpoints' => 'array',
        'last_sync_at' => 'datetime',
        'last_sync_counters' => 'array',
        'contract_notes' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Datenführerschaft einer Capability: `orgamax`, `workdiary` oder
     * `manual_review`. Sicherer Standard ist lesend/manuelle Prüfung.
     */
    public function capabilityLeader(string $capability): string {
        return (string) ($this->capabilities[$capability]['leader'] ?? 'manual_review');
    }

    public function capabilityEnabled(string $capability): bool {
        return (bool) ($this->capabilities[$capability]['enabled'] ?? false);
    }

    /** Poll-Checkpoint je Ressource (ISO-8601) — null = nie gelaufen. */
    public function checkpoint(string $resource): ?Carbon {
        $raw = $this->checkpoints[$resource] ?? null;

        return $raw === null ? null : Carbon::parse((string) $raw);
    }

    public function setCheckpoint(string $resource, Carbon $at): void {
        $checkpoints = (array) $this->checkpoints;
        $checkpoints[$resource] = $at->toIso8601String();
        $this->checkpoints = $checkpoints;
    }
}
