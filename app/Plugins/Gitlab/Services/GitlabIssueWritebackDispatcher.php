<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabIssueWritebackDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Services;

use App\Plugins\Gitlab\Api\GitlabClientFactory;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Support\GitIssueImport\AbstractGitIssueWritebackDispatcher;

/**
 * Status-Rückrichtung nach GitLab (Audit 2026-08, Welle 1.4): erledigte
 * Aufgabe → Issue schließen (+ Notiz), wiedereröffnete → Issue öffnen.
 * external_id-Format des Importers: `projectId#iid`.
 */
class GitlabIssueWritebackDispatcher extends AbstractGitIssueWritebackDispatcher {
    public function pluginId(): string {
        return GitlabPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = GitlabConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'] && GitlabConfig::isConfigured($organizationId);
    }

    public function externalType(): string {
        return GitlabPlugin::EXT_TYPE_ISSUE;
    }

    protected function applyState(int $organizationId, string $externalId, bool $closed): void {
        [$projectId, $iid] = $this->parse($externalId);
        $this->client($organizationId)->setIssueState($projectId, $iid, $closed);
    }

    protected function comment(int $organizationId, string $externalId, string $body): void {
        [$projectId, $iid] = $this->parse($externalId);
        $this->client($organizationId)->commentIssue($projectId, $iid, $body);
    }

    private function client(int $organizationId): \App\Plugins\Gitlab\Api\GitlabClient {
        return app(GitlabClientFactory::class)->for($organizationId);
    }

    /** @return array{0: string, 1: int} */
    private function parse(string $externalId): array {
        // `projectId#iid` — Bestandsformat des Importers (nie ändern).
        $pos = strrpos($externalId, '#');
        if ($pos === false || $pos === 0) {
            throw new \RuntimeException('GitLab-Referenz nicht auflösbar: ' . $externalId);
        }

        return [substr($externalId, 0, $pos), (int) substr($externalId, $pos + 1)];
    }
}
