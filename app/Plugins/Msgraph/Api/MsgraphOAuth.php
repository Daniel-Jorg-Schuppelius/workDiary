<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) gegen die Microsoft Identity
 * Platform (MVP-328, Bauturbo A8); authorize_url/token_url kommen aus
 * MsgraphConfig bereits mit eingesetztem Tenant (Default 'common');
 * per-Org-App-Registrierungen über {@see MsgraphGrantBase} (Variante B).
 */
class MsgraphOAuth extends MsgraphGrantBase {
    protected function callbackRouteName(): string {
        return 'admin.msgraph.oauth.callback';
    }
}
