<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevenIoServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevenIo;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 147): hängt `config.php` unter
 * `plugins.sevenio` ein. Mehr braucht das Gateway nicht — es hat weder
 * Routen noch Views; der Versand läuft über den SMS-Kanal des Kerns.
 */
class SevenIoServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SevenIoPlugin::ID;
    }
}
