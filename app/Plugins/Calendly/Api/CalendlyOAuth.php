<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Api;

use App\Plugins\Calendly\CalendlyConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für Calendly (Feature 095).
 * Die Scopes kommen aus `plugins.calendly.scopes` (ENV) und müssen ggf.
 * `offline_access` enthalten, damit Calendly ein Refresh-Token liefert.
 */
class CalendlyOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return CalendlyConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.calendly.oauth.callback';
    }
}
