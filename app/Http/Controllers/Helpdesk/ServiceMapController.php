<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceMapController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\ServiceTicket\{BusinessService, ServiceTicket};
use App\Services\ServiceTicket\ServiceImpactAnalyzer;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Servicelandkarte (Feature 065, MVP-1256): je Fachdienst, wovon er abhängt,
 * wer von ihm abhängt, wie viele Dienste bei seinem Ausfall betroffen sind und
 * wie viele Tickets offen sind.
 */
final class ServiceMapController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(ServiceImpactAnalyzer $impact): View {
        Gate::authorize('viewAny', ServiceTicket::class);
        $organizationId = (int) $this->currentOrganization()->id;

        $services = BusinessService::query()
            ->with(['dependsOn:business_services.id,name', 'dependents:business_services.id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'active']);
        $openTickets = $impact->openTicketCounts($organizationId, array_values($services->pluck('id')->map(fn ($id): int => (int) $id)->all()));

        $rows = $services->map(fn (BusinessService $service): array => [
            'service' => $service,
            'affected' => count($impact->affectedBy($organizationId, (int) $service->id)),
            'openTickets' => $openTickets[$service->id] ?? 0,
        ]);

        return view('helpdesk.service-map.index', [
            'rows' => $rows,
            'canManage' => Gate::allows('create', \App\Models\Procurement\RequestItem::class),
        ]);
    }
}
