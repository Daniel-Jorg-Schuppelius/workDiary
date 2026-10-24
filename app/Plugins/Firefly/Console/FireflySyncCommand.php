<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflySyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Services\FireflySyncService;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Throwable;

/** Firefly-III-Abgleich je Organisation (Scheduler `firefly.sync` stündlich). */
class FireflySyncCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    protected $signature = 'firefly:sync ' . self::ORGANIZATION_OPTION;

    protected $description = 'Spiegelt Kontobewegungen aus Firefly III und ordnet sie Rechnungen zu.';

    public function handle(FireflySyncService $sync): int {
        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($sync): void {
                if (! $this->pluginEnabledFor(FireflyPlugin::ID, (int) $organization->id) || ! $sync->mayRun($organization)) {
                    return;
                }
                $counters = $sync->run($organization);
                $this->info(sprintf('Organisation #%d: read %d, created %d, updated %d, matched %d, removed %d', $organization->id, $counters['read'], $counters['created'], $counters['updated'], $counters['matched'], $counters['removed']));
            },
            onError: function (Organization $organization, Throwable $e): void {
                $this->error(sprintf('Organisation #%d: %s', $organization->id, class_basename($e)));
                report($e);
            },
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
