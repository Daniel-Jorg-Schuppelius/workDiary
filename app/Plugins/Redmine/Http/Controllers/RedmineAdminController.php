<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Http\Controllers;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Plugins\Redmine\Api\RedmineClientFactory;
use App\Plugins\Redmine\Models\{RedmineConnection, RedmineProjectLink};
use App\Plugins\Redmine\{RedmineConfig, RedminePlugin};
use App\Plugins\Redmine\Services\{RedmineImportService, RedmineTimeImportService};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Redmine-Verbindungsseite (MVP-1321/1324): wie Gitea/Forgejo, dazu je
 * Zuordnung der Schalter „Zeiten übernehmen“ und die ausdrückliche Zuordnung
 * der Redmine-Benutzer, deren Zeiteinträge warten.
 *
 * @extends TokenTaskImportAdminController<RedmineProjectLink, RedmineConnection>
 */
class RedmineAdminController extends TokenTaskImportAdminController {
    // Erneut eingebunden: organization() ist privat und aus der Basisklasse nicht sichtbar (Muster Etsy).
    use ResolvesPluginOrgContext;

    /** Zeitimport einer Zuordnung ein- oder ausschalten. */
    public function toggleTimes(string $link): RedirectResponse {
        $admin = $this->admin();
        $model = $this->findLink($link, $this->organization($admin));
        $model->forceFill(['is_time_import_enabled' => ! $model->is_time_import_enabled])->save();
        $model->audit(RedminePlugin::ID . '.time_import_' . ($model->is_time_import_enabled ? 'enabled' : 'disabled'), ['by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', (string) __($model->is_time_import_enabled ? 'redmine::redmine.flash.times_enabled' : 'redmine::redmine.flash.times_disabled'));
    }

    /** Redmine-Benutzer einem Benutzer der Organisation zuordnen; der nächste Lauf bucht seine wartenden Zeiten. */
    public function mapUser(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $data = $request->validate([
            'redmine_user' => ['required', 'string', 'max:96', 'regex:/^[a-f0-9]{32}:\d+$/'],
            'user' => ['required', 'string', 'max:64'],
        ]);
        $user = $this->ownedModel(User::class, (string) $data['user'], $organization);
        $connection = $this->importService()->connection((int) $organization->id);
        if (! $user instanceof User || $connection === null || ! str_starts_with((string) $data['redmine_user'], (string) $connection->sourceId() . ':')) {
            return back()->withErrors(['user' => (string) __('redmine::redmine.flash.user_invalid')]);
        }

        // Ein Redmine-Benutzer zeigt auf genau einen Benutzer — eine frühere Zuordnung wird ersetzt.
        $this->userReferences($organization)->where('external_id', (string) $data['redmine_user'])->delete();
        ExternalReference::query()->updateOrCreate(
            ['plugin_id' => RedminePlugin::ID, 'external_type' => RedmineTimeImportService::EXT_TYPE_USER, 'referenceable_type' => $user->getMorphClass(), 'referenceable_id' => $user->id],
            ['organization_id' => $organization->id, 'external_id' => (string) $data['redmine_user'], 'synced_at' => now()],
        );
        $connection->audit(RedminePlugin::ID . '.user_mapped', ['redmine_user' => (string) $data['redmine_user'], 'user_id' => (int) $user->id]);

        return $this->backToOverview()->with('success', (string) __('redmine::redmine.flash.user_mapped'));
    }

    public function unmapUser(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $data = $request->validate(['redmine_user' => ['required', 'string', 'max:96']]);
        $this->userReferences($organization)->where('external_id', (string) $data['redmine_user'])->delete();

        return $this->backToOverview()->with('success', (string) __('redmine::redmine.flash.user_unmapped'));
    }

    protected function extraView(): ?string {
        return 'redmine::admin._times';
    }

    protected function extraViewData(Organization $organization): array {
        $model = $this->linkModel();
        $mapped = $this->userReferences($organization)->with('referenceable')->get();

        return [
            'timeLinks' => $model::query()->where('organization_id', $organization->id)->with('project:id,name')->orderBy('source_project_name')->get(),
            'pendingUsers' => app(RedmineTimeImportService::class)->pendingUsers($organization),
            'mappedUsers' => $mapped,
            'orgUsers' => User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name', 'email']),
            'timeWindowDays' => RedmineConfig::resolve((int) $organization->id)['time_window_days'],
        ];
    }

    protected function importService(): RedmineImportService {
        return app(RedmineImportService::class);
    }

    protected function linkModel(): string {
        return RedmineProjectLink::class;
    }

    protected function connectionModel(): string {
        return RedmineConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(RedmineClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) RedmineConfig::resolve($organizationId)['base_url'];
        $user = app(RedmineClientFactory::class)->for($organizationId)->currentUser();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl),
            'source_url' => $baseUrl,
            'account_id' => $user['id'],
            'account_name' => $user['name'],
        ];
    }

    protected function providerName(): string {
        return 'Redmine';
    }

    protected function sourceNoun(): string {
        return (string) __('redmine::redmine.source');
    }

    protected function pluginId(): string {
        return RedminePlugin::ID;
    }

    protected function isConfigured(): bool {
        return RedmineConfig::isConfigured();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ExternalReference> */
    private function userReferences(Organization $organization): \Illuminate\Database\Eloquent\Builder {
        return ExternalReference::query()->forPlugin($organization, RedminePlugin::ID, RedmineTimeImportService::EXT_TYPE_USER);
    }
}
