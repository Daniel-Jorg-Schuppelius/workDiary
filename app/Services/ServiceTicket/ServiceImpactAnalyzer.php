<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceImpactAnalyzer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\ServiceTicket\{BusinessService, ServiceTicket};
use Illuminate\Support\Facades\DB;

/**
 * Servicelandkarte (Feature 065, MVP-1256): Abhängigkeiten zwischen
 * Fachdiensten als gerichteter Graph „hängt ab von“. Fällt ein Fachdienst aus,
 * sind alle betroffen, die direkt oder über andere von ihm abhängen.
 */
final class ServiceImpactAnalyzer {
    /** @var array<int, array{down: array<int, list<int>>, up: array<int, list<int>>}> */
    private array $graphs = [];

    /**
     * Fachdienste, die (auch mittelbar) vom Dienst abhängen — ohne ihn selbst.
     *
     * @return list<int>
     */
    public function affectedBy(int $organizationId, int $businessServiceId): array {
        return $this->reach($this->graph($organizationId)['up'], $businessServiceId);
    }

    /**
     * Fachdienste, auf die der Dienst (auch mittelbar) angewiesen ist.
     *
     * @return list<int>
     */
    public function requiredBy(int $organizationId, int $businessServiceId): array {
        return $this->reach($this->graph($organizationId)['down'], $businessServiceId);
    }

    /**
     * Ergäbe „$service hängt ab von $dependsOnIds“ einen Kreis?
     *
     * @param  list<int>  $dependsOnIds
     */
    public function wouldCreateCycle(int $organizationId, int $businessServiceId, array $dependsOnIds): bool {
        if (in_array($businessServiceId, $dependsOnIds, true)) {
            return true;
        }
        $dependents = $this->affectedBy($organizationId, $businessServiceId);

        return array_intersect($dependsOnIds, $dependents) !== [];
    }

    /**
     * Offene Tickets je Fachdienst.
     *
     * @param  list<int>  $businessServiceIds
     * @return array<int, int>
     */
    public function openTicketCounts(int $organizationId, array $businessServiceIds): array {
        if ($businessServiceIds === []) {
            return [];
        }

        return ServiceTicket::query()
            ->where('organization_id', $organizationId)
            ->whereIn('business_service_id', $businessServiceIds)
            ->whereNotIn('status', [ServiceTicketStatus::Closed, ServiceTicketStatus::Done])
            ->groupBy('business_service_id')
            ->selectRaw('business_service_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'business_service_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /** Neue Kanten nach dem Speichern berücksichtigen. */
    public function forget(int $organizationId): void {
        unset($this->graphs[$organizationId]);
    }

    /**
     * @param  array<int, list<int>>  $edges
     * @return list<int>
     */
    private function reach(array $edges, int $start): array {
        $seen = [$start => true];
        $queue = [$start];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($edges[$current] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }
        unset($seen[$start]);

        return array_keys($seen);
    }

    /** @return array{down: array<int, list<int>>, up: array<int, list<int>>} */
    private function graph(int $organizationId): array {
        if (isset($this->graphs[$organizationId])) {
            return $this->graphs[$organizationId];
        }

        $down = [];
        $up = [];
        $rows = DB::table('business_service_dependencies')
            ->where('organization_id', $organizationId)
            ->get(['business_service_id', 'depends_on_business_service_id']);
        foreach ($rows as $row) {
            $service = (int) $row->business_service_id;
            $dependsOn = (int) $row->depends_on_business_service_id;
            $down[$service][] = $dependsOn;
            $up[$dependsOn][] = $service;
        }

        return $this->graphs[$organizationId] = ['down' => $down, 'up' => $up];
    }

    /**
     * Abhängigkeiten eines Fachdienstes ersetzen; Kreise und fremde Dienste
     * werden vorher abgewiesen.
     *
     * @param  list<int>  $dependsOnIds
     */
    public function syncDependencies(BusinessService $service, array $dependsOnIds): void {
        $organizationId = (int) $service->organization_id;
        $valid = BusinessService::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $dependsOnIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $service->dependsOn()->sync(array_fill_keys($valid, ['organization_id' => $organizationId]));
        $this->forget($organizationId);
    }
}
