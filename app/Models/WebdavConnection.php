<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\Mirror\{MirrorConnection, MirrorsDocumentFolders};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * WebDAV-Ablage einer Organisation (Feature 058, MVP-127). Das App-Passwort ist
 * at-rest verschlüsselt (`encrypted`-Cast, APP_KEY) und nie serialisiert/
 * auditiert (`$hidden`). `folder_map` ordnet Dokumenttypen Zielordnern zu; ohne
 * Treffer greift `default_folder`. WorkDiary spiegelt freigegebene Dokumente in
 * die Collection unter `base_url` — WorkDiary bleibt führend, kein Rückkanal.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $base_url
 * @property string $username
 * @property string $app_password
 * @property string $default_folder
 * @property array<string, string>|null $folder_map
 * @property array<int, string>|null $sources
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_mirrored_at
 */
class WebdavConnection extends Model implements MirrorConnection {
    use Auditable;

    use BelongsToOrganization;
    use HasConnectionHealth;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use MirrorsDocumentFolders;

    /** Tabellenname explizit (defensiv, konsistent zur Migration). */
    protected $table = 'webdav_connections';

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'app_password',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'base_url',
        'username',
        'app_password',
        'default_folder',
        'folder_map',
        'sources',
        'active',
        'last_mirrored_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'app_password' => 'encrypted',
        'folder_map' => 'array',
        'sources' => 'array',
        'active' => 'boolean',
        'last_mirrored_at' => 'datetime',
    ];

    /** Betriebsbereit: aktiv geschaltet und vollständig konfiguriert. */
    public function isActive(): bool {
        return $this->active && $this->base_url !== '' && $this->username !== '' && $this->app_password !== '';
    }

    /** Vollständige URL eines Objekts (Collection-Root + relativer Pfad). */
    public function objectUrl(string $relativePath): string {
        return rtrim($this->base_url, '/') . '/' . ltrim($relativePath, '/');
    }
}
