<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcsFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\Platform\Organization;
use App\Models\Platform\User;
use App\Services\Event\{IcsFeedService, OrganizationCalendarFeedService};
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class IcsFeedController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly IcsFeedService $ics,
    ) {}

    public function personal(): Response {
        /** @var User $user */
        $user = Auth::user();

        return response($this->ics->feedForUser($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-events.ics"',
        ]);
    }

    /** Gemeinsamer Feed der eigenen Organisation — angemeldet und mandantengebunden. */
    public function public(): Response {
        return response($this->ics->feedPublic($this->currentOrganization()), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-public-events.ics"',
        ]);
    }

    /**
     * Tokenisierter Feed der Organisation (Termine mit Visibility=Public).
     * Ohne Anmeldung, weil Outlook/Google/Apple eine Abo-Adresse ohne Sitzung
     * abrufen — Sicherheit über den zufälligen Token, dessen Abdruck die
     * Organisation bestimmt (Mandanten-Review 2026-09-13).
     */
    public function organizationFeed(string $token, OrganizationCalendarFeedService $feeds): Response {
        if (strlen($token) < 32) {
            abort(404);
        }
        $organization = $feeds->resolve($token);
        abort_unless($organization instanceof Organization, 404);

        return response($this->ics->feedPublic($organization), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-public-events.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Tokenisierter persönlicher Schedule-Feed (Urlaube + Schichten).
     * Public route, ohne Auth – Sicherheit über zufälligen Token.
     */
    public function personalSchedule(string $token): Response {
        if (strlen($token) < 32) {
            abort(404);
        }
        // Auflösung über den Hash (S-44) — gespeichert ist kein Klartext mehr.
        $user = User::query()->where('calendar_feed_token_hash', User::hashCalendarFeedToken($token))->first();
        // Deaktivierte Konten liefern nichts mehr (Audit 2026-09-17, offboard-1).
        abort_unless($user instanceof User && $user->canLogin(), 404);

        return response($this->ics->feedPersonalSchedule($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-schedule.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
