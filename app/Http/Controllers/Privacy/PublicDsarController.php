<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicDsarController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\SubmitDsarRequest;
use App\Models\Privacy\{DataSubjectRequest, DsarPortal};
use App\Services\Privacy\{DsarPortalIntakeService, PrivacyEventService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Oeffentliches Betroffenen-Selbstmeldeportal (Feature 043, MVP-728, G11):
 * Formular, Eingang, Quittung und Adressbestaetigung. Kein App-Menue, kein
 * Auth-Kontext, kein JavaScript.
 *
 * Default-Deny und keine Enumeration: die Organisation kommt ausschliesslich
 * aus dem Portal-Slug ({@see \App\Http\Middleware\Privacy\ResolveDsarPortal}),
 * jede nicht aufloesbare Situation endet in 404 statt in einer sprechenden
 * Meldung.
 */
class PublicDsarController extends Controller {
    public function __construct(private readonly DsarPortalIntakeService $intake) {}

    /** Neutrale Landingpage unter /datenschutz/anfrage — ohne Portal-/Org-Bezug. */
    public function landing(): View {
        return view('privacy.public.landing');
    }

    public function show(Request $request): View {
        return view('privacy.public.portal', [
            'portal' => $this->portal($request),
            'types' => DataSubjectRequestType::cases(),
        ]);
    }

    public function store(SubmitDsarRequest $request): RedirectResponse {
        $portal = $this->portal($request);

        // Honeypot: ausgefuelltes verstecktes Feld → still als „Erfolg"
        // behandeln, ohne dem Bot eine verwertbare Rueckmeldung zu geben.
        if (trim((string) $request->input('company_website', '')) !== '') {
            return $this->toReceipt($portal, null);
        }

        $dsr = $this->intake->submit($portal, $request->validated(), $request->uploadedAttachments());

        return $this->toReceipt($portal, (string) $dsr->request_number);
    }

    public function receipt(Request $request): View|RedirectResponse {
        $portal = $this->portal($request);
        $number = session('dsar_request_number');

        // Direktaufruf ohne frische Anfrage → zurueck zum Formular.
        if (! session()->has('dsar_submitted')) {
            return redirect()->route('dsar.portal', ['portal' => $portal->public_slug]);
        }

        return view('privacy.public.receipt', [
            'portal' => $portal,
            'requestNumber' => is_string($number) ? $number : null,
        ]);
    }

    /**
     * Bestaetigt die Rueckadresse (signierte, befristete URL aus der
     * Eingangsbestaetigung). Bewusst ohne Fallinhalte und ohne Fristwirkung —
     * der Klick belegt nur, dass die Adresse erreichbar ist.
     */
    public function confirm(Request $request, string $dsr): View {
        $id = Sqid::decode(DataSubjectRequest::class, $dsr);
        $model = $id !== null
            ? DataSubjectRequest::query()->withoutGlobalScopes()->find($id)
            : null;

        abort_unless($model instanceof DataSubjectRequest && $model->isFromPortal(), 404);

        if ($model->contact_email_confirmed_at === null) {
            $model->forceFill(['contact_email_confirmed_at' => now()])->save();
            app(PrivacyEventService::class)->record($model, 'portal_email_confirmed');
        }

        return view('privacy.public.confirmed', [
            'requestNumber' => (string) $model->request_number,
        ]);
    }

    private function toReceipt(DsarPortal $portal, ?string $requestNumber): RedirectResponse {
        return redirect()
            ->route('dsar.receipt', ['portal' => $portal->public_slug])
            ->with('dsar_submitted', true)
            ->with('dsar_request_number', $requestNumber);
    }

    private function portal(Request $request): DsarPortal {
        $portal = $request->attributes->get('dsar_portal');
        abort_unless($portal instanceof DsarPortal, 404);

        return $portal;
    }
}
