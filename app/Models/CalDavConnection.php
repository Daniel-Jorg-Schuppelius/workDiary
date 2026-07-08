<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * CalDAV-Anbindung einer Organisation (Feature 058, MVP-126). Das App-Passwort
 * ist at-rest verschlüsselt (`encrypted`-Cast, APP_KEY) und nie serialisiert/
 * auditiert (`$hidden`). WorkDiary publiziert Termine in die Collection unter
 * `calendar_path` (relativ zur `base_url`).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $base_url
 * @property string $username
 * @property string $app_password
 * @property string $calendar_path
 * @property array<int, string>|null $scopes
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_published_at
 */
class CalDavConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** Tabellenname explizit (Klassenname würde sonst zu `cal_dav_connections`). */
    protected $table = 'caldav_connections';

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
        'calendar_path',
        'scopes',
        'active',
        'last_published_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'app_password' => 'encrypted',
        'scopes' => 'array',
        'active' => 'boolean',
        'last_published_at' => 'datetime',
    ];

    /** Publish-Scopes dieser Anbindung; null/leer = nur Termine (rückwärtskompatibel). */
    public const SCOPES = ['events', 'schedule'];

    /** Betriebsbereit: aktiv geschaltet und vollständig konfiguriert. */
    public function isActive(): bool {
        return $this->active && $this->base_url !== '' && $this->username !== '' && $this->app_password !== '';
    }

    /**
     * Publiziert diese Anbindung den angegebenen Scope (`events`|`schedule`)?
     * Ohne gesetzte Scopes werden – wie vor Rang 17 – nur Termine publiziert.
     */
    public function publishesScope(string $scope): bool {
        $scopes = $this->scopes;
        if (! is_array($scopes) || $scopes === []) {
            return $scope === 'events';
        }

        return in_array($scope, $scopes, true);
    }

    /** Vollständige URL des Kalenderobjekts (Collection + Objektname). */
    public function objectUrl(string $objectName): string {
        return rtrim($this->base_url, '/') . '/' . trim($this->calendar_path, '/') . '/' . ltrim($objectName, '/');
    }
}
