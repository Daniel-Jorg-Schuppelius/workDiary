<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistConnection.php
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
 * Todoist-OAuth-Verbindung einer Organisation (Feature 055, MVP-111): genau
 * eine je Org, Tokens verschlüsselt at-rest. `last_error` trägt nur die
 * gekürzte Fehlerklasse — Tokens/Payloads erscheinen nie in Logs, Audits
 * oder Supportexporten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $todoist_user_id
 * @property string|null $todoist_user_email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string $status
 * @property bool $webhook_capable
 * @property string|null $sync_cursor
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_full_sync_at
 * @property string|null $last_error
 */
class TodoistConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * Tokens (und der interne Cursor) erscheinen nie im Array-/JSON-Output —
     * und damit auch nie in Audit-Payloads ({@see Auditable::getAuditAttributes()}
     * schließt `$hidden` mit aus).
     *
     * @var list<string>
     */
    protected $hidden = ['access_token', 'refresh_token', 'sync_cursor'];

    protected $fillable = [
        'organization_id',
        'todoist_user_id',
        'todoist_user_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'webhook_capable',
        'sync_cursor',
        'last_sync_at',
        'last_full_sync_at',
        'last_error',
        'connected_by',
        'connected_at',
        'disconnected_by',
        'disconnected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'webhook_capable' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_full_sync_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE && trim((string) $this->access_token) !== '';
    }
}
