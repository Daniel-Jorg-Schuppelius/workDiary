<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicInterviewOfferController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Concerns\ChecksTenantPublicSurfaces;
use App\Http\Controllers\Controller;
use App\Models\Applications\{JobApplication, JobInterviewOffer};
use App\Services\Applications\InterviewOfferService;
use App\Support\{ErrorText, OrganizationContext};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use RuntimeException;

/** Öffentliche Terminwahl des Bewerbers (MVP-925); unbekannter, abgelaufener oder benutzter Link → 404. */
class PublicInterviewOfferController extends Controller {
    use ChecksTenantPublicSurfaces;

    public function __construct(private readonly InterviewOfferService $offers) {}

    public function show(string $token): View {
        $offer = $this->offer($token);

        return OrganizationContext::run($offer->organization ?? abort(404), fn (): View => view('public.interview-offer', [
            'offer' => $offer,
            'token' => $token,
            'orgName' => (string) $offer->organization->name,
            'title' => (string) JobApplication::query()->withoutGlobalScopes()->with('requisition')->find($offer->job_application_id)?->requisition?->title,
        ]));
    }

    public function choose(Request $request, string $token): View|RedirectResponse {
        $offer = $this->offer($token);
        $data = $request->validate(['slot' => ['required', 'integer', 'min:0', 'max:4']]);

        try {
            $interview = OrganizationContext::run($offer->organization ?? abort(404), fn () => $this->offers->choose($offer, (int) $data['slot']));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return OrganizationContext::run($offer->organization, fn (): View => view('public.interview-offer-confirmed', [
            'interview' => $interview,
            'orgName' => (string) $offer->organization->name,
        ]));
    }

    private function offer(string $token): JobInterviewOffer {
        $offer = $this->offers->resolve($token);
        abort_unless($offer instanceof JobInterviewOffer, 404);
        $offer->load('organization');
        $this->assertTenantPublicSurfacesAvailable($offer->organization);

        return $offer;
    }
}
