<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphGrantBase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * Gemeinsame Basis der vier Msgraph-Grants (Feature 102 Variante B):
 * Organisationen können eine EIGENE App-Registrierung hinterlegen
 * (Plugin-Settings-Overlay, {@see MsgraphConfig::resolve()}); ohne Overlay
 * gilt die Instanz-App aus der ENV.
 *
 * `grant()`/`scopes()` lösen die Organisation aus dem Request-Kontext auf,
 * `grantFor()`/`scopesFor()` (Basisklasse) nehmen sie explizit.
 */
abstract class MsgraphGrantBase extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return MsgraphConfig::resolve();
    }

    /** @return array<string, string|int|bool> */
    protected function configFor(?int $organizationId): array {
        return MsgraphConfig::resolve($organizationId);
    }
}
