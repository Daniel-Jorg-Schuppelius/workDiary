<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\GobdExport;
use App\Services\Finance\GdpduExportService;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132): Auswahl von Zeitraum
 * und Datenbereichen mit Preflight (Vollständigkeitswarnungen) sowie Download
 * des GDPdU-Pakets. Erzeugung + revisionssicherer Nachweis laufen über den
 * {@see GdpduExportService}. Recht `finance.gobd.export`; Modul-Gating
 * `module.finance` automatisch über die `finance.*`-Routen.
 */
class GobdExportController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly GdpduExportService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', GobdExport::class);
        $organization = $this->currentOrganization();

        [$from, $to] = $this->period($request);
        $preflight = $request->filled('from') && $request->filled('to')
            ? $this->service->preflight($organization, $from, $to)
            : null;

        return view('finance.gobd.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sections' => $this->service->availableSections(),
            'selected' => array_values(array_filter((array) $request->input('sections', $this->service->availableSections()), 'is_string')),
            'encodings' => $this->service->availableEncodings(),
            'preflight' => $preflight,
            'recent' => GobdExport::query()->with('creator:id,name')->orderByDesc('created_at')->limit(10)->get(),
        ]);
    }

    public function export(Request $request): Response {
        Gate::authorize('viewAny', GobdExport::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['string'],
            'encoding' => ['sometimes', 'string', Rule::in($this->service->availableEncodings())],
        ]);

        $result = $this->service->build(
            $this->currentOrganization(),
            Carbon::parse((string) $data['from']),
            Carbon::parse((string) $data['to']),
            array_values(array_filter((array) ($data['sections'] ?? $this->service->availableSections()), 'is_string')),
            Auth::user(),
            (string) ($data['encoding'] ?? GdpduExportService::ENCODING_CP1252),
        );

        return response($result['content'], 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} Vorjahr als Standard-Prüfungszeitraum. */
    private function period(Request $request): array {
        $from = $request->filled('from')
            ? Carbon::parse((string) $request->input('from'))
            : Carbon::now()->subYear()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse((string) $request->input('to'))
            : Carbon::now()->subYear()->endOfYear();

        return [$from, $to];
    }
}
