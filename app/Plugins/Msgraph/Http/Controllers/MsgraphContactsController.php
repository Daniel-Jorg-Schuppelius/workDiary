<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphContactsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Models\{Customer, MsgraphContactConnection, User};
use App\Plugins\Msgraph\Api\{MsgraphContactsClient, MsgraphContactsOAuth};
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\PluginManager;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Gate, Log};
use Throwable;

/**
 * OAuth-Verbindungsflow des KONTAKT-PUSHS (Feature 102, Schnitt D) — fünfter
 * Grant (`Contacts.ReadWrite`), verwaltet im Msgraph-Admin-Panel — plus die
 * Push-Aktion aus der Kundenakte (Slot-Button, Lexoffice-Muster). Ablauf über
 * die gemeinsame Basis {@see ConnectionOAuthController} (state einmalig,
 * org-/sitzungsgebunden, PKCE).
 */
class MsgraphContactsController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    protected function oauth(): PluginOAuthGrant {
        return app(MsgraphContactsOAuth::class);
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return MsgraphContactConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'msgraph-contacts-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.msgraph.index';
    }

    protected function pluginKey(): string {
        return 'msgraph_contacts';
    }

    protected function connectedStatus(): string {
        return MsgraphContactConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return MsgraphContactConnection::STATUS_DISCONNECTED;
    }

    /** Bestätigte Kontoidentität laden (Fehler unkritisch — Health meldet API-Probleme). */
    protected function afterConnected(Model $connection, User $admin): void {
        if (! $connection instanceof MsgraphContactConnection) {
            return;
        }
        try {
            $connection->forceFill(['account_label' => (new MsgraphContactsClient($connection))->account()['label']])->save();
        } catch (Throwable) {
            // Anzeige-Komfort; die Verbindung bleibt nutzbar.
        }
    }

    /** Kunde → Outlook-Kontakt (idempotent; Slot-Button der Kundenakte). */
    public function push(Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $plugin = app(PluginManager::class)->find(MsgraphPlugin::ID);
        if (! $plugin instanceof MsgraphPlugin || ! $plugin->isEnabled()) {
            return back()->with('error', __('msgraph_contacts.flash.plugin_disabled'));
        }

        try {
            $externalId = $plugin->pushContact($customer);

            return back()->with('success', __('msgraph_contacts.flash.pushed', ['id' => $externalId]));
        } catch (Throwable $e) {
            // Nur Klasse/Kunde loggen — nie Payload/Token.
            Log::error('msgraph contact push failed', ['customer' => $customer->id, 'class' => class_basename($e)]);

            return back()->with('error', __('msgraph_contacts.flash.push_failed', ['class' => class_basename($e)]));
        }
    }
}
