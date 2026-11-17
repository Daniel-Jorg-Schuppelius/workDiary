<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\ServicePlan;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Services\ServicePlan\Import\{ServicePlanListImporter, ServicePlanTableReader};
use App\Services\ServicePlan\ServicePlanCatalog;
use App\Support\ErrorText;
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request, Response, UploadedFile};
use Illuminate\Support\Facades\{Gate, Session, Storage};
use Illuminate\View\View;
use RuntimeException;

/**
 * Listen in den Leistungsplan übernehmen (Feature 199, MVP-1640): Datei
 * (CSV/Excel) hochladen, Vorschau mit Zuordnung je Zeile, dann übernehmen.
 * Die Datei liegt zwischen Vorschau und Übernahme auf der privaten Disk; die
 * Übernahme prüft sie neu, statt der Vorschau zu vertrauen.
 */
class ServicePlanImportController extends Controller {
    use ResolvesCurrentOrganization;

    private const string SESSION_KEY = 'service_plan_import';

    private const string DIRECTORY = 'service-plan-imports';

    /** Größte Datei in KB. */
    private const int MAX_KB = 5120;

    public function __construct(
        private readonly ServicePlanTableReader $reader,
        private readonly ServicePlanListImporter $importer,
        private readonly ServicePlanCatalog $catalog,
    ) {}

    public function create(): View {
        Gate::authorize('manage', CustomerServiceWaiver::class);

        return view('service-plan.import.create', [
            'services' => $this->catalog->services((int) $this->currentOrganization()->id),
            'year' => CarbonImmutable::today()->year,
        ]);
    }

    /** Vorlage mit erfundenen Beispielzeilen: Jahresliste oder Periodenliste. */
    public function template(string $kind): Response {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        if ($kind === 'periodenliste') {
            $headers = ['Kundennummer', 'Leistung', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez', 'Bemerkung'];
            $rows = [
                ['Kundennummer' => '10001', 'Leistung' => 'Monatsbetreuung', 'Jan' => 'erledigt', 'Feb' => 'erledigt', 'Mär' => 'in Arbeit', 'Bemerkung' => 'Beispiel'],
                ['Kundennummer' => '10002', 'Leistung' => 'Monatsbetreuung', 'Jan' => 'erledigt', 'Feb' => 'entfällt', 'Mär' => 'offen', 'Bemerkung' => ''],
            ];
        } else {
            $headers = ['Kundennummer', 'Leistung', 'Stand', 'Bemerkung', 'Unterlagen da'];
            $rows = [
                ['Kundennummer' => '10001', 'Leistung' => 'Jahresabschluss', 'Stand' => 'in Arbeit', 'Bemerkung' => 'Beispiel', 'Unterlagen da' => '15.02.2026'],
                ['Kundennummer' => '10002', 'Leistung' => 'Jahresabschluss', 'Stand' => 'entfällt', 'Bemerkung' => '', 'Unterlagen da' => ''],
            ];
        }

        return response(CsvFacade::buildCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leistungsplan-' . $kind . '.csv"',
        ]);
    }

    public function preview(Request $request): View|RedirectResponse {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:' . self::MAX_KB],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'service' => ['nullable', 'string', 'max:100'],
            'create_orders' => ['nullable', 'boolean'],
        ]);
        $service = ServiceKey::fromParam($data['service'] ?? null);
        if (($data['service'] ?? '') !== '' && $service === null) {
            return back()->withErrors(['service' => __('service_plan.error.unknown_service')])->withInput();
        }

        $this->discard();
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return back()->withErrors(['file' => __('service_plan.import.error.unreadable')]);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->store(self::DIRECTORY, 'local');
        if (! is_string($path)) {
            return back()->withErrors(['file' => __('service_plan.import.error.unreadable')]);
        }
        try {
            $table = $this->reader->read(Storage::disk('local')->path($path), $extension);
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => ErrorText::for($e)])->withInput();
        }

        $analysis = $this->importer->analyze($organization, $table, (int) $data['year'], $service, CarbonImmutable::today());
        Session::put(self::SESSION_KEY, [
            'kind' => $analysis['kind'],
            'path' => $path,
            'extension' => $extension,
            'filename' => $file->getClientOriginalName(),
            'year' => (int) $data['year'],
            'service' => $service?->param(),
            'create_orders' => $request->boolean('create_orders'),
            'organization_id' => (int) $organization->id,
        ]);

        return view('service-plan.import.preview', [
            'analysis' => $analysis,
            'filename' => $file->getClientOriginalName(),
            'year' => (int) $data['year'],
            'serviceLabel' => $service !== null ? $this->catalog->label((int) $organization->id, $service) : null,
            'createOrders' => $request->boolean('create_orders'),
        ]);
    }

    public function store(): RedirectResponse {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $import = Session::get(self::SESSION_KEY);
        if (! is_array($import) || ! is_string($import['path'] ?? null) || (int) ($import['organization_id'] ?? 0) !== (int) $organization->id || ! Storage::disk('local')->exists($import['path'])) {
            return redirect()->route('service-plan.import.create')->withErrors(['file' => __('service_plan.import.error.expired')]);
        }

        try {
            $table = $this->reader->read(Storage::disk('local')->path($import['path']), (string) ($import['extension'] ?? 'csv'));
            $counts = $this->importer->apply(
                $organization,
                $table,
                (int) $import['year'],
                ServiceKey::fromParam($import['service'] ?? null),
                (bool) ($import['create_orders'] ?? false),
                $this->authUser(),
                CarbonImmutable::today(),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('service-plan.import.create')->withErrors(['file' => ErrorText::for($e)]);
        } finally {
            $this->discard();
        }

        $route = ($import['kind'] ?? null) === ServicePlanListImporter::KIND_PERIODS ? 'service-plan.periods.index' : 'service-plan.index';

        return redirect()->route($route, ['year' => (int) $import['year']])->with('success', __('service_plan.import.flash.done', $counts));
    }

    /** Hochgeladene Datei und Sitzungsangaben einer vorigen Vorschau entfernen. */
    private function discard(): void {
        $previous = Session::pull(self::SESSION_KEY);
        if (is_array($previous) && is_string($previous['path'] ?? null)) {
            Storage::disk('local')->delete($previous['path']);
        }
    }
}
