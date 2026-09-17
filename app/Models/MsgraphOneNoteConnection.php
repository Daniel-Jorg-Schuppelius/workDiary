<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOneNoteConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Integration\OAuthConnectionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * OneNote-Verbindung einer Organisation (Feature 155, MVP-815): genau eine
 * lesende OAuth-Verbindung je Org (`Notes.Read`), Tokens at-rest
 * verschlüsselt und nie serialisiert. Nur Übernahme auf Anstoß — kein
 * Rückschreiben, kein laufender Abgleich.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $account_label
 * @property OAuthConnectionStatus $status
 * @property Carbon|null $last_import_at
 */
class MsgraphOneNoteConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    /** Rohwerte für die Hooks des gemeinsamen OAuth-Controllers. */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'msgraph_onenote_connections';

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
        'last_import_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'status' => OAuthConnectionStatus::class,
        'token_expires_at' => 'datetime',
        'last_import_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function isActive(): bool {
        return $this->status === OAuthConnectionStatus::Active
            && trim((string) $this->access_token) !== ''
            && $this->disabled_at === null;
    }
}
