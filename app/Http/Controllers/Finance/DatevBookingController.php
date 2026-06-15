<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ChartOfAccounts;
use App\Http\Controllers\Controller;
use App\Models\Finance\DatevBookingBatch;
use App\Models\{Organization, User};
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\{DatevBookingException, DatevBookingService, FinancialFormatsSupport};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3): Liste, Anlage
 * (Modal, Zeitraum + optionale Spesen), Vorschau mit Preflight, Finalisierung
 * und Download der erzeugten DATEV-V700-CSV. Statusmaschine und CSV-Erzeugung
 * laufen ausschließlich über den {@see DatevBookingService}; Autorisierung über
 * DatevBookingBatchPolicy (finance.booking.export; Konfiguration finance.config).
 * Modul-Gating module.finance über die finance.*-Routen.
 */
class DatevBookingController extends Controller {
    public function __construct(
        private readonly DatevBookingService $service,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', DatevBookingBatch::class);

        $batches = DatevBookingBatch::query()
            ->with('creator:id,name')
            ->withCount('sources')
            ->orderByDesc('created_at')
            ->paginate(25);

        // Kennzahl: buchungsreife, noch nicht exportierte Belege im laufenden Monat.
        $org = $this->organization();
        $period = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];
        $openCount = $org !== null
            ? $this->service->collectBookingReady($org, $period)->count()
            : 0;

