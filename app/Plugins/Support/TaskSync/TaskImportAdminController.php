<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Models\Platform\User;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, OAuthConnectionStatus, SerializedBearerAuthentication};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Throwable;

/**
 * Verbindungsseite der Aufgabenimporte mit OAuth (Jira, Asana; MVP-1309/1311):
 * Anmeldung über {@see ConnectionOAuthController}, danach Quelle wählen; der
 * Rest kommt aus {@see ManagesTaskImportLinks}.
 *
 * @template TLink of Model&TaskImportLink
 * @template TConnection of Model&TaskImportConnection
 */
abstract class TaskImportAdminController extends ConnectionOAuthController {
    /** @use ManagesTaskImportLinks<TLink, TConnection> */
    use ManagesTaskImportLinks;
    use ResolvesPluginOrgContext;

    /**
     * Nach dem Consent erreichbare Quellen und Konto.
     *
     * @return array{sources: list<array{id: string, name: string, url: ?string}>, account_id: ?string, account_name: ?string}
     */
    abstract protected function consentContext(int $organizationId): array;

    protected function connectMode(): string {
        return 'oauth';
    }

    /** Quelle (Site/Workspace) aus den nach dem Consent erreichbaren wählen. */
    public function chooseSource(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $data = $request->validate(['source_id' => ['required', 'string', 'max:64']]);
        $connection = $this->importService()->connection((int) $organization->id);
        if ($connection === null || $connection->getAttribute('status') !== OAuthConnectionStatus::Active) {
            return $this->backToOverview()->with('error', $this->flashMessage('not_connected'));
        }

        $source = collect($connection->availableSources())->firstWhere('id', (string) $data['source_id']);
        if ($source === null) {
            return $this->backToOverview()->with('error', $this->flashMessage('source_unknown'));
        }
        // Zuordnungen und Referenzen gehören zur bisherigen Quelle — nie neu deuten.
        $model = $this->linkModel();
        if ($connection->sourceId() !== null && $connection->sourceId() !== $source['id']
            && $model::query()->where('organization_id', $organization->id)->exists()) {
            return $this->backToOverview()->with('error', $this->flashMessage('source_locked'));
        }

        $connection->forceFill(['source_id' => $source['id'], 'source_name' => $source['name'], 'source_url' => $source['url']])->save();
        // @phpstan-ignore method.notFound (Auditable)
        $connection->audit($this->pluginKey() . '.source_chosen', ['source_id' => $source['id'], 'by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', $this->flashMessage('source_chosen', ['source_name' => $source['name']]));
    }

    /**
     * Trennen unter der Refresh-Sperre (kein verspäteter Refresh schreibt Tokens
     * zurück); Zuordnungen pausieren, Aufgaben und Referenzen bleiben.
     */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->importService()->connection((int) $organization->id);
        $response = $connection !== null
            ? SerializedBearerAuthentication::lock($connection)->block(30, fn (): RedirectResponse => parent::disconnect())
            : parent::disconnect();

        $this->pauseLinksOnDisconnect((int) $organization->id);

        return $response;
    }

    // ── OAuth-Haken ─────────────────────────────────────────────────────

    protected function overviewRouteName(): string {
        return $this->routePrefix() . '.index';
    }

    protected function pluginKey(): string {
        return $this->pluginId();
    }

    protected function stateCachePrefix(): string {
        return $this->pluginId() . '-oauth-state';
    }

    protected function connectedStatus(): string {
        return OAuthConnectionStatus::Active->value;
    }

    protected function disconnectedStatus(): string {
        return OAuthConnectionStatus::Disconnected->value;
    }

    /**
     * Quellen und Konto nach dem Consent laden. Bleibt die gewählte Quelle
     * erreichbar, laufen die beim Trennen pausierten Zuordnungen weiter.
     */
    protected function afterConnected(Model $connection, User $admin): void {
        try {
            $context = $this->consentContext((int) $connection->getAttribute('organization_id'));
        } catch (Throwable $e) {
            report($e);

            return;
        }
        $connection->forceFill([
            'available_sources' => $context['sources'],
            'account_id' => $context['account_id'],
            'account_name' => $context['account_name'],
        ])->save();

        $current = $connection instanceof TaskImportConnection ? $connection->sourceId() : null;
        if ($current !== null && in_array($current, array_column($context['sources'], 'id'), true)) {
            $this->resumeLinksAfterReconnect((int) $connection->getAttribute('organization_id'));
        }
    }
}
