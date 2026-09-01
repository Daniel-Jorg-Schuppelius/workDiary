<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : S3ServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\S3;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 123, MVP-726): registriert Config
 * und Routen des S3-Backupziels. Kein Gateway-Singleton — der Client wird je
 * Verbindung gebaut, weil Endpoint, Region und Zugangsdaten daran hängen.
 */
class S3ServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return S3Plugin::ID;
    }

    protected function registerPlugin(): void {}
}
