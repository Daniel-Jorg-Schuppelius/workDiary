<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Plugins\Github\Api\GithubClientFactory;
use App\Plugins\Github\GithubConfig;
use App\Plugins\Github\Services\GithubIssueImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polling-Import als verlässliche Quelle (Feature 060, MVP-129): GitHub
 * liefert Webhooks nicht automatisch nach — dieser Lauf holt über den
 * `since`-Aufholpunkt lückenlos auf. Idempotent über ExternalReference; ein
 * Abbruch in einer Organisation lässt die übrigen unberührt.
 */
class GithubSyncCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'github:sync ' . self::ORGANIZATION_OPTION;

    protected $description = 'Importiert GitHub-Issues je Organisation als Aufgaben (idempotent, since-Aufholpunkt).';

    public function handle(GithubClientFactory $factory, GithubIssueImporter $importer): int {
        $this->forEachOrganization(function (Organization $org) use ($factory, $importer): void {
            $config = GithubConfig::resolve((int) $org->id);
            if (! $config['enabled'] || ! GithubConfig::isConfigured((int) $org->id)) {
                return;
            }

            $result = $importer->import($org, $factory->for((int) $org->id), $config);
            $this->info(sprintf(
                'Organisation #%d (%s) / %s/%s: created %d, updated %d, skipped %d',
                $org->id, $org->name, (string) $config['repo_owner'], (string) $config['repo_name'],
                $result['created'], $result['updated'], $result['skipped'],
            ));
        }, onError: fn (Organization $org, Throwable $e) => $this->error(sprintf('Organisation #%d: Abbruch — %s', $org->id, $e->getMessage())));

        return self::SUCCESS;
    }
}
