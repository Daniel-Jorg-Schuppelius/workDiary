<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOneNoteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Models\Platform\User;
use App\Models\Plugins\Msgraph\MsgraphOneNoteConnection;
use App\Plugins\Msgraph\Api\{MsgraphOneNoteClient, MsgraphOneNoteOAuth};
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Throwable;

/**
 * OAuth-Verbindungsflow der OneNote-Übernahme (Feature 155, MVP-815) — eigener
 * Grant (`Notes.Read`), verwaltet im Msgraph-Admin-Panel. Verbinden geht erst,
 * wenn die Organisation die Übernahme in den Plugin-Einstellungen einschaltet;
 * die Übernahme selbst startet im Einstieg „Wissen“.
 */
class MsgraphOneNoteController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    protected function oauth(): PluginOAuthGrant {
        return app(MsgraphOneNoteOAuth::class);
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return MsgraphOneNoteConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'msgraph-onenote-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.msgraph.index';
    }

    protected function pluginKey(): string {
        return 'msgraph_onenote';
    }

    protected function connectedStatus(): string {
        return MsgraphOneNoteConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return MsgraphOneNoteConnection::STATUS_DISCONNECTED;
    }

    /** Ohne eingeschaltete Übernahme kein zusätzlicher Berechtigungsbereich. */
    public function startOAuth(Request $request): RedirectResponse {
        $organization = $this->organization($this->admin());
        if (! MsgraphConfig::oneNoteImportEnabled((int) $organization->id)) {
            return back()->with('error', $this->flashMessage('disabled'));
        }

        return parent::startOAuth($request);
    }

    /** Bestätigte Kontoidentität laden (Fehler unkritisch). */
    protected function afterConnected(Model $connection, User $admin): void {
        if (! $connection instanceof MsgraphOneNoteConnection) {
            return;
        }
        try {
            $connection->forceFill(['account_label' => (new MsgraphOneNoteClient($connection))->account()['label']])->save();
        } catch (Throwable) {
            // Anzeige-Komfort; die Verbindung bleibt nutzbar.
        }
    }
}
