<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Api;

use App\Plugins\DatevOnline\DatevOnlineConfig;
use App\Plugins\Support\PluginOAuthGrant;

/** „Login mit DATEV“: OpenID Connect Authorization Code mit PKCE (S256). */
class DatevOnlineOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return DatevOnlineConfig::resolve();
    }

    /** @return array<string, string|int|bool> */
    protected function configFor(?int $organizationId): array {
        return DatevOnlineConfig::resolve($organizationId);
    }

    protected function callbackRouteName(): string {
        return 'admin.datev-online.oauth.callback';
    }
}
