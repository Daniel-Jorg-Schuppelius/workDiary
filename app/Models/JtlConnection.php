<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Models\Concerns\HasPrivateNetworkOptIn;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * JTL-Wawi-Verbindung einer Organisation (Feature 078, MVP-317): genau eine
 * je Org, beide Betriebsarten (OnPremise-API-Key / Cloud-OAuth2). Alle
 * Secrets verschlüsselt at-rest und `$hidden` — sie erscheinen nie in
 * Audit-Payloads, Logs oder Supportexporten. `last_error` trägt nur die
 * gekürzte Fehlerklasse.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $mode
 * @property string|null $base_url
 * @property string $api_version
 * @property bool $allow_private_network
 * @property string|null $tenant_id
 * @property string|null $company_id
 * @property string|null $app_id
 * @property string|null $challenge_code
 * @property string|null $registration_id
 * @property string|null $registration_status
 * @property string|null $api_key
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $access_token
 * @property Carbon|null $token_expires_at
 * @property array<int, string>|null $granted_scopes
 * @property string $status
 * @property string|null $blocked_reason
 * @property Carbon|null $stock_checkpoint_at
 * @property Carbon|null $article_checkpoint_at
 * @property Carbon|null $last_sync_at
 * @property array<string, int>|null $last_sync_counters
 * @property string|null $last_error
 * @property string|null $detected_version
 * @property array<int, string>|null $contract_notes
 */
class JtlConnection extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasPrivateNetworkOptIn;

    public const MODE_ON_PREMISE = 'on_premise';

    public const MODE_CLOUD = 'cloud';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REGISTRATION = 'pending_registration';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const REGISTRATION_PENDING = 'pending';

    public const REGISTRATION_REJECTED = 'rejected';

    public const REGISTRATION_ACCEPTED = 'accepted';

    protected $table = 'jtl_connections';

    /**
     * Secrets erscheinen nie im Array-/JSON-Output — und damit auch nie in
     * Audit-Payloads ({@see Auditable::getAuditAttributes()} schließt
     * `$hidden` mit aus).
     *
     * @var list<string>
     */
    protected $hidden = ['api_key', 'client_id', 'client_secret', 'access_token', 'challenge_code'];

    protected $fillable = [
        'organization_id',
        'mode',
        'base_url',
        'api_version',
        'allow_private_network',
        'tenant_id',
        'company_id',
        'app_id',
        'challenge_code',
        'registration_id',
        'registration_status',
        'api_key',
        'client_id',
        'client_secret',
        'access_token',
        'token_expires_at',
        'granted_scopes',
        'status',
        'blocked_reason',
        'stock_checkpoint_at',
        'article_checkpoint_at',
        'last_sync_at',
        'last_sync_counters',
        'last_error',
        'detected_version',
        'contract_notes',
        'connected_by',
        'connected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'allow_private_network' => 'boolean',
        'challenge_code' => 'encrypted',
        'api_key' => 'encrypted',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'granted_scopes' => 'array',
        'stock_checkpoint_at' => 'datetime',
        'article_checkpoint_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_sync_counters' => 'array',
        'contract_notes' => 'array',
        'connected_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isOnPremise(): bool {
        return $this->mode === self::MODE_ON_PREMISE;
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE && $this->hasCredentials();
    }

    public function hasCredentials(): bool {
        return $this->isOnPremise()
            ? trim((string) $this->api_key) !== ''
            : (trim((string) $this->client_id) !== '' && trim((string) $this->client_secret) !== '');
    }

    /** Kurzlebiger Cloud-Token noch gültig (mit Sicherheitsfenster)? */
    public function hasValidCloudToken(): bool {
        return trim((string) $this->access_token) !== ''
            && $this->token_expires_at !== null
            && $this->token_expires_at->isAfter(now()->addMinutes(2));
    }

    /**
     * Vermerkt eine erkannte API-Vertragsabweichung (Abweichungsregister,
     * MVP-316) — idempotent, damit Polling-Läufe keine Duplikate anhäufen.
     */
    public function noteContractDeviation(string $note): void {
        $notes = $this->contract_notes ?? [];
        if (! in_array($note, $notes, true)) {
            $notes[] = $note;
            $this->forceFill(['contract_notes' => $notes])->save();
        }
    }
}
