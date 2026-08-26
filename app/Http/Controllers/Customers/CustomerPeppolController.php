<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPeppolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Peppol\PeppolParticipantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * „Registrierung prüfen" an der Kundenakte (Feature 066, MVP-734): löst die
 * hinterlegte Peppol-Teilnehmerkennung über SML/SMP auf und zeigt das
 * Ergebnis. Erzwingt eine frische Auflösung (`refresh`) — der Zwischenspeicher
 * ist für den Versand da, die manuelle Prüfung will die aktuelle Auskunft.
 */
class CustomerPeppolController extends Controller {
    public function __invoke(Customer $customer, PeppolParticipantService $participants): RedirectResponse {
        Gate::authorize('update', $customer);

        $participant = PeppolParticipantService::forCustomer($customer);
        if ($participant === null) {
            $raw = trim((string) $customer->peppol_participant_id);

            return back()->with('error', $raw === ''
                ? __('peppol.error.no_participant', ['customer' => (string) $customer->name])
                : __('peppol.error.invalid_participant', ['customer' => (string) $customer->name, 'value' => $raw]));
        }

        try {
            $lookup = $participants->lookup((int) $customer->organization_id, $participant, refresh: true);
        } catch (RuntimeException $e) {
            return back()->with('error', __('peppol.error.lookup_failed', ['message' => $e->getMessage()]));
        }

        $result = $lookup->registered
            ? (string) __('peppol.status.registered', [
                'smp' => (string) $lookup->smp_base_url,
                'count' => count($lookup->document_types ?? []),
            ])
            : (string) __('peppol.status.not_registered');

        $customer->audit('customer.peppolChecked', [
            'participant' => $participant->canonical(),
            'registered' => $lookup->registered,
            'document_types' => count($lookup->document_types ?? []),
        ]);

        return back()->with($lookup->registered ? 'status' : 'error', __('peppol.flash.checked', [
            'customer' => (string) $customer->name,
            'result' => $result,
        ]));
    }
}
