<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Filename : TimeExportController.php
 * License  : AGPL-3.0-or-later
 */

namespace App\Http\Controllers;

use App\Enums\TimeExport\TimeExportStatus;
use App\Models\{TimeExport, User};
use App\Services\TimeExport\{TimeExportException, TimeExportService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * UI für MVP-019 (ApprovedTimeExporter).
 *
 * Liste, Anlage, Vorschau, Download, Bereitstellung und Ablehnung von
 * Zeit-Exporten auf Basis genehmigter Monatsfreigaben.
 */
class TimeExportController extends Controller {
    private const ALLOWED_SORTS = ['period_year', 'profile', 'status', 'rows_count', 'created_at'];

    public function __construct(private readonly TimeExportService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', TimeExport::class);
        /** @var User $user */
        $user = Auth::user();

        $statusFilter = (string) $request->input('status', 'all');
        $profileFilter = (string) $request->input('profile', 'all');
        $yearFilter = $request->filled('year') ? (int) $request->input('year') : null;
        // Whitelist-Auflösung zentral (C21; Vollaudit 2026-07, N26) — bei
        // ungültigem Key fallen Key UND Richtung auf die Defaults zurück.
        [$sort, $dir] = \App\Support\SortableQuery::resolve($request, self::ALLOWED_SORTS, 'created_at');

        $query = TimeExport::query()
            ->where('organization_id', $user->organization_id)
            ->with(['creator', 'deliveredBy', 'scopeUser'])
            ->orderBy($sort, $dir)
            ->when($sort === 'period_year', fn($q) => $q->orderBy('period_month', $dir));

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($profileFilter !== '' && $profileFilter !== 'all') {
            $query->where('profile', $profileFilter);
        }
        if ($yearFilter !== null) {
            $query->where('period_year', $yearFilter);
        }

        $exports = $query->paginate(25)->withQueryString();

        return view('exports.index', [
            'exports' => $exports,
            'profiles' => $this->availableProfiles(),
            'statuses' => TimeExportStatus::cases(),
            'filters' => [
                'status' => $statusFilter,
                'profile' => $profileFilter,
                'year' => $yearFilter,
            ],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', TimeExport::class);
        /** @var User $user */
        $user = Auth::user();

        $now = now();

        return view('exports.create', [
            'profiles' => $this->availableProfiles(),
            'defaultYear' => (int) $now->copy()->subMonthNoOverflow()->format('Y'),
            'defaultMonth' => (int) $now->copy()->subMonthNoOverflow()->format('n'),
            'teamUsers' => User::query()
                ->where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TimeExport::class);
        /** @var User $user */
        $user = Auth::user();

        $profileKeys = array_keys($this->availableProfiles());

        $request->merge([
            'scope_user_id' => Sqid::decode(\App\Models\User::class, $request->input('scope_user_id')),
        ]);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2999'],
            'month' => ['required', 'integer', 'between:1,12'],
            'profile' => ['required', 'string', 'in:' . implode(',', $profileKeys)],
            'scope' => ['required', 'string', 'in:organization,user'],
            'scope_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
        ]);

        $org = $user->organization;
        if ($org === null) {
            abort(404);
        }

        /** @var 'organization'|'team'|'user' $scope */
        $scope = (string) $data['scope'];

        try {
            $export = $this->service->prepare(
                $org,
                (int) $data['year'],
                (int) $data['month'],
                (string) $data['profile'],
                $scope,
                isset($data['scope_user_id']) ? (int) $data['scope_user_id'] : null,
                null,
                $user,
            );

            $export = $this->service->build($export, $user);
        } catch (TimeExportException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('exports.show', $export)
            ->with('status', __('Export erstellt.'));
    }

    public function show(TimeExport $export): View {
        Gate::authorize('view', $export);
        $export->load(['lines.user', 'lines.surchargeRule', 'events.actor', 'creator', 'deliveredBy', 'scopeUser', 'supersededBy']);

        return view('exports.show', [
            'export' => $export,
        ]);
    }

    public function download(TimeExport $export): BinaryFileResponse {
        Gate::authorize('download', $export);
        /** @var User $user */
        $user = Auth::user();

        $path = $export->file_path;
        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $diskName = (string) config('exports.storage.disk', 'local');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            abort(404);
        }

        $this->service->recordDownload($export, $user);

        $filename = sprintf(
            '%s-%s.%s',
            $export->profile,
            $export->periodLabel(),
            $export->file_format ?? 'csv',
        );

        return response()->download($disk->path($path), $filename);
    }

    public function deliver(Request $request, TimeExport $export): RedirectResponse {
        Gate::authorize('deliver', $export);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $note = isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null;

        try {
            $this->service->markDelivered($export, $user, $note);
        } catch (TimeExportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Export als ausgeliefert markiert.'));
    }

    /**
     * Kostenstellen-Override je Zeile im Prüf-UI (Rang 35): nur im Status
     * ready; die Export-Datei wird neu gerendert (neuer Hash, auditiert).
     */
    public function updateLine(Request $request, TimeExport $export, \App\Models\TimeExportLine $line): RedirectResponse {
        Gate::authorize('deliver', $export);
        abort_unless((int) $line->time_export_id === (int) $export->id, 404);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'cost_center' => ['nullable', 'string', 'max:32'],
        ]);
        $costCenter = isset($data['cost_center']) && trim((string) $data['cost_center']) !== ''
            ? trim((string) $data['cost_center'])
            : null;

        try {
            $this->service->updateLineCostCenter($export, $line, $costCenter, $user);
        } catch (TimeExportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Kostenstelle aktualisiert — Export-Datei neu erzeugt.'));
    }

    public function reject(Request $request, TimeExport $export): RedirectResponse {
        Gate::authorize('reject', $export);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->service->reject($export, $user, (string) $data['note']);
        } catch (TimeExportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Export abgelehnt.'));
    }

    /**
     * Löschung mit Pflicht-Begründung (Vollaudit 2026-07, N6) — nur nicht
     * übergebene Läufe (TimeExportPolicy::delete), Spur im Audit-Protokoll.
     */
    public function destroy(Request $request, TimeExport $export): RedirectResponse {
        Gate::authorize('delete', $export);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->service->delete($export, (string) $data['note'], $user);
        } catch (TimeExportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('exports.index')->with('status', __('Export gelöscht — Begründung im Audit-Protokoll.'));
    }

    /**
     * @return array<string,string>
     */
    private function availableProfiles(): array {
        /** @var array<string,array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $out = [];
        foreach ($profiles as $key => $cfg) {
            $driver = $cfg['driver'] ?? null;
            if ($driver === null) {
                continue;
            }
            $label = isset($cfg['label']) && is_string($cfg['label']) ? $cfg['label'] : (string) $key;
            $out[(string) $key] = $label;
        }

        return $out;
    }
}
