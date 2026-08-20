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

use App\Enums\Finance\{ChartOfAccounts, DatevBatchStatus};
use App\Http\Controllers\Controller;
use App\Models\{ExpenseCategory, Organization, User};
use App\Models\Finance\DatevBookingBatch;
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\{DatevBookingException, DatevBookingService, FinancialFormatsSupport};
use App\Support\Sqid;
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

        // Kennzahlen der KPI-Zeile: Stapelbestand nach Status + Exportsumme des Jahres.
        $draftCount = DatevBookingBatch::query()->where('status', DatevBatchStatus::Draft)->count();
        $exportedCount = DatevBookingBatch::query()->where('status', DatevBatchStatus::Exported)->count();
        $exportedTotalYear = (float) DatevBookingBatch::query()
            ->where('status', DatevBatchStatus::Exported)
            ->whereYear('finalized_at', now()->year)
            ->sum('total_amount');

        return view('finance.datev.index', [
            'batches' => $batches,
            'openCount' => $openCount,
            'draftCount' => $draftCount,
            'exportedCount' => $exportedCount,
            'exportedTotalYear' => $exportedTotalYear,
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
            'include_reversals' => ['nullable', 'boolean'],
        ]);

        $org = $this->organization();
        if ($org === null) {
            return back()->withErrors(['from' => __('finance.datev.error.no_organization')])->withInput();
        }

        $config = DatevBookingConfig::forOrganization($org);
        $period = ['from' => $data['from'], 'to' => $data['to']];
        $sources = $this->service->collectBookingReady(
            $org,
            $period,
            $request->boolean('include_expenses'),
            $request->boolean('include_reversals'),
        );

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
            'canReshape' => Gate::allows('reshape', $batch),
        ]);
    }

    /**
     * Teilauswahl (MVP-334): entfernt markierte Quellsätze aus einem DRAFT-
     * Stapel — der Zuschnitt wird am Exportnachweis persistiert
     * (selection_mode = manual + Hash-Ketten-Event), entfernte Quellen sind
     * sofort wieder buchungsreif.
     */
    public function removeSources(Request $request, DatevBookingBatch $batch): RedirectResponse {
        Gate::authorize('reshape', $batch);

        $data = $request->validate([
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => ['required', 'string'],
        ]);

        try {
            // Sqids aus dem Formular (W3.3); der Service prueft die Bindung an den Stapel.
            $sourceIds = array_values(array_filter(array_map(
                static fn (string $v): ?int => \App\Support\Sqid::decodeOrNumeric(\App\Models\Finance\DatevBookingSource::class, $v),
                $data['sources'],
            )));
            $this->service->removeSources($batch, $sourceIds, $this->actor());
        } catch (DatevBookingException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('finance.datev.flash.sources_removed'));
    }

    /** Draft verwerfen (SoftDelete) — gibt die Quellen wieder frei (MVP-334). */
    public function destroy(DatevBookingBatch $batch): RedirectResponse {
        Gate::authorize('reshape', $batch);

        try {
            $this->service->discardDraft($batch, $this->actor());
        } catch (DatevBookingException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('finance.datev.index')
            ->with('success', __('finance.datev.flash.discarded'));
    }

    /** draft → exported: CSV erzeugen, Hash, Datei, Quellen als übergeben markieren. */
    public function finalize(DatevBookingBatch $batch): RedirectResponse {
        Gate::authorize('finalize', $batch);

        if (! FinancialFormatsSupport::isAvailable()) {
            return back()->withErrors(['status' => FinancialFormatsSupport::unavailableMessage('finance.datev.error.unavailable')]);
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
            // MVP-334: Aufwands-/Vorsteuerkonto je Spesenkategorie (org-scoped
            // über das BelongsToOrganization-Scope der Kategorie).
            'expenseCategories' => ExpenseCategory::query()->active()->ordered()->get(),
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
            // MVP-334: Spesenkategorie-Mapping — Schlüssel sind Kategorie-Sqids.
            'datev.expense_accounts' => ['nullable', 'array'],
            'datev.expense_accounts.*.account' => ['nullable', 'string', 'max:12'],
            'datev.expense_accounts.*.tax_key' => ['nullable', 'string', 'max:4'],
        ]);

        $org = $this->organization();
        if ($org === null) {
            return back()->withErrors(['datev' => __('finance.datev.error.no_organization')]);
        }

        // Sqid-Schlüssel → Kategorie-IDs auflösen; nur org-sichtbare Kategorien
        // (BelongsToOrganization-Scope) werden übernommen.
        if (isset($data['datev']['expense_accounts']) && is_array($data['datev']['expense_accounts'])) {
            $mapped = [];
            foreach ($data['datev']['expense_accounts'] as $sqid => $entry) {
                $categoryId = Sqid::decode(ExpenseCategory::class, (string) $sqid);
                if ($categoryId === null || ExpenseCategory::query()->whereKey($categoryId)->doesntExist()) {
                    continue;
                }
                $mapped[(string) $categoryId] = [
                    'account' => trim((string) ($entry['account'] ?? '')),
                    'tax_key' => trim((string) ($entry['tax_key'] ?? '')),
                ];
            }
            $data['datev']['expense_accounts'] = $mapped;
        }

        $settings = is_array($org->settings) ? $org->settings : [];
        $existing = is_array($settings['datev'] ?? null) ? $settings['datev'] : [];

        $clean = $this->stripEmpty($data['datev']);
        $next = array_replace_recursive($existing, $clean);
        $next = $this->stripEmpty($next);
        // Gelöschte Mapping-Einträge nicht aus dem Bestand „wiederbeleben":
        // das Mapping wird als Ganzes ersetzt statt rekursiv gemerged.
        if (array_key_exists('expense_accounts', $data['datev'])) {
            $cleanMapping = $this->stripEmpty(is_array($data['datev']['expense_accounts']) ? $data['datev']['expense_accounts'] : []);
            if ($cleanMapping === []) {
                unset($next['expense_accounts']);
            } else {
                $next['expense_accounts'] = $cleanMapping;
            }
        }

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

    /**
     * EXTF-Stammdatenexport Kategorie 16 (Nachtrag 045a): Debitoren aus dem
     * Kundenstamm als DATEV-CSV (CP1252) — Konto folgt der
     * Buchungsstapel-Logik (debtor_no bzw. Nummernkreis-Basis + Kunden-ID).
     */
    public function exportDebtors(): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('create', DatevBookingBatch::class);

        $org = $this->organization();
        abort_unless($org !== null, 404);

        $config = DatevBookingConfig::forOrganization($org);
        if (! $config->hasClientNumbers()) {
            return redirect()->route('finance.datev.config')
                ->with('error', __('finance.datev.preflight.missing_client_numbers'));
        }

        $result = app(\App\Services\Finance\Datev\DatevMasterDataExporter::class)->generateDebtors($org, $config);

        $org->audit('finance.datev.debtors_exported', ['count' => $result['count']]);

        $name = 'EXTF_Debitoren_' . now()->format('Ymd_His') . '.csv';

        return response($result['csv'], 200, [
            'Content-Type' => 'text/csv; charset=windows-1252',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * EXTF-Sachkonten-Beistellung Kategorie 20 (MVP-334): alle im
     * Buchungsstapel verwendeten Sachkonten (Erlöskonten + Aufwandskonten je
     * Spesenkategorie) als Kontenbeschriftungs-CSV.
     */
    public function exportGlAccounts(): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('create', DatevBookingBatch::class);

        $org = $this->organization();
        abort_unless($org !== null, 404);

        $config = DatevBookingConfig::forOrganization($org);
        if (! $config->hasClientNumbers()) {
            return redirect()->route('finance.datev.config')
                ->with('error', __('finance.datev.preflight.missing_client_numbers'));
        }

        $result = app(\App\Services\Finance\Datev\DatevMasterDataExporter::class)->generateGlAccounts($org, $config);

        $org->audit('finance.datev.gl_accounts_exported', ['count' => $result['count']]);

        $name = 'EXTF_Sachkonten_' . now()->format('Ymd_His') . '.csv';

        return response($result['csv'], 200, [
            'Content-Type' => 'text/csv; charset=windows-1252',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }
}
