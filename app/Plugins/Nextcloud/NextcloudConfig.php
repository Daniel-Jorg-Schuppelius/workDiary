<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud;

/**
 * Aufgelöste Installations-Konfiguration des Nextcloud-Plugins (Muster
 * {@see \App\Plugins\Dropbox\DropboxConfig}). Im Plugin-Kontext ist config()
 * bereits unter `plugins.nextcloud` gemergt.
 */
class NextcloudConfig {
    /** @return array{enabled: bool, timeout: int, scan_folder_budget: int, max_reconcile_files: int, chunk_size: int, allow_private_targets: bool} */
    public static function resolve(): array {
        /** @var array{enabled?: bool, timeout?: int, scan_folder_budget?: int, max_reconcile_files?: int, chunk_size?: int, allow_private_targets?: bool} $config */
        $config = (array) config('plugins.nextcloud', []);

        return $config + [
            'enabled' => false,
            'timeout' => 30,
            'scan_folder_budget' => 50,
            'max_reconcile_files' => 5_000,
            'chunk_size' => 10_485_760,
            'allow_private_targets' => false,
        ];
    }

    public static function allowPrivateTargets(): bool {
        return (bool) self::resolve()['allow_private_targets'];
    }
}
