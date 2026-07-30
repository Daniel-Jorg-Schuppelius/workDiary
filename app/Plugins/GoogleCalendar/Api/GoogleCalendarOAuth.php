<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar\Api;

use App\Plugins\GoogleCalendar\GoogleCalendarConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für Google (MVP-328, Bauturbo A8).
 * Offline-Access (`access_type=offline` + `prompt=consent`) stellt das
 * Refresh-Token sicher (Zusatzparameter setzt der Admin-Flow).
 */
class GoogleCalendarOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return GoogleCalendarConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.google-calendar.oauth.callback';
    }
}
