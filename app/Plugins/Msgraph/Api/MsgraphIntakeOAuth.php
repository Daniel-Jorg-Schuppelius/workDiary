<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für den LESENDEN
 * Cloud-Dokumenteingang über Microsoft Graph (Feature 080, MVP-354).
 * Nutzt dieselbe App-Registrierung wie die Kalender-Verbindung
 * (Feature 058), aber eigene Intake-Scopes und einen eigenen Callback —
 * das Aktivieren eines Imports erteilt nie Kalender-/Schreibrechte.
 */
class MsgraphIntakeOAuth extends MsgraphGrantBase {
    protected function callbackRouteName(): string {
        return 'admin.cloud-intake.microsoft.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'intake_scopes';
    }
}
