<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubIssueWritebackDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Services;

use App\Plugins\Github\Api\GithubClientFactory;
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Plugins\Support\GitIssueImport\AbstractGitIssueWritebackDispatcher;

/**
 * Status-Rückrichtung nach GitHub (Audit 2026-08, Welle 1.4): erledigte
 * Aufgabe → Issue schließen (+ Notiz), wiedereröffnete → Issue öffnen.
 * external_id-Format des Importers: `owner/repo#number`.
 */
class GithubIssueWritebackDispatcher extends AbstractGitIssueWritebackDispatcher {
    public function pluginId(): string {
        return GithubPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = GithubConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'] && GithubConfig::isConfigured($organizationId);
    }

    public function externalType(): string {
        return GithubPlugin::EXT_TYPE_ISSUE;
    }

    protected function applyState(int $organizationId, string $externalId, bool $closed): void {
        [$owner, $repo, $number] = $this->parse($externalId);
        $this->client($organizationId)->setIssueState($owner, $repo, $number, $closed);
    }

    protected function comment(int $organizationId, string $externalId, string $body): void {
        [$owner, $repo, $number] = $this->parse($externalId);
        $this->client($organizationId)->commentIssue($owner, $repo, $number, $body);
    }

    private function client(int $organizationId): \App\Plugins\Github\Api\GithubClient {
        return app(GithubClientFactory::class)->for($organizationId);
    }

    /** @return array{0: string, 1: string, 2: int} */
    private function parse(string $externalId): array {
        // `owner/repo#number` — Bestandsformat des Importers (nie ändern).
        if (! preg_match('/^([^\/]+)\/([^#]+)#(\d+)$/', $externalId, $m)) {
            throw new \RuntimeException('GitHub-Referenz nicht auflösbar: ' . $externalId);
        }

        return [$m[1], $m[2], (int) $m[3]];
    }
}
