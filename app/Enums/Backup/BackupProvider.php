<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Backup;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Provider verschlüsselter Cloud-Backupziele (Feature 017, Phase 32).
 * Bewusst EIGENES Enum neben {@see \App\Enums\CloudIntake\CloudIntakeProvider}:
 * Backupziele sind eine eigene Produktgrenze (systemweit, eigene Scopes,
 * eigene Verbindungen) — die Plugin-IDs zeigen aber auf dieselben Adapter.
 */
enum BackupProvider: string implements HasLabel {
    use HasOptions;

    case Dropbox = 'dropbox';
    case Microsoft = 'microsoft';
    case Google = 'google';
    case Nextcloud = 'nextcloud';
    // Generisches WebDAV (Feature 123, MVP-612): eigener Server, EU-Hoster,
    // Synology, jeder Apache mit mod_dav.
    case Webdav = 'webdav';

    public function label(): string {
        return (string) __('enums.backup.provider.' . $this->value);
    }

    /** Plugin-ID des Adapters in der Plugin-Registry. */
    public function pluginId(): string {
        return match ($this) {
            self::Dropbox => 'dropbox',
            self::Microsoft => 'msgraph',
            self::Google => 'google-drive',
            self::Nextcloud => 'nextcloud',
            self::Webdav => 'webdav',
        };
    }
}
