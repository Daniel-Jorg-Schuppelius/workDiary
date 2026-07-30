<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Api;

use App\Plugins\GoogleDrive\GoogleDriveConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für Google Drive (Feature 080,
 * MVP-355). `access_type=offline` + `prompt=consent` sichern das
 * Refresh-Token (Zusatzparameter setzt der Intake-Flow).
 */
class GoogleDriveOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return GoogleDriveConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.cloud-intake.google.oauth.callback';
    }
}
