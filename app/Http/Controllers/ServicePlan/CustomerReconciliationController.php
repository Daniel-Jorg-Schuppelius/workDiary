<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerReconciliationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\ServicePlan;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\ServicePlan\Import\{CustomerListReconciler, ServicePlanTableReader};
use App\Support\ErrorText;
use App\Support\Toolkit\CsvFacade;
use Illuminate\Http\{RedirectResponse, Request, Response, UploadedFile};
use Illuminate\Support\Facades\{Gate, Session, Storage};
use Illuminate\View\View;
use RuntimeException;

/**
 * Abgleich des Kundenstamms gegen eine Kundenliste aus einem Fremdsystem
 * (Feature 199, MVP-1640; Spaltenvorlage DATEV-Mandantenexport): Vorschau mit
 * neuen, umbenannten und fehlenden Kunden; übernommen wird nur, was eine
 * Person einzeln ankreuzt.
 */
class CustomerReconciliationController extends Controller {
    use ResolvesCurrentOrganization;

    private const string SESSION_KEY = 'service_plan_reconcile';

    private const string DIRECTORY = 'service-plan-imports';

    private const int MAX_KB = 5120;

    public function __construct(
        private readonly ServicePlanTableReader $reader,
        private readonly CustomerListReconciler $reconciler,
    ) {}

    public function create(): View {
        Gate::authorize('manage', CustomerServiceWaiver::class);

        return view('service-plan.reconcile.create');
    }

    /** Spaltenvorlage nach dem DATEV-Mandantenexport mit erfundenen Beispielen. */
    public function template(): Response {
        Gate::authorize('manage', CustomerServiceWaiver::class);

        return response(CsvFacade::buildCsv(['Mandantennummer', 'Name', 'Ort'], [
            ['Mandantennummer' => '10001', 'Name' => 'Beispiel Bäckerei GmbH', 'Ort' => 'Musterstadt'],
            ['Mandantennummer' => '10002', 'Name' => 'Erika Mustermann', 'Ort' => 'Beispielhausen'],
        ]), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kundenabgleich-vorlage.csv"',
        ]);
    }

    public function preview(Request $request): View|RedirectResponse {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:' . self::MAX_KB]]);

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

            return back()->withErrors(['file' => ErrorText::for($e)]);
        }
        Session::put(self::SESSION_KEY, ['path' => $path, 'extension' => $extension, 'organization_id' => (int) $organization->id]);

        return view('service-plan.reconcile.preview', [
            'analysis' => $this->reconciler->analyze($organization, $table),
            'filename' => $file->getClientOriginalName(),
            'mayCreate' => Gate::allows('create', Customer::class),
            'mayRename' => $this->authUser()->can(Permission::CustomerUpdate->value),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('manage', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'create_numbers' => ['nullable', 'array', 'max:5000'],
            'create_numbers.*' => ['string', 'max:64'],
            'rename_numbers' => ['nullable', 'array', 'max:5000'],
            'rename_numbers.*' => ['string', 'max:64'],
        ]);
        $session = Session::get(self::SESSION_KEY);
        if (! is_array($session) || ! is_string($session['path'] ?? null) || (int) ($session['organization_id'] ?? 0) !== (int) $organization->id || ! Storage::disk('local')->exists($session['path'])) {
            return redirect()->route('service-plan.reconcile.create')->withErrors(['file' => __('service_plan.import.error.expired')]);
        }

        try {
            $table = $this->reader->read(Storage::disk('local')->path($session['path']), (string) ($session['extension'] ?? 'csv'));
            $result = $this->reconciler->apply(
                $organization,
                $table,
                array_values(array_map('strval', $data['create_numbers'] ?? [])),
                array_values(array_map('strval', $data['rename_numbers'] ?? [])),
                $this->authUser(),
                Gate::allows('create', Customer::class),
                $this->authUser()->can(Permission::CustomerUpdate->value),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('service-plan.reconcile.create')->withErrors(['file' => ErrorText::for($e)]);
        } finally {
            $this->discard();
        }

        return redirect()->route('service-plan.reconcile.create')->with('success', __('service_plan.reconcile.flash.done', $result));
    }

    private function discard(): void {
        $previous = Session::pull(self::SESSION_KEY);
        if (is_array($previous) && is_string($previous['path'] ?? null)) {
            Storage::disk('local')->delete($previous['path']);
        }
    }
}
