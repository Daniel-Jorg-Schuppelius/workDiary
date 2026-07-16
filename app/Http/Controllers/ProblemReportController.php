<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Support\ProblemReportSeverity;
use App\Models\ProblemReport;
use App\Services\Support\ProblemReportService;
use App\Support\Setting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Problem melden" für angemeldete Nutzer (Feature 041, MVP-053):
 * Dialog aus Hilfe-Sidebar/Fehlerseiten/Supportmenü, eigene Meldungen
 * mit Referenznummer. Jede Meldung bleibt org- und melderbezogen.
 */
class ProblemReportController extends Controller {
    public function __construct(private readonly ProblemReportService $service) {}

    public function index(Request $request): View {
        $reports = ProblemReport::query()
            ->where('user_id', $request->user()?->id)
            ->latest()
            ->paginate((int) Setting::get('pagination.notifications', 25));

        return view('problem-reports.index', ['reports' => $reports]);
    }

    public function create(Request $request): View {
        $mode = $this->service->diagnosticsMode();

        // Der Dialog-Host lädt das Modal per AJAX und hängt `?dialog=1` an
        // (app.js: withDialogParam). Fehlt dieser Kontext — etwa beim Klick
        // auf „Problem melden" auf einer standalone Fehlerseite, die eine
        // volle Seitennavigation auslöst — muss eine eigenständige, gestylte
        // Seite kommen statt des nackten Modal-Fragments (sonst: Text ohne CSS).
        $embedded = $request->boolean('dialog') || $request->ajax();

        return view($embedded ? 'problem-reports._form_dialog' : 'problem-reports.create', [
            'context' => [
                'route' => substr((string) $request->query('route', ''), 0, 150),
                'url' => substr((string) $request->query('url', ''), 0, 500),
                'help_topic' => substr((string) $request->query('topic', ''), 0, 150),
                'error_code' => $request->query('code') !== null ? (int) $request->query('code') : null,
            ],
            'diagnosticsMode' => $mode,
            'diagnosticsPreview' => $mode === ProblemReportService::DIAG_MODE_NEVER
                ? null
                : $this->service->buildDiagnosticExcerpt(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'expected_behavior' => ['nullable', 'string', 'max:2000'],
            'actual_behavior' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(array_column(ProblemReportSeverity::cases(), 'value'))],
            'contact_ok' => ['nullable', 'boolean'],
            'include_diagnostics' => ['nullable', 'boolean'],
            'context_route' => ['nullable', 'string', 'max:150'],
            'context_url' => ['nullable', 'string', 'max:500'],
            'context_topic' => ['nullable', 'string', 'max:150'],
            'context_error_code' => ['nullable', 'integer'],
            'screenshots' => ['nullable', 'array', 'max:3'],
            'screenshots.*' => ['file', 'max:' . (int) Setting::get('uploads.customer_attachment_kb', 10240), 'mimes:png,jpg,jpeg,webp,pdf'],
        ]);

        $report = $this->service->create(
            $user,
            $validated,
            [
                'route' => $validated['context_route'] ?? null,
                'url' => $validated['context_url'] ?? null,
                'help_topic' => $validated['context_topic'] ?? null,
                'error_code' => $validated['context_error_code'] ?? null,
            ],
            array_values($request->file('screenshots', [])),
        );

        // context_url kann leer sein (z. B. Meldung von einer Fehlerseite ohne
        // Ursprungs-URL) — dann zurück zur eigenen Meldungsliste statt auf "".
        $back = filled($validated['context_url'] ?? null) ? $validated['context_url'] : route('problem-reports.index');

        return redirect()
            ->to($back)
            ->with('status', __('problemreport.flash.created', ['reference' => $report->reference_no]));
    }
}
