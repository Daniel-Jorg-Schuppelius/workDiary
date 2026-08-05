<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Microsoft-365-MAIL-Verbindung einer Organisation (Feature 102, Graph-Mail-
 * Versand): genau EINE OAuth-Verbindung je Org, Tokens at-rest verschlüsselt
 * (`encrypted`-Cast, APP_KEY) und nie serialisiert/auditiert (`$hidden`).
 * Der {@see \App\Plugins\Msgraph\Mail\MsgraphMailTransport} versendet über
 * `POST /me/sendMail` (delegated `Mail.Send`); `from_address` erlaubt einen
 * Shared-Mailbox-/Send-As-Absender (Exchange-Berechtigung vorausgesetzt).
 * Auto-Disable über {@see HasConnectionHealth}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $account_label
 * @property string|null $from_address
 * @property bool $save_to_sent_items
 * @property string $status
 * @property Carbon|null $last_sent_at
 */
class MsgraphMailConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'msgraph_mail_connections';

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
        'from_address',
        'save_to_sent_items',
        'status',
        'last_sent_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'save_to_sent_items' => 'boolean',
        'last_sent_at' => 'datetime',
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

    public function markSent(): void {
        $this->forceFill(['last_sent_at' => Carbon::now()])->save();
    }
}
