<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Calendly-OAuth-Verbindung einer Organisation (Feature 095): genau EINE
 * Verbindung je Org, Tokens at-rest verschlüsselt (`encrypted`-Cast, APP_KEY)
 * und nie serialisiert/auditiert (`$hidden`). `calendly_user_uri`/
 * `calendly_organization_uri` sind die Calendly-URIs des verbundenen Nutzers
 * bzw. der Organisation (Scope-Ziel der Webhook-Subscription). Auto-Disable
 * über {@see HasConnectionHealth}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $calendly_user_uri
 * @property string|null $calendly_organization_uri
 * @property string $status
 * @property Carbon|null $last_synced_at
 * @property int|null $connected_by
 */
class CalendlyConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'calendly_connections';

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $fillable = [
        'organization_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'calendly_user_uri',
        'calendly_organization_uri',
        'status',
        'last_synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /** Betriebsbereit: verbunden, Token vorhanden und nicht auto-deaktiviert. */
    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE
            && trim((string) $this->access_token) !== ''
            && $this->disabled_at === null;
    }

    public function organizationId(): int {
        return (int) $this->organization_id;
    }
}
