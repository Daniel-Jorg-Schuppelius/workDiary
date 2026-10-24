<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaOAuthGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Asana\AsanaConfig;
use App\Plugins\Support\PluginOAuthGrant;

/** Asana-OAuth-Grant (PKCE) der installationsweiten App, mit Widerrufs-Endpunkt; Test-Austauschpunkt als Singleton. */
class AsanaOAuthGrant extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return AsanaConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.asana.oauth.callback';
    }

    /** @param array<string, string|int|bool> $config */
    protected function buildGrant(array $config): OAuth2AuthorizationCodeGrant {
        $grant = parent::buildGrant($config);
        $grant->setRevocationUrl((string) $config['revoke_url']);

        return $grant;
    }
}
