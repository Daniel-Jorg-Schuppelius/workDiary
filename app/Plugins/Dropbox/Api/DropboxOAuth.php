<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Api;

use App\Plugins\Dropbox\DropboxConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für Dropbox (Feature 080,
 * MVP-353). `token_access_type=offline` erzwingt kurzlebige Access- plus
 * Refresh-Tokens (Zusatzparameter setzt der Intake-Flow).
 */
class DropboxOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return DropboxConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.cloud-intake.dropbox.oauth.callback';
    }
}
