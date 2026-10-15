<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeReconcileCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\License;

use App\Models\Platform\Organization;
use App\Services\Licensing\ModuleScopeService;
use Illuminate\Console\Command;

/**
 * Umfangsabgleich (Feature 165, MVP-1188): bewusst begrenzte Organisationen
 * behalten neu lizenzierte Module deaktiviert, bis sie gewählt werden. Läuft
 * im Deploy hinter `modules:cache` (neue Katalogmodule haben keinen Hook)
 * und täglich als Fangnetz; idempotent.
 */
class ScopeReconcileCommand extends Command {
    protected $signature = 'scope:reconcile
        {--org= : Nur diese Organisation (ID)}';

    protected $description = 'Hält bei bewusst begrenzten Organisationen neu lizenzierte Module deaktiviert, bis sie gewählt werden.';

    public function handle(ModuleScopeService $scope): int {
        $query = Organization::query()->orderBy('id');
        $only = $this->option('org');
        if (is_string($only) && $only !== '') {
            $query->whereKey((int) $only);
        }

        $organizations = 0;
        $disabled = 0;
        $query->chunkById(100, function ($chunk) use ($scope, &$organizations, &$disabled): void {
            foreach ($chunk as $organization) {
                if (! $scope->isLimited($organization)) {
                    continue;
                }
                $organizations++;
                $result = $scope->reconcile($organization);
                $disabled += count($result);
                if ($result !== []) {
                    $this->line(sprintf('  %s (#%d): %s deaktiviert', $organization->name, $organization->id, implode(', ', $result)));
                }
            }
        });

        $this->info(sprintf('Umfangsabgleich: %d begrenzte Organisation(en) geprüft, %d Modul(e) neu deaktiviert.', $organizations, $disabled));

        return self::SUCCESS;
    }
}
