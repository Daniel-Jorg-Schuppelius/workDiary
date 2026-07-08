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

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
class WebdavConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

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

    /** Spiegelbare Quellen (Rang 19); null/leer = nur DMS-Dokumente (rückwärtskompatibel). */
    public const SOURCES = ['document', 'invoice_pdf', 'protocol_pdf'];

    /** Betriebsbereit: aktiv geschaltet und vollständig konfiguriert. */
    public function isActive(): bool {
        return $this->active && $this->base_url !== '' && $this->username !== '' && $this->app_password !== '';
    }

    /** Spiegelt diese Anbindung die angegebene Quelle? Ohne Auswahl nur `document`. */
    public function mirrorsSource(string $source): bool {
        $sources = $this->sources;
        if (! is_array($sources) || $sources === []) {
            return $source === 'document';
        }

        return in_array($source, $sources, true);
    }

    /** Zielordner (relativ zur base_url) für einen Dokumenttyp; sonst der Standardordner. */
    public function folderFor(string $documentType): string {
        $map = $this->folder_map ?? [];
        $folder = $map[$documentType] ?? $this->default_folder;

        return trim((string) $folder, '/');
    }

    /** Vollständige URL eines Objekts (Collection-Root + relativer Pfad). */
    public function objectUrl(string $relativePath): string {
        return rtrim($this->base_url, '/') . '/' . ltrim($relativePath, '/');
    }

    /** Relativer Zielpfad eines Dokuments (Ordner nach Typ + stabiler Dateiname). */
    public function relativePathFor(string $documentType, int $documentId, string $originalName): string {
        $ext = '';
        if (str_contains($originalName, '.')) {
            $candidate = strtolower((string) Str::of($originalName)->afterLast('.'));
            if (preg_match('/^[a-z0-9]{1,8}$/', $candidate) === 1) {
                $ext = '.' . $candidate;
            }
        }
        $folder = $this->folderFor($documentType);
        $file = 'document-' . $documentId . $ext;

        return $folder !== '' ? $folder . '/' . $file : $file;
    }
}
