<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubIssueImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Services;

use App\Models\Organization;
use App\Plugins\Github\Api\GithubClient;
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Plugins\Support\GitIssueImport\AbstractGitIssueImporter;

/**
 * Import von GitHub-Issues als WorkDiary-Aufgaben (Feature 060, MVP-129,
 * Bauturbo A6). GitHub bleibt führend; hier entstehen nur Aufgaben für
 * Zeiterfassung/Abrechnung. **Idempotent** über ExternalReference
 * (Plugin `github`, Typ `issue`, Schlüssel `owner/repo#number`): ein Replay
 * legt keine Dubletten an. Skeleton in der Basis (C8); hier nur
 * GitHub-Spezifika (Repo-Adressierung, PR-Filter, `since`-Checkpoint).
 */
class GithubIssueImporter extends AbstractGitIssueImporter {
    /** Settings-Schlüssel des Polling-Aufholpunkts (GitHub `since`). */
    public const CHECKPOINT_KEY = 'since_checkpoint';

    private string $owner = '';

    private string $repo = '';

    /**
     * @param  array{api_token: ?string, repo_owner: ?string, repo_name: ?string, webhook_secret: ?string, default_project: ?string, base_url: string, enabled: bool}|null  $config
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    public function import(Organization $organization, GithubClient $client, ?array $config = null): array {
        $config ??= GithubConfig::resolve((int) $organization->id);
        $this->owner = (string) $config['repo_owner'];
        $this->repo = (string) $config['repo_name'];

        return $this->runImport(
            $organization,
            $config['default_project'],
            fn (?string $since, int $page, int $perPage): array => $client->issues($this->owner, $this->repo, $since, $page, $perPage),
        );
    }

    protected function pluginId(): string {
        return GithubPlugin::ID;
    }

    protected function externalType(): string {
        return GithubPlugin::EXT_TYPE_ISSUE;
    }

    protected function checkpointKey(): string {
        return self::CHECKPOINT_KEY;
    }

    protected function maxPagesConfigKey(): string {
        return 'plugins.github.max_pages';
    }

    /** Recherche 2026-07: die Issues-API liefert auch Pull Requests. */
    protected function shouldSkip(array $issue): bool {
        return isset($issue['pull_request']);
    }

    protected function externalId(array $issue): string {
        return sprintf('%s/%s#%d', $this->owner, $this->repo, (int) ($issue['number'] ?? 0));
    }

    protected function taskTitle(array $issue): string {
        $number = (int) ($issue['number'] ?? 0);
        $title = trim((string) ($issue['title'] ?? ''));

        return '#' . $number . ' ' . ($title !== '' ? $title : ('Issue ' . $number));
    }
}
