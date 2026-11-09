<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyCapabilitySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Services;

use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Support\Contracts\PluginCapabilitySource;

/** Zahlungsabgleich als Anzeige in der Plugin-Übersicht. */
final class FireflyCapabilitySource implements PluginCapabilitySource {
    public function labelFor(string $pluginId): ?string {
        return $pluginId === FireflyPlugin::ID ? (string) __('firefly::firefly.plugin.capability') : null;
    }
}