        return view('finance.datev.index', [
            'batches' => $batches,
            'openCount' => $openCount,
            'importAvailable' => FinancialFormatsSupport::isAvailable(),
            'canCreate' => Gate::allows('create', DatevBookingBatch::class),
            'canConfigure' => Gate::allows('configure', DatevBookingBatch::class),
        ]);
    }

    /** Anlage-Dialog (Modal-Partial): Zeitraum + optionale Spesen-Auswahl. */
    public function create(): View {
        Gate::authorize('create', DatevBookingBatch::class);

        return view('finance.datev._form_dialog', [
            'defaultFrom' => now()->startOfMonth()->toDateString(),
            'defaultTo' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', DatevBookingBatch::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'include_expenses' => ['nullable', 'boolean'],
        ]);

        $org = $this->organization();
        if ($org === null) {
            return back()->withErrors(['from' => __('finance.datev.error.no_organization')])->withInput();
        }

        $config = DatevBookingConfig::forOrganization($org);
        $period = ['from' => $data['from'], 'to' => $data['to']];
        $sources = $this->service->collectBookingReady($org, $period, $request->boolean('include_expenses'));

        try {
            $batch = $this->service->createDraft($org, $period, $sources, $config, $this->actor());
        } catch (DatevBookingException $e) {
            return back()->withErrors(['from' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('finance.datev.show', $batch)
            ->with('success', __('finance.datev.flash.created'));
    }

    /** Vorschau: Buchungssätze-Tabelle, Summen, Preflight-Warnungen. */
    public function show(DatevBookingBatch $batch): View {
        Gate::authorize('view', $batch);

        $batch->load(['creator:id,name', 'sources', 'events' => fn($q) => $q->orderBy('id')]);

        $org = $this->organization();
        $config = DatevBookingConfig::forOrganization($org);

        // Preflight aus den persistierten Quell-Snapshots ableiten.
        $preflight = $this->preflightFromSources($batch, $config);

        return view('finance.datev.show', [
            'batch' => $batch,
            'preflight' => $preflight,
            'importAvailable' => FinancialFormatsSupport::isAvailable(),
            'canFinalize' => Gate::allows('finalize', $batch),
        ]);
    }

    /** draft → exported: CSV erzeugen, Hash, Datei, Quellen als übergeben markieren. */
    public function finalize(DatevBookingBatch $batch): RedirectResponse {
        Gate::authorize('finalize', $batch);

        if (! FinancialFormatsSupport::isAvailable()) {
            return back()->withErrors(['status' => __('finance.datev.error.unavailable')]);
        }

        $config = DatevBookingConfig::forOrganization($this->organization());
        $preflight = $this->preflightFromSources($batch, $config);
        if ($preflight['errors'] !== []) {
            return back()->withErrors(['status' => __('finance.datev.error.preflight_failed')]);
        }

        try {
            $this->service->finalize($batch, $config, $this->actor());
        } catch (DatevBookingException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('finance.datev.flash.finalized'));
    }

    /** Download der erzeugten CSV (Gate-geprüft, pfadsicher). */
    public function download(DatevBookingBatch $batch): StreamedResponse {
        Gate::authorize('download', $batch);

        $path = (string) $batch->file_path;
        abort_unless($path !== '' && str_starts_with($path, DatevBookingService::BASE_PATH . '/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(DatevBookingService::DISK);
        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($path), ['Content-Type' => 'text/csv']);
    }

    /** Buchhaltungs-Konfiguration (Konten/Steuerschlüssel/Beraternummer). */
    public function editConfig(): View {
        Gate::authorize('configure', DatevBookingBatch::class);

        $org = $this->organization();
        $settings = is_array($org?->settings) ? $org->settings : [];
        $stored = is_array($settings['datev'] ?? null) ? $settings['datev'] : [];

        return view('finance.datev.config', [
            'config' => DatevBookingConfig::forOrganization($org),
            'stored' => $stored,
            'chartOptions' => ChartOfAccounts::cases(),
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse {
        Gate::authorize('configure', DatevBookingBatch::class);

        $data = $request->validate([
            'datev' => ['required', 'array'],
            'datev.advisor_number' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'datev.client_number' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'datev.skr' => ['nullable', 'string', 'in:' . implode(',', array_map(fn(ChartOfAccounts $c): string => $c->value, ChartOfAccounts::cases()))],
            'datev.account_length' => ['nullable', 'integer', 'min:4', 'max:8'],
            'datev.revenue_account' => ['nullable', 'string', 'max:12'],
            'datev.revenue_account_tax_free' => ['nullable', 'string', 'max:12'],
            'datev.debtor_base' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'datev.tax_keys' => ['nullable', 'array'],
            'datev.tax_keys.*' => ['nullable', 'string', 'max:4'],
            'datev.finalize' => ['nullable', 'in:0,1'],
            'datev.encoding' => ['nullable', 'string', 'in:UTF-8,ISO-8859-1'],
        ]);

        $org = $this->organization();
        if ($org === null) {
            return back()->withErrors(['datev' => __('finance.datev.error.no_organization')]);
        }

        $settings = is_array($org->settings) ? $org->settings : [];
        $existing = is_array($settings['datev'] ?? null) ? $settings['datev'] : [];

        $clean = $this->stripEmpty($data['datev']);
        $next = array_replace_recursive($existing, $clean);
        $next = $this->stripEmpty($next);

        if ($next === []) {
            unset($settings['datev']);
        } else {
            $settings['datev'] = $next;
        }

        $org->settings = $settings;
        $org->save();

        return redirect()
            ->route('finance.datev.config')
            ->with('success', __('finance.datev.flash.config_saved'));
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * Preflight aus den persistierten Quell-Snapshots: prüft Stammdaten und
     * Konsistenz, ohne die fachlichen Quellen erneut zu laden.
     *
     * @return array{errors: list<string>, warnings: list<string>, total: float, count: int}
     */
    private function preflightFromSources(DatevBookingBatch $batch, DatevBookingConfig $config): array {
        $batch->loadMissing('sources');

        $errors = [];
        $warnings = [];
        $total = 0.0;

        if (! $config->hasClientNumbers()) {
            $errors[] = (string) __('finance.datev.preflight.missing_client_numbers');
        }

        if ($batch->sources->isEmpty()) {
            $errors[] = (string) __('finance.datev.preflight.no_sources');
        }

        foreach ($batch->sources as $source) {
            $amount = (float) $source->amount;
            $total += $source->soll_haben === 'H' ? -$amount : $amount;

            if ($source->tax_key === null || $source->tax_key === '') {
                $warnings[] = (string) __('finance.datev.preflight.unknown_tax_key', [
                    'ref' => (string) $source->document_ref,
                    'rate' => '—',
                ]);
            }
            if (trim((string) $source->debtor_account) === '' || $source->debtor_account === '0') {
                $errors[] = (string) __('finance.datev.preflight.missing_debtor', ['ref' => (string) $source->document_ref]);
            }
            if (trim((string) $source->revenue_account) === '') {
                $errors[] = (string) __('finance.datev.preflight.missing_revenue', ['ref' => (string) $source->document_ref]);
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'total' => round($total, 2),
            'count' => $batch->sources->count(),
        ];
    }

    /**
     * Entfernt leere Werte rekursiv (analog OrganizationController::stripEmpty).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stripEmpty(array $values): array {
        $out = [];
        foreach ($values as $k => $v) {
            if (is_array($v)) {
                $cleaned = $this->stripEmpty($v);
                if ($cleaned !== []) {
                    $out[$k] = $cleaned;
                }

                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    private function organization(): ?Organization {
        if (app()->bound('currentOrganization')) {
            /** @var Organization|null $org */
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return $org;
            }
        }

        $user = Auth::user();
        $orgId = $user->organization_id ?? null;

        return $orgId !== null ? Organization::query()->find($orgId) : null;
    }

    private function actor(): ?User {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
