<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{AppointmentRequest, BookableService, User};
use App\Services\Appointments\{AppointmentRequestService, AppointmentSlotService};
use App\Services\SqidEncoder;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Portal-Terminbuchung (Feature 087, MVP-666–668): kuratierte Leistungsarten,
 * anonyme Slots, zweiphasige Anfrage.
 *
 * Kein Kunde schreibt in den Dienstplan: Der Kunde WÄHLT ein Fenster, die
 * Disposition BESTÄTIGT — erst dann entsteht der Eintrag.
 */
class AppointmentController extends Controller {
    public function index(AppointmentSlotService $slots, Request $request, SqidEncoder $sqids): View {
        /** @var User $portalUser */
        $portalUser = Auth::guard('customer')->user();

        $services = BookableService::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $portalUser->organization_id)
            ->where('active', true)
            ->orderBy('title')
            ->get();

        // Slot-Vorschau: gewählte Leistung + Datum (Default: frühester Tag).
        $selected = null;
        $day = null;
        $windows = [];
        $serviceSqid = (string) $request->query('service', '');
        if ($serviceSqid !== '') {
            $serviceId = $sqids->decode(BookableService::class, $serviceSqid);
            $selected = $services->firstWhere('id', $serviceId);
        }
        if ($selected !== null) {
            $day = CarbonImmutable::parse((string) $request->query('day', $selected->earliestStart()->format('Y-m-d')))->startOfDay();
            $windows = $slots->slotsFor($selected, $day);
        }

        $requests = AppointmentRequest::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $portalUser->organization_id)
            ->where('portal_user_id', $portalUser->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('customer.appointments.index', [
            'services' => $services,
            'selected' => $selected,
            'day' => $day,
            'windows' => $windows,
            'requests' => $requests,
        ]);
    }

    public function store(Request $request, AppointmentRequestService $service, SqidEncoder $sqids): RedirectResponse {
        /** @var User $portalUser */
        $portalUser = Auth::guard('customer')->user();
        $customer = $portalUser->customer;
        abort_if($customer === null, 404);

        $data = $request->validate([
            'service' => ['required', 'string'],
            'start' => ['required', 'date'],
        ]);

        $serviceId = $sqids->decode(BookableService::class, (string) $data['service']);
        $bookable = BookableService::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $portalUser->organization_id)
            ->whereKey($serviceId)
            ->first();
        abort_if($bookable === null, 404);

        try {
            $service->requestFromPortal($bookable, $customer, $portalUser, CarbonImmutable::parse((string) $data['start']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.appointments.index')
            ->with('success', __('Terminanfrage gesendet — wir bestätigen verbindlich.'));
    }

    public function cancel(AppointmentRequest $appointmentRequest, AppointmentRequestService $service): RedirectResponse {
        /** @var User $portalUser */
        $portalUser = Auth::guard('customer')->user();
        abort_unless($appointmentRequest->organization_id === (int) $portalUser->organization_id, 404);

        try {
            $service->cancelFromPortal($appointmentRequest, $portalUser);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Terminanfrage storniert.'));
    }
}
