<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für den Graph-MAIL-VERSAND
 * (Feature 102). Vierter, getrennter Grant neben Kalender/Intake/Backup:
 * eigener Redirect, eigener Scope-Satz (`Mail.Send` — bewusst delegated;
 * Application-`Mail.Send` wäre tenantweit und braucht RBAC-Eingrenzung).
 */
class MsgraphMailOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return MsgraphConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.msgraph.mail.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'mail_scopes';
    }
}
