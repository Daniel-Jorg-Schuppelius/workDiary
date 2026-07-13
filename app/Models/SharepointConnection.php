<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\Mirror\{MirrorConnection, MirrorsDocumentFolders};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SharePoint-Ablage einer Organisation (MVP-330, Bauturbo A10): genau EINE
 * OAuth-Verbindung je Org, Tokens at-rest verschlüsselt (`encrypted`-Cast,
 * APP_KEY) und nie serialisiert/auditiert (`$hidden`). WorkDiary spiegelt
 * freigegebene Dokumente über Microsoft Graph in die gewählte
 * Dokumentbibliothek (`drive_id` einer Site) — WorkDiary bleibt führend,
 * kein Rückkanal. Ordner-/Quellen-Logik identisch zur WebDAV-Ablage
 * ({@see MirrorsDocumentFolders}); Auto-Disable über
 * {@see HasConnectionHealth} (disabled_at ⇒ nicht mehr betriebsbereit).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property string|null $site_id
 * @property string|null $site_name
 * @property string|null $drive_id
 * @property string|null $drive_name
 * @property string $default_folder
 * @property array<string, string>|null $folder_map
 * @property array<int, string>|null $sources
 * @property bool $active
 * @property string $status
 * @property Carbon|null $last_mirrored_at
 */
class SharepointConnection extends Model implements MirrorConnection {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;
    use MirrorsDocumentFolders;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    /** Tabellenname explizit (defensiv, konsistent zur Migration). */
    protected $table = 'sharepoint_connections';

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
        'site_id',
        'site_name',
        'drive_id',
        'drive_name',
        'default_folder',
        'folder_map',
        'sources',
        'active',
        'status',
        'last_mirrored_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'folder_map' => 'array',
        'sources' => 'array',
        'active' => 'boolean',
        'last_mirrored_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /**
     * Betriebsbereit: verbunden (Token), Ziel-Bibliothek gewählt, aktiv
     * geschaltet und nicht auto-deaktiviert (MVP-178).
     */
    public function isActive(): bool {
        return $this->active
            && $this->status === self::STATUS_ACTIVE
            && trim((string) $this->access_token) !== ''
            && trim((string) $this->drive_id) !== ''
            && $this->disabled_at === null;
    }
}
