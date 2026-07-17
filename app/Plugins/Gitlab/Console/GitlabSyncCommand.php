<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Plugins\Gitlab\Api\GitlabClientFactory;
use App\Plugins\Gitlab\GitlabConfig;
use App\Plugins\Gitlab\Services\GitlabIssueImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polling-Import als verlässliche Quelle (Feature 060, MVP-129): GitLab
 * deaktiviert Webhooks nach wiederholten Zustellfehlern selbst — dieser Lauf
 * holt über den `updated_after`-Aufholpunkt lückenlos auf. Idempotent über
 * ExternalReference; ein Abbruch in einer Organisation lässt die übrigen
 * unberührt.
 */
class GitlabSyncCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'gitlab:sync ' . self::ORGANIZATION_OPTION;

    protected $description = 'Importiert GitLab-Issues je Organisation als Aufgaben (idempotent, updated_after-Aufholpunkt).';

    public function handle(GitlabClientFactory $factory, GitlabIssueImporter $importer): int {
        $this->forEachOrganization(function (Organization $org) use ($factory, $importer): void {
            $config = GitlabConfig::resolve((int) $org->id);
            if (! $config['enabled'] || ! GitlabConfig::isConfigured((int) $org->id)) {
                return;
            }

            $result = $importer->import($org, $factory->for((int) $org->id), $config);
            $this->info(sprintf(
                'Organisation #%d (%s) / Projekt %s: created %d, updated %d, skipped %d',
                $org->id, $org->name, (string) $config['project_id'],
                $result['created'], $result['updated'], $result['skipped'],
            ));
        }, onError: fn (Organization $org, Throwable $e) => $this->error(sprintf('Organisation #%d: Abbruch — %s', $org->id, $e->getMessage())));

        return self::SUCCESS;
    }
}
