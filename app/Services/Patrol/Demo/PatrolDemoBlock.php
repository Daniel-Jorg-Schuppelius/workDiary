<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Patrol\Demo;

use App\Models\Patrol\PatrolRoute;
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Patrol\PatrolService;
use Illuminate\Support\Carbon;

/**
 * Wächterrundgang-Vorführung (MVP-1288): eine Route mit drei Kontrollpunkten,
 * ein pünktlicher und ein abweichender Lauf für Bericht und Leitstelle.
 */
final class PatrolDemoBlock implements DemoBlock {
    public function __construct(private readonly PatrolService $patrols) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;
        $actor = $context->actor;

        $route = PatrolRoute::query()->create([
            'organization_id' => $organization->id,
            'name' => (string) __('Nachtrundgang Lager (Demo)'),
            'active' => true,
            'created_by' => $actor->id,
        ]);
        $tokens = [];
        foreach ([[__('Haupttor'), 0], [__('Lagerhalle Nord'), 15], [__('Serverraum'), 30]] as [$label, $offset]) {
            $tokens[] = $this->patrols->addCheckpoint($route, (string) $label, $offset)['token'];
        }

        // Pünktlich vor zwei Tagen, mit Lücke gestern.
        foreach ([[2, [0, 14, 31]], [1, [0, 26]]] as [$daysAgo, $minutes]) {
            $run = $this->patrols->start($route, $actor);
            $startedAt = Carbon::now()->subDays($daysAgo)->setTime(22, 0);
            $run->forceFill(['started_at' => $startedAt])->save();
            foreach ($minutes as $i => $offset) {
                $this->patrols->scan($run, $tokens[$i], $startedAt->copy()->addMinutes($offset));
            }
            $this->patrols->complete($run->refresh(), $actor, $daysAgo === 1 ? (string) __('Serverraum war wegen Wartung verschlossen.') : null);
            $run->forceFill(['finished_at' => $startedAt->copy()->addMinutes(40)])->save();
        }

        return ['patrol_routes' => 1, 'patrol_runs' => 2];
    }

    public function purge(Organization $organization): void {
        PatrolRoute::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
