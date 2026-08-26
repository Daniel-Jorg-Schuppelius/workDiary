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

use App\Enums\Finance\GobdExportStatus;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Jobs\Finance\GobdExportJob;
use App\Models\GobdExport;
use App\Services\Finance\GdpduExportService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132): Auswahl von Zeitraum
 * und Datenbereichen mit Preflight (Vollständigkeitswarnungen), Beauftragung
 * des Paketbaus und Download. Erzeugung + revisionssicherer Nachweis laufen
 * über den {@see GdpduExportService}. Recht `finance.gobd.export`; Modul-Gating
 * `module.finance` automatisch über die `finance.*`-Routen.
 *
 * Seit MVP-722 gibt es KEINEN synchronen Pfad mehr (Vollscan 2026-08-23, A16):
 * `export()` reiht nur noch ein. Eine Schwelle „kleine Zeiträume synchron"
 * hätte zwei Wege durch denselben, hash-relevanten Paketbau geführt — und der
 * reproduzierbare Paket-Hash ist das ganze Versprechen dieses Features. Ein
 * Prüfungspaket ist nie interaktiv-eilig; der Nachweis in der Liste zeigt den
 * Lauf, der Download holt ihn ab.
 */
class GobdExportController extends Controller {
    use ResolvesCurrentOrganization;

    use ResolvesGlobalDateRange;

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

    /** Auswahl-Dialog (Zeitraum + Datenbereiche); wird per data-entry-modal-trigger geladen. */
    public function check(Request $request): View {
        Gate::authorize('viewAny', GobdExport::class);

        [$from, $to] = $this->period($request);

        return view('finance.gobd._check_dialog', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sections' => $this->service->availableSections(),
            'selected' => array_values(array_filter((array) $request->input('sections', $this->service->availableSections()), 'is_string')),
        ]);
    }

    /** Reiht den Paketbau ein; das Ergebnis erscheint als Nachweis in der Liste. */
    public function export(Request $request): RedirectResponse {
        Gate::authorize('viewAny', GobdExport::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['string'],
            'encoding' => ['sometimes', 'string', Rule::in($this->service->availableEncodings())],
        ]);

        $export = $this->service->register(
            $this->currentOrganization(),
            Carbon::parse((string) $data['from']),
            Carbon::parse((string) $data['to']),
            array_values(array_filter((array) ($data['sections'] ?? $this->service->availableSections()), 'is_string')),
            (string) ($data['encoding'] ?? GdpduExportService::ENCODING_CP1252),
            Auth::user(),
            GobdExportStatus::Queued,
        );

        GobdExportJob::dispatch($export->id);

        return redirect()
            ->route('finance.gobd.index', ['from' => $data['from'], 'to' => $data['to']])
            ->with('status', __('gobd.queued'));
    }

    /** Herunterladen eines fertigen Pakets — Recht wie die Erzeugung, mit Audit. */
    public function download(GobdExport $export): BinaryFileResponse {
        Gate::authorize('viewAny', GobdExport::class);
        abort_unless($export->organization_id === $this->currentOrganization()->id, 404);

        $path = $export->packagePath();
        abort_unless($export->status->isDownloadable() && $path !== null && is_file($path), 404);

        // Die Datenträgerüberlassung verlässt das Haus — wer sie abgeholt hat,
        // gehört in die Auditspur (GoBD).
        $export->audit('gobd.downloaded', ['package_sha256' => $export->package_sha256]);

        return response()->download($path, $export->downloadName(), ['Content-Type' => 'application/zip']);
    }

    /** @return array{0: Carbon, 1: Carbon} Vorjahr als Standard-Prüfungszeitraum. */
    private function period(Request $request): array {
        // Guard statt Roh-Parse (Vollscan 2026-08-23, B10).
        [$from, $to] = $this->resolveRangeWithDefault($request, static fn (): array => [
            \Carbon\CarbonImmutable::now()->subYear()->startOfYear(),
            \Carbon\CarbonImmutable::now()->subYear()->endOfYear(),
        ]);

        return [Carbon::instance($from), Carbon::instance($to)];
    }
}
