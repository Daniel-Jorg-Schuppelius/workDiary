<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicApplicationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Models\Applications\{JobApplication, JobPosting};
use App\Models\Organization;
use App\Services\Applications\{CareerApplicationUploadService, CareerFormState, RecruitingService};
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Öffentlicher, sessionloser Bewerbungseingang (MVP-437).
 *
 * Missbrauchsschutz: signierter Formularzustand ({@see CareerFormState}, ersetzt
 * die Session-CSRF-Abhängigkeit der Embed-Variante), Honeypot, Idempotenz über
 * eine stabile Eingangsreferenz und Rate-Limits (Route). Uploads gehen
 * gehärtet in Quarantäne. Der Eingang läuft **actorlos** über
 * {@see RecruitingService::publicIntake()} — kein fingierter Nutzer.
 */
class PublicApplicationController extends Controller {
    public function __construct(
        private readonly RecruitingService $recruiting,
        private readonly CareerApplicationUploadService $uploads,
    ) {}

    // `{org}` MUSS als erstes Argument stehen (positionsweise Bindung bei
    // Präfix-Parametern), sonst landet der Org-Slug in `$posting`.
    public function store(Request $request, string $org, string $posting): RedirectResponse {
        $organization = $this->organization($request);
        $model = $this->resolveApplyablePosting($organization, $posting);

        $data = $request->validate([
            'form_state' => ['required', 'string'],
            'candidate_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:5000'],
            'privacy_ack' => ['accepted'],
            'documents' => ['nullable', 'array', 'max:' . CareerApplicationUploadService::MAX_FILES],
            'documents.*' => ['file', 'max:' . (CareerApplicationUploadService::MAX_BYTES / 1024), 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        // Honeypot: ausgefülltes verstecktes Feld → still als „Erfolg" behandeln,
        // ohne dem Bot Rückmeldung zu geben.
        if (trim((string) $request->input('company_website', '')) !== '') {
            return $this->toConfirmation($organization, $posting);
        }

        // Signierter Formularzustand → nonce (zugleich Idempotenz-Anker).
        $now = $request->server('REQUEST_TIME') ? (int) $request->server('REQUEST_TIME') : 0;
        $nonce = CareerFormState::verify((string) $data['form_state'], $model->id, $now);
        if ($nonce === null) {
            return redirect()
                ->route('careers.show', ['org' => $organization->slug, 'posting' => $posting])
                ->with('error', (string) __('careers.form.expired'));
        }

        $emailHash = JobApplication::hashEmail((string) $data['email']);
        $intakeRef = hash('sha256', $model->id . '|' . $emailHash . '|' . $nonce);
        $privacyVersion = $this->privacyVersion();

        try {
            $result = $this->recruiting->publicIntake(
                $organization,
                $model,
                [
                    'candidate_name' => $data['candidate_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'notes' => $data['message'] ?? null,
                ],
                $privacyVersion,
                $intakeRef,
            );
        } catch (QueryException $e) {
            // Doppelsendung desselben Formulars (Unique-Index auf der
            // Eingangsreferenz) → idempotent als Erfolg behandeln.
            if ($this->isDuplicateIntake($e)) {
                return $this->toConfirmation($organization, $posting);
            }
            throw $e;
        }

        $files = \Illuminate\Support\Arr::wrap($request->file('documents'));
        if ($files !== []) {
            $this->uploads->store($result['application'], $files);
        }

        return $this->toConfirmation($organization, $posting);
    }

    public function confirmation(Request $request, string $org, string $posting): View {
        $organization = $this->organization($request);

        return view('careers.confirmation', [
            'organization' => $organization,
            'postingSlug' => $posting,
            'contactEmail' => (string) Setting::get('applications.portal.contact_email', ''),
        ]);
    }

    private function toConfirmation(Organization $organization, string $posting): RedirectResponse {
        return redirect()->route('careers.confirmation', ['org' => $organization->slug, 'posting' => $posting]);
    }

    private function resolveApplyablePosting(Organization $organization, string $slug): JobPosting {
        $posting = JobPosting::query()
            ->where('organization_id', $organization->id)
            ->where('public_slug', $slug)
            ->publicResolvable()
            ->with('requisition')
            ->first();

        abort_if(! $posting instanceof JobPosting, 404);
        abort_unless($posting->isApplyable(), 404);

        return $posting;
    }

    private function privacyVersion(): string {
        $text = (string) Setting::get('applications.portal.privacy_notice_text', '');
        $url = (string) Setting::get('applications.portal.privacy_notice_url', '');

        return substr(hash('sha256', $text . '|' . $url), 0, 40);
    }

    private function isDuplicateIntake(QueryException $e): bool {
        return str_contains($e->getMessage(), 'jap_org_intake_ref_unq')
            || (isset($e->errorInfo[0]) && in_array((string) $e->errorInfo[0], ['23000', '23505'], true));
    }

    private function organization(Request $request): Organization {
        $organization = $request->attributes->get('career_organization');
        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }
}
