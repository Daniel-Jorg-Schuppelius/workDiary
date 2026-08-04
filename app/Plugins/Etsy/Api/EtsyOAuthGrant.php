<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyOAuthGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Api;

use App\Plugins\Etsy\EtsyConfig;
use App\Plugins\Support\{PluginOAuthGrant, PluginOrgContext};

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE-Pflicht) für Etsy (Feature 101,
 * MVP-494). Abweichung vom installationsweiten Standard: die Credentials
 * kommen JE ORGANISATION aus den Plugin-Settings (jede Org bringt ihre
 * eigene Seller-App mit — kein Commercial Access nötig), aufgelöst über den
 * Container-gebundenen Org-Kontext. Etsy ist ein Public Client: client_id =
 * Keystring, client_secret bleibt leer (das api-toolkit lässt den Parameter
 * beim Token-Tausch dann weg, W0-Preflight 2026-08-04); das Shared Secret
 * wird nur im `x-api-key`-Header verwendet ({@see EtsyClient}).
 */
class EtsyOAuthGrant extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        $config = EtsyConfig::resolve(PluginOrgContext::currentId());

        return [
            'client_id' => (string) ($config['keystring'] ?? ''),
            'client_secret' => '', // Public Client (PKCE) — Etsy kennt kein client_secret.
            'authorize_url' => $config['authorize_url'],
            'token_url' => $config['token_url'],
            'scopes' => $config['scopes'],
        ];
    }

    protected function callbackRouteName(): string {
        return 'admin.etsy.oauth.callback';
    }
}
