<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Models\Platform\User;
use App\Plugins\Support\PluginApiException;
use App\Plugins\Todoist\Enums\TodoistConnectionStatus;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Throwable;

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
 * @property TodoistConnectionStatus $status
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
        'status' => TodoistConnectionStatus::class,
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
        return $this->status === TodoistConnectionStatus::Active && trim((string) $this->access_token) !== '';
    }

    /**
     * Ergebnis eines Abgleichs festhalten: Fehlerklasse in `last_error`, ein
     * abgelehntes Token (401) pausiert die Verbindung bis zum Neuverbinden.
     */
    public function recordSyncResult(?Throwable $error): void {
        if ($error === null) {
            if ($this->last_error !== null) {
                $this->forceFill(['last_error' => null])->save();
            }

            return;
        }

        $status = $error instanceof PluginApiException ? $error->status : 0;
        $this->forceFill([
            'last_error' => mb_substr(class_basename($error) . ($status > 0 ? ' (HTTP ' . $status . ')' : ''), 0, 191),
            'status' => $status === 401 ? TodoistConnectionStatus::Paused : $this->status,
        ])->save();
    }
}
