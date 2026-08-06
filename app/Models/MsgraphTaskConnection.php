<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTaskConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Microsoft-To-Do-Verbindung einer Organisation (Feature 102, Schnitt E —
 * TaskSync): genau EINE OAuth-Verbindung je Org (sechster Grant,
 * `Tasks.ReadWrite`), Tokens at-rest verschlüsselt und nie serialisiert.
 * Synchronisiert werden nur ausdrücklich zugeordnete Listen
 * ({@see MsgraphTaskListLink}). Auto-Disable über {@see HasConnectionHealth}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $account_label
 * @property string $status
 * @property Carbon|null $last_sync_at
 */
class MsgraphTaskConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'msgraph_task_connections';

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
        'account_label',
        'status',
        'last_sync_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /** Betriebsbereit: verbunden, Token vorhanden und nicht auto-deaktiviert (MVP-178). */
    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE
            && trim((string) $this->access_token) !== ''
            && $this->disabled_at === null;
    }
}
