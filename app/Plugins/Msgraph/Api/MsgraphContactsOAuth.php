<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphContactsOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für den KONTAKT-PUSH
 * (Feature 102, Schnitt D). Fünfter, getrennter Grant: eigener Redirect,
 * eigener Scope-Satz (`Contacts.ReadWrite` — delegated, nur die Kontakte
 * des verbundenen Kontos).
 */
class MsgraphContactsOAuth extends MsgraphGrantBase {
    protected function callbackRouteName(): string {
        return 'admin.msgraph.contacts.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'contacts_scopes';
    }
}
