<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TokenTaskImportAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Http\Controllers\Controller;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\OAuthConnectionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Verbindungsseite der Aufgabenimporte für selbst gehostete Systeme mit
 * API-Token (Gitea/Forgejo, Redmine; Phase 158). Basis-URL, Token und das
 * Opt-in für interne Adressen stehen in den Plugin-Einstellungen; „Verbindung
 * prüfen“ ruft die Instanz an und hält Identität, Konto und Gesundheit in der
 * Verbindungszeile. Eine andere Instanz bei bestehenden Zuordnungen wird
 * abgewiesen, damit Referenzen nie neu gedeutet werden.
 *
 * @template TLink of Model&TaskImportLink
 * @template TConnection of Model&TaskImportConnection
 */
abstract class TokenTaskImportAdminController extends Controller {
    /** @use ManagesTaskImportLinks<TLink, TConnection> */
    use ManagesTaskImportLinks;
    use ResolvesPluginOrgContext;

    /** @return class-string<TConnection> */
    abstract protected function connectionModel(): string;

    /**
     * Instanz anrufen und ihre Identität liefern; wirft, wenn sie nicht
     * erreichbar ist oder den Zugang ablehnt.
     *
     * @return array{source_id: string, source_name: string, source_url: string, account_id: ?string, account_name: ?string}
     */
    abstract protected function verifyConnection(int $organizationId): array;

    protected function connectMode(): string {
        return 'token';
    }

    public function verify(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        if (! $this->isConfigured()) {
            return $this->backToOverview()->with('error', $this->flashMessage('not_configured'));
        }
        try {
            $identity = $this->verifyConnection((int) $organization->id);
        } catch (Throwable $e) {
            report($e);

            return $this->backToOverview()->with('error', $this->flashMessage('verify_failed', ['class' => class_basename($e)]));
        }

        $model = $this->connectionModel();
        $connection = $model::query()->firstOrNew(['organization_id' => $organization->id]);
        $previous = $connection->sourceId();
        $links = $this->linkModel();
        if ($previous !== null && $previous !== $identity['source_id']
            && $links::query()->where('organization_id', $organization->id)->exists()) {
            return $this->backToOverview()->with('error', $this->flashMessage('source_locked'));
        }

        $connection->forceFill($identity + [
            'status' => OAuthConnectionStatus::Active,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => CarbonImmutable::now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();
        $this->resumeLinksAfterReconnect((int) $organization->id);
        // @phpstan-ignore method.notFound (Auditable)
        $connection->audit($this->pluginId() . '.connected', ['source_id' => $identity['source_id'], 'by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', $this->flashMessage('verified', ['source_name' => $identity['source_name']]));
    }

    /** Trennen: der Abgleich ruht, Zuordnungen pausieren; Aufgaben, Referenzen und Zeiten bleiben. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $model = $this->connectionModel();
        $connection = $model::query()->where('organization_id', $organization->id)->first();
        if ($connection !== null) {
            $connection->forceFill([
                'status' => OAuthConnectionStatus::Disconnected,
                'disconnected_by' => $admin->id,
                'disconnected_at' => CarbonImmutable::now(),
            ])->save();
            // @phpstan-ignore method.notFound (Auditable)
            $connection->audit($this->pluginId() . '.disconnected', ['by_user_id' => (int) $admin->id]);
        }
        $this->pauseLinksOnDisconnect((int) $organization->id);

        return $this->backToOverview()->with('success', $this->flashMessage('disconnected_token'));
    }

    protected function backToOverview(): RedirectResponse {
        return redirect()->route($this->routePrefix() . '.index');
    }
}
