<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint\Api;

use App\Plugins\Sharepoint\SharepointConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) gegen die Microsoft Identity
 * Platform für die SharePoint-Ablage (MVP-330, Bauturbo A10); Fallback auf
 * die MSGRAPH_*-Werte, Tenant-Default 'common' (via SharepointConfig).
 */
class SharepointOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return SharepointConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.sharepoint.oauth.callback';
    }
}
