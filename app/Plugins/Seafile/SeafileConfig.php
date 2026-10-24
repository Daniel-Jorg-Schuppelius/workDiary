<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile;

use App\Plugins\Support\PluginSettingsResolver;

/** Seafile-Zugang des Dokumenteingangs je Organisation (Plugin-Einstellungen) und Betreiberwerte. */
final class SeafileConfig {
    /** @return array{base_url: ?string, api_token: ?string, scan_folder_budget: int, max_reconcile_files: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(SeafilePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'api_token' => $r->string('api_token', trim: true),
            'scan_folder_budget' => max(1, (int) config('plugins.seafile.scan_folder_budget', 50)),
            'max_reconcile_files' => max(0, (int) config('plugins.seafile.max_reconcile_files', 5000)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_token'] !== null;
    }

    public static function allowPrivateBackupTargets(): bool {
        return (bool) config('plugins.seafile.allow_private_backup_targets', false);
    }
}
