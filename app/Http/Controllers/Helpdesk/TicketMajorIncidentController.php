<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketMajorIncidentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\{ServiceTicket, User};
use App\Services\ServiceTicket\TicketIncidentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Major-Incident-Kennzeichnung (Feature 065, MVP-160): Lead ist Pflicht
 * (org-gescopte User-Sqid); Beginn/Ende landen als system_event in der
 * Zeitlinie ({@see TicketIncidentService::markMajor()}/unmarkMajor()).
 */
class TicketMajorIncidentController extends Controller {
    public function __construct(private readonly TicketIncidentService $incidents) {}

    public function store(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('update', $ticket);

        $data = $request->validate([
            'incident_lead' => ['required', 'string'],
            'stakeholders' => ['nullable', 'string', 'max:1000'],
            'comm_rhythm' => ['nullable', 'string', 'max:120'],
        ]);

        $leadId = Sqid::decode(User::class, $data['incident_lead']);
        $lead = $leadId !== null
            ? User::query()
                ->whereKey($leadId)
                ->where('organization_id', $ticket->organization_id)
                ->first()
            : null;
        if ($lead === null) {
            return back()->withErrors(['incident_lead' => __('Leitung nicht gefunden.')]);
        }

        $stakeholders = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) ($data['stakeholders'] ?? '')),
        ), static fn (string $value): bool => $value !== ''));

        /** @var User $user */
        $user = Auth::user();
        $this->incidents->markMajor($ticket, $lead, $stakeholders, $data['comm_rhythm'] ?? null, $user);

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Major Incident ausgerufen.'));
    }

    public function destroy(ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('update', $ticket);

        /** @var User $user */
        $user = Auth::user();
        $this->incidents->unmarkMajor($ticket, $user);

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Major-Incident-Status aufgehoben.'));
    }
}
