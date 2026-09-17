<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOneNoteOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für die OneNote-Übernahme
 * (Feature 155, MVP-815). Eigener Grant mit eigenem, nur lesendem Scope-Satz
 * (`Notes.Read` — delegated, nur die Notizbücher des verbundenen Kontos).
 */
class MsgraphOneNoteOAuth extends MsgraphGrantBase {
    protected function callbackRouteName(): string {
        return 'admin.msgraph.onenote.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'onenote_scopes';
    }
}
