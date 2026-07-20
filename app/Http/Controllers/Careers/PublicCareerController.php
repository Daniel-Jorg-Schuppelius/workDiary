<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicCareerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Models\Applications\JobPosting;
use App\Models\Organization;
use App\Services\Applications\CareerFormState;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Öffentlicher Karrierebereich (MVP-437), read-only: Stellenliste, Stellendetail
 * und die einbettbare Ansicht. Läuft im schlanken `careers`-Middleware-Stack
 * (kein Auth/Org-Context/Locale); die Organisation ist über
 * {@see \App\Http\Middleware\Careers\ResolveCareerPortal} bereits aufgelöst.
 */
class PublicCareerController extends Controller {
    public function index(Request $request): View {
        $organization = $this->organization($request);

        $postings = JobPosting::query()
            ->where('organization_id', $organization->id)
            ->publiclyListed()
            ->orderByDesc('published_at')
            ->get();

        return view('careers.index', [
            'organization' => $organization,
            'postings' => $postings,
        ]);
    }

    // Hinweis: Bei Präfix-Parametern (`karriere/{org}`) bindet Laravel skalare
    // Controller-Argumente positionsweise — daher MUSS `{org}` als erstes
    // Argument stehen, sonst landet der Org-Slug in `$posting`.
    public function show(Request $request, string $org, string $posting): View {
        $organization = $this->organization($request);
        $model = $this->resolvePosting($organization, $posting);

        return view('careers.show', $this->detailData($organization, $model, embed: false, issuedAt: $this->requestTime($request)));
    }

    public function embed(Request $request, string $org, string $posting): View {
        $organization = $this->organization($request);
        $model = $this->resolvePosting($organization, $posting);

        return view('careers.embed', $this->detailData($organization, $model, embed: true, issuedAt: $this->requestTime($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(Organization $organization, JobPosting $posting, bool $embed, int $issuedAt): array {
        return [
            'organization' => $organization,
            'posting' => $posting,
            'content' => $posting->publicPayload(),
            'applyable' => $posting->isApplyable(),
            'embed' => $embed,
            'formToken' => CareerFormState::issue($posting->id, $issuedAt),
            'privacyNoticeUrl' => (string) Setting::get('applications.portal.privacy_notice_url', ''),
            'privacyNoticeText' => (string) Setting::get('applications.portal.privacy_notice_text', ''),
        ];
    }

    private function resolvePosting(Organization $organization, string $slug): JobPosting {
        $posting = JobPosting::query()
            ->where('organization_id', $organization->id)
            ->where('public_slug', $slug)
            ->publicResolvable()
            ->with('requisition')
            ->first();

        abort_if(! $posting instanceof JobPosting, 404);

        return $posting;
    }

    private function organization(Request $request): Organization {
        $organization = $request->attributes->get('career_organization');
        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }

    private function requestTime(Request $request): int {
        $time = $request->server('REQUEST_TIME');

        return is_numeric($time) ? (int) $time : 0;
    }
}
