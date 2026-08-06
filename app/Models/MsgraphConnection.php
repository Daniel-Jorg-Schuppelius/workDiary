<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\Calendar\RemoteCalendarConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Microsoft-365-Kalender-Verbindung einer Organisation (MVP-328, Bauturbo A8):
 * genau EINE OAuth-Verbindung je Org, Tokens at-rest verschlüsselt
 * (`encrypted`-Cast, APP_KEY) und nie serialisiert/auditiert (`$hidden`).
 * WorkDiary publiziert Termine idempotent in den Ziel-Kalender
 * (`calendar_id`; leer = Standardkalender `/me/events`). Auto-Disable über
 * {@see HasConnectionHealth} (disabled_at ⇒ nicht mehr betriebsbereit).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $calendar_id
 * @property string|null $calendar_name
 * @property bool $teams_meetings
 * @property bool $two_way
 * @property string|null $calendar_delta_link
 * @property Carbon|null $last_imported_at
 * @property string $status
 * @property Carbon|null $last_published_at
 */
class MsgraphConnection extends Model implements RemoteCalendarConnection {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    /** Tabellenname explizit (Klassenname würde sonst zu `msgraph_connections` — zur Sicherheit fixiert). */
    protected $table = 'msgraph_connections';

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
        'calendar_id',
        'calendar_name',
        'teams_meetings',
        'two_way',
        'calendar_delta_link',
        'status',
        'last_published_at',
        'last_imported_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'teams_meetings' => 'boolean',
        'two_way' => 'boolean',
        'last_published_at' => 'datetime',
        'last_imported_at' => 'datetime',
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

    public function organizationId(): int {
        return (int) $this->organization_id;
    }

    public function markPublished(): void {
        $this->forceFill(['last_published_at' => Carbon::now()])->save();
    }
}
