<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationCalendarFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Services\Event\OrganizationCalendarFeedService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Abo-Link für den gemeinsamen Kalender der Organisation.
 *
 * Berechtigung bewusst `organization.update` und nicht `event.*`: der Link
 * öffnet die öffentlichen Termine ohne Anmeldung: das ist eine Entscheidung
 * über die Organisation, keine Terminpflege (wie beim Geräte-Pass).
 */
class OrganizationCalendarFeedController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly OrganizationCalendarFeedService $feeds) {}

    public function show(Request $request): View {
        Gate::authorize(P::OrganizationUpdate->value);

        $status = $this->feeds->status($this->currentOrganization());

        return view('events.feed', [
            'status' => $status,
            // Zeitstempel in der Anzeige-Zeitzone, nicht roh aus den Einstellungen.
            'issuedAt' => is_string($status['issued_at']) ? Tz::toLocal(CarbonImmutable::parse($status['issued_at'])) : null,
            // Der Klartext überlebt genau eine Umleitung — danach ist er weg.
            'token' => $request->session()->get('organization_calendar_feed_token'),
        ]);
    }

    public function rotate(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);
        $token = $this->feeds->issue($this->currentOrganization());

        return redirect()->route('events.feed.show')
            ->with('organization_calendar_feed_token', $token)
            ->with('success', __('Neuer Kalender-Link erzeugt.'));
    }

    public function revoke(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);
        $this->feeds->revoke($this->currentOrganization());

        return redirect()->route('events.feed.show')
            ->with('success', __('Kalender-Link widerrufen.'));
    }
}
