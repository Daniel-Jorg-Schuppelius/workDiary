<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Enums\Whistleblowing\ReporterMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whistleblowing\SubmitReportRequest;
use App\Models\Whistleblowing\Portal;
use App\Services\Whistleblowing\WhistleblowingReportService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Nimmt Meldungen entgegen und zeigt die einmaligen Zugangsdaten. Die
 * Zugangsdaten werden NUR einmal (per Session-Flash) angezeigt und nirgends
 * im Klartext gespeichert.
 */
class PublicReportController extends Controller {
    public function store(SubmitReportRequest $request, WhistleblowingReportService $service): RedirectResponse {
        /** @var Portal $portal */
        $portal = $request->attributes->get('wb_portal');

        $mode = (string) $request->input('reporter_mode');
        if ($mode === ReporterMode::Anonymous->value && ! $portal->allow_anonymous) {
            return back()->withErrors(['reporter_mode' => __('Anonyme Meldungen sind fuer dieses Portal nicht aktiviert.')])->withInput();
        }
        if ($mode === ReporterMode::Confidential->value && ! $portal->allow_confidential) {
            return back()->withErrors(['reporter_mode' => __('Vertrauliche Meldungen sind fuer dieses Portal nicht aktiviert.')])->withInput();
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $files */
        $files = $request->file('attachments', []);

        $result = $service->submit($portal, $request->validated(), $files);

        // Einmalige Anzeige ueber Flash – danach unwiederbringlich.
        return redirect()
            ->route('whistleblowing.receipt', ['portal' => $portal->public_slug])
            ->with('wb_case_number', $result['case_number'])
            ->with('wb_secret', $result['secret']);
    }

    public function receipt(Request $request): View|RedirectResponse {
        /** @var Portal $portal */
        $portal = $request->attributes->get('wb_portal');

        $caseNumber = session('wb_case_number');
        $secret = session('wb_secret');

        // Direktaufruf ohne frische Meldung → zurueck zum Portal.
        if (! is_string($caseNumber) || ! is_string($secret)) {
            return redirect()->route('whistleblowing.portal', ['portal' => $portal->public_slug]);
        }

        return view('whistleblowing.public.receipt', [
            'portal' => $portal,
            'caseNumber' => $caseNumber,
            'secret' => $secret,
        ]);
    }
}
