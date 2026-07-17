<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabIssueImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Services;

use App\Models\Organization;
use App\Plugins\Gitlab\Api\GitlabClient;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Support\GitIssueImport\AbstractGitIssueImporter;

/**
 * Import von GitLab-Issues als WorkDiary-Aufgaben (Feature 060, MVP-129,
 * Bauturbo A6). GitLab bleibt führend; hier entstehen nur Aufgaben für
 * Zeiterfassung/Abrechnung. **Idempotent** über ExternalReference
 * (Plugin `gitlab`, Typ `issue`, Schlüssel `project_id#iid`): ein Replay legt
 * keine Dubletten an. Skeleton in der Basis (C8); hier nur GitLab-Spezifika:
 *
 * - Identität: `iid` ist NUR projektbezogen eindeutig — der Schlüssel setzt
 *   sich deshalb aus `project_id` + `iid` zusammen; die globale `id` wird
 *   bewusst nie verwendet (Recherche 2026-07).
 * - Aufholpunkt: `updated_after` = größtes gesehenes `updated_at`
 *   (serverseitige Uhr), fortgeschrieben in plugin_settings.
 */
class GitlabIssueImporter extends AbstractGitIssueImporter {
    /** Settings-Schlüssel des Polling-Aufholpunkts (GitLab `updated_after`). */
    public const CHECKPOINT_KEY = 'updated_after_checkpoint';

    private string $projectId = '';

    /**
     * @param  array{api_token: ?string, project_id: ?string, webhook_token: ?string, default_project: ?string, base_url: string, allow_private_network: bool, enabled: bool}|null  $config
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    public function import(Organization $organization, GitlabClient $client, ?array $config = null): array {
        $config ??= GitlabConfig::resolve((int) $organization->id);
        $this->projectId = (string) $config['project_id'];

        return $this->runImport(
            $organization,
            $config['default_project'],
            fn (?string $updatedAfter, int $page, int $perPage): array => $client->issues($this->projectId, $updatedAfter, $page, $perPage),
        );
    }

    protected function pluginId(): string {
        return GitlabPlugin::ID;
    }

    protected function externalType(): string {
        return GitlabPlugin::EXT_TYPE_ISSUE;
    }

    protected function checkpointKey(): string {
        return self::CHECKPOINT_KEY;
    }

    protected function maxPagesConfigKey(): string {
        return 'plugins.gitlab.max_pages';
    }

    /** iid + project_id — NIE die globale id (Recherche 2026-07). */
    protected function externalId(array $issue): string {
        return sprintf('%s#%d', $this->projectId, (int) ($issue['iid'] ?? 0));
    }

    protected function taskTitle(array $issue): string {
        $iid = (int) ($issue['iid'] ?? 0);
        $title = trim((string) ($issue['title'] ?? ''));

        return '#' . $iid . ' ' . ($title !== '' ? $title : ('Issue ' . $iid));
    }
}
