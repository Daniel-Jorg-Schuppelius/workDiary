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

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * Gemeinsame Basis der vier Msgraph-Grants (Feature 102 Variante B):
 * Organisationen können eine EIGENE App-Registrierung hinterlegen
 * (Plugin-Settings-Overlay, {@see MsgraphConfig::resolve()}); ohne Overlay
 * gilt die Instanz-App aus der ENV.
 *
 * - `grant()`/`scopes()` (Basisklasse) lösen die Organisation aus dem
 *   Request-Kontext auf — der OAuth-Verbindungsflow der Admin-Panels.
 * - `grantFor()`/`scopesFor()` nehmen die Organisation EXPLIZIT — für
 *   Token-Refresh im Queue-/Konsolen-Kontext, wo kein Org-Kontext gebunden
 *   ist, die Verbindung ihre Organisation aber kennt.
 */
abstract class MsgraphGrantBase extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return MsgraphConfig::resolve();
    }

    public function grantFor(?int $organizationId): OAuth2AuthorizationCodeGrant {
        return $this->buildGrant(MsgraphConfig::resolve($organizationId));
    }

    /** @return list<string> */
    public function scopesFor(?int $organizationId): array {
        $config = MsgraphConfig::resolve($organizationId);

        return array_values(array_filter(explode(' ', (string) ($config[$this->scopesKey()] ?? ''))));
    }
}
