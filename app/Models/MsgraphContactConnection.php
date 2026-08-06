<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphContactConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Microsoft-365-KONTAKT-Verbindung einer Organisation (Feature 102,
 * Schnitt D — ContactSync): genau EINE OAuth-Verbindung je Org, Tokens
 * at-rest verschlüsselt (`encrypted`-Cast) und nie serialisiert/auditiert.
 * Push-only-Pilot: {@see \App\Plugins\Msgraph\MsgraphPlugin::pushContact()}
 * schreibt WorkDiary-Kunden idempotent in die Outlook-Kontakte des
 * verbundenen Kontos. Auto-Disable über {@see HasConnectionHealth}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $account_label
 * @property string $status
 * @property Carbon|null $last_pushed_at
 */
class MsgraphContactConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'msgraph_contact_connections';

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
        'last_pushed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_pushed_at' => 'datetime',
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

    public function markPushed(): void {
        $this->forceFill(['last_pushed_at' => Carbon::now()])->save();
    }
}
