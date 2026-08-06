<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTasksOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für den TO-DO-SYNC
 * (Feature 102, Schnitt E). Sechster, getrennter Grant: eigener Redirect,
 * eigener Scope-Satz (`Tasks.ReadWrite` — delegated, nur die To-Do-Listen
 * des verbundenen Kontos).
 */
class MsgraphTasksOAuth extends MsgraphGrantBase {
    protected function callbackRouteName(): string {
        return 'admin.msgraph.tasks.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'tasks_scopes';
    }
}
