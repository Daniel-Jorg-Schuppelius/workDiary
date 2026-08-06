<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Models\{MsgraphMailConnection, User};
use App\Plugins\Msgraph\Api\{MsgraphMailClient, MsgraphMailOAuth};
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Throwable;

/**
 * OAuth-Verbindungsflow + Einstellungen des Graph-MAIL-VERSANDS (Feature 102).
 * Vierter Grant der Msgraph-App-Registrierung (`Mail.Send`), verwaltet im
 * Msgraph-Admin-Panel; Ablauf über die gemeinsame Basis
 * {@see ConnectionOAuthController} (state einmalig, org-/sitzungsgebunden,
 * PKCE). Tokens erscheinen nie in Logs, Fehlermeldungen oder Audit-Payloads.
 */
class MsgraphMailController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    protected function oauth(): PluginOAuthGrant {
        return app(MsgraphMailOAuth::class);
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return MsgraphMailConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'msgraph-mail-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.msgraph.index';
    }

    protected function pluginKey(): string {
        return 'msgraph_mail';
    }

    protected function connectedStatus(): string {
        return MsgraphMailConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return MsgraphMailConnection::STATUS_DISCONNECTED;
    }

    /** Bestätigte Kontoidentität laden (Fehler unkritisch — Health meldet API-Probleme). */
    protected function afterConnected(Model $connection, User $admin): void {
        if (! $connection instanceof MsgraphMailConnection) {
            return;
        }
        try {
            $connection->forceFill(['account_label' => (new MsgraphMailClient($connection))->account()['label']])->save();
        } catch (Throwable) {
            // Kontoidentität ist Anzeige-Komfort; die Verbindung bleibt nutzbar.
        }
    }

    /** Absender-/Ablage-Einstellungen (auditiert; from_address = Shared-Mailbox/Send-As). */
    public function storeSettings(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphMailConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof MsgraphMailConnection) {
            return back()->with('error', __('msgraph_mail.flash.no_connection'));
        }

        $data = $request->validate([
            'from_address' => ['nullable', 'email', 'max:190'],
            'save_to_sent_items' => ['nullable', 'boolean'],
        ]);

        $connection->forceFill([
            'from_address' => trim((string) ($data['from_address'] ?? '')) !== '' ? trim((string) $data['from_address']) : null,
            'save_to_sent_items' => (bool) ($data['save_to_sent_items'] ?? false),
        ])->save();
        $connection->audit('msgraph_mail.settings_saved', [
            'by_user_id' => (int) $admin->id,
            'from_address' => $connection->from_address ?? 'default',
            'save_to_sent_items' => $connection->save_to_sent_items,
        ]);

        return back()->with('success', __('msgraph_mail.flash.settings_saved'));
    }
}
