<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceTransferController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Customer, MaterialUsage, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Services\Finance\{BillingModeResolver, BillingTransferException, BillingTransferService};
use App\Services\Finance\Targets\{FacturationTargetRegistry, FileTarget};
use App\Services\Invoicing\BillableTimeAggregator;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Faktura-Übergabe (Feature 045, Teil B): Liste, Anlage, Vorschau und
 * Ausführung von Übergabenachweisen an Lexoffice bzw. als Datei-Paket.
 * Statusmaschine über {@see BillingTransferService}, Ziele über
 * {@see FacturationTargetRegistry}.
 */
class FinanceTransferController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly BillingTransferService $service,
        private readonly BillingModeResolver $modeResolver,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', BillingTransfer::class);

        $filters = [
            'customer' => (string) $request->query('customer', ''),
            'channel' => (string) $request->query('channel', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];

        $customerId = Sqid::decode(Customer::class, $filters['customer']);

        $query = BillingTransfer::query()
            ->with(['customer:id,name', 'creator:id,name'])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when(TransferChannel::tryFrom($filters['channel']) !== null, fn($q) => $q->where('channel', $filters['channel']))
            ->when(TransferStatus::tryFrom($filters['status']) !== null, fn($q) => $q->where('status', $filters['status']))
            ->orderByDesc('created_at');

        // Globaler Header-Zeitraum, Überlappung mit Leistungszeitraum (offene Grenzen = Treffer).
        $range = $this->globalDateRange();
        $query->where(fn($q) => $q->whereNull('period_to')->orWhereDate('period_to', '>=', $range['from']->toDateString()));
        $query->where(fn($q) => $q->whereNull('period_from')->orWhereDate('period_from', '<=', $range['to']->toDateString()));

        $hasActiveFilters = $customerId !== null
            || TransferChannel::tryFrom($filters['channel']) !== null
            || TransferStatus::tryFrom($filters['status']) !== null;

        return view('finance.transfers.index', [
            'transfers' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'canCreate' => Gate::allows('create', [BillingTransfer::class, TransferChannel::Time])
                || Gate::allows('create', [BillingTransfer::class, TransferChannel::Material]),
        ]);
    }

    /**
     * Anlage-Dialog (Modal-Partial). Optionaler ?customer={sqid} belegt das
     * Ziel aus dem effektiven billing_mode vor.
     */
    public function create(Request $request): View {
        $allowedChannels = $this->allowedChannels();
        abort_if($allowedChannels === [], 403);

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'billing_mode', 'organization_id']);

        $selected = null;
        $rawCustomer = (string) $request->query('customer', '');
        if ($rawCustomer !== '') {
            $selectedId = Sqid::decode(Customer::class, $rawCustomer);
            $selected = $selectedId !== null ? $customers->firstWhere('id', $selectedId) : null;
            abort_if($selected === null, 404);
        }

        $mode = $selected !== null ? $this->modeResolver->effectiveFor($selected) : null;

        return view('finance.transfers._form_dialog', [
            'customers' => $customers,
            'selectedCustomer' => $selected,
            'selectedMode' => $mode,
            'allowedChannels' => $allowedChannels,
            'allowedTargets' => $mode !== null ? self::allowedTargetsFor($mode) : [TransferTarget::Lexoffice, TransferTarget::File],
            'defaultTarget' => $mode !== null ? self::defaultTargetFor($mode) : TransferTarget::File,
            'showDatevHint' => $mode === BillingMode::Datev,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $customerId = Sqid::decodeOrNumeric(Customer::class, (string) $request->input('customer_id'));
        $request->merge(['customer_id' => $customerId]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'channel' => ['required', 'string', Rule::enum(TransferChannel::class)],
            'target' => ['required', 'string', Rule::enum(TransferTarget::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $channel = TransferChannel::from($data['channel']);
        Gate::authorize('create', [BillingTransfer::class, $channel]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);

        // Ziel gegen effektiven Fakturierungsweg prüfen (Lexoffice nur bei Lexoffice-Hoheit).
        $target = TransferTarget::from($data['target']);
        $mode = $this->modeResolver->effectiveFor($customer);
        if (! in_array($target, self::allowedTargetsFor($mode), true)) {
            return back()->withErrors([
                'target' => (string) __('finance.error.target_not_allowed', ['mode' => $mode->label()]),
            ])->withInput();
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $transfer = $this->service->createDraft(
                $customer,
                $channel,
                $target,
                ['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null],
                null,
                $actor,
            );
        } catch (BillingTransferException $e) {
            return back()->withErrors(['from' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('finance.transfers.show', $transfer)
            ->with('success', __('finance.flash.created'));
    }

    /** Vorschau: entstehende Positionen (Zeit-Blöcke bzw. Material) + Quellen. */
    public function show(BillingTransfer $transfer): View {
        Gate::authorize('view', $transfer);

        $transfer->load(['customer:id,name,currency,hourly_rate', 'creator:id,name', 'externalReference', 'events' => fn($q) => $q->orderBy('id')]);
        $transfer->items->loadMorph('source', [
            TimeEntry::class => ['project:id,name', 'user:id,name'],
            MaterialUsage::class => ['timesheet:id,work_date,project_id', 'timesheet.project:id,name'],
        ]);

        return view('finance.transfers.show', [
            'transfer' => $transfer,
            'positions' => $this->previewPositions($transfer),
        ]);
    }

    /** draft → confirmed bzw. failed → confirmed (erneut versuchen). */
    public function confirm(BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('confirm', $transfer);

        try {
            $this->service->confirm($transfer, $this->actor());
        } catch (BillingTransferException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('finance.flash.confirmed'));
    }

    /**
     * confirmed → Ziel-Adapter ausführen: Erfolg ⇒ markTransferred (Quellen
     * verbraucht), Fehler ⇒ markFailed mit Meldung (Quellen bleiben frei,
     * Retry über confirm()).
     */
    public function execute(BillingTransfer $transfer, FacturationTargetRegistry $targets): RedirectResponse {
        Gate::authorize('markTransferred', $transfer);

        // Guard VOR Ziel-Aufruf: kein Remote-Entwurf aus unbestätigtem Transfer.
        if ($transfer->status !== TransferStatus::Confirmed) {
            return back()->withErrors([
                'status' => (string) __('finance.error.illegal_transition', [
                    'from' => $transfer->status->label(),
                    'to' => TransferStatus::Transferred->label(),
                ]),
            ]);
        }

        try {
            $result = $targets->for($transfer->target)->transfer($transfer);
        } catch (Throwable $e) {
            $this->service->markFailed($transfer, mb_substr($e->getMessage(), 0, 1000), $this->actor());

            return back()->withErrors(['transfer' => __('finance.flash.failed') . ' ' . $e->getMessage()]);
        }

        $this->service->markTransferred($transfer, $result->externalReference, $result->filePath, $this->actor());

        return back()->with('success', __('finance.flash.transferred'));
    }

    /** draft|confirmed → voided (Quellen wieder frei). */
    public function void(BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('void', $transfer);

        try {
            $this->service->void($transfer, $this->actor());
        } catch (BillingTransferException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('finance.transfers.index')
            ->with('success', __('finance.flash.voided'));
    }

    /** Download des Datei-Übergabepakets (Gate-geprüft, Pfad-sicher). */
    public function download(BillingTransfer $transfer): StreamedResponse {
        Gate::authorize('view', $transfer);

        $path = (string) $transfer->file_path;
        // Pfad-Härtung: nur Finance-Export-Verzeichnis, keine Traversal-Segmente (defensiv, obwohl serverseitig gesetzt).
        abort_unless($path !== '' && str_starts_with($path, FileTarget::BASE_PATH . '/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(FileTarget::DISK);
        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($path), ['Content-Type' => 'text/csv']);
    }

    // ── intern ─────────────────────────────────────────────────────────

    private function actor(): ?User {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /** @return list<TransferChannel> */
    private function allowedChannels(): array {
        return array_values(array_filter(
            TransferChannel::cases(),
            fn(TransferChannel $c) => Gate::allows('create', [BillingTransfer::class, $c]),
        ));
    }

    /**
     * Zulässige Ziele je Fakturierungsweg: Lexoffice nur bei Lexoffice-Hoheit,
     * das Datei-Paket steht immer offen.
     *
     * @return list<TransferTarget>
     */
    public static function allowedTargetsFor(BillingMode $mode): array {
        return match ($mode) {
            BillingMode::Lexoffice => [TransferTarget::Lexoffice, TransferTarget::File],
            BillingMode::OrgaMax => [TransferTarget::OrgaMax, TransferTarget::File],
            BillingMode::SevDesk => [TransferTarget::SevDesk, TransferTarget::File],
            BillingMode::Easybill => [TransferTarget::Easybill, TransferTarget::File],
            BillingMode::Datev, BillingMode::Workdiary => [TransferTarget::File],
        };
    }

    public static function defaultTargetFor(BillingMode $mode): TransferTarget {
        return match ($mode) {
            BillingMode::Lexoffice => TransferTarget::Lexoffice,
            BillingMode::OrgaMax => TransferTarget::OrgaMax,
            BillingMode::SevDesk => TransferTarget::SevDesk,
            BillingMode::Easybill => TransferTarget::Easybill,
            default => TransferTarget::File,
        };
    }

    /**
     * Vorschau-Positionen wie das Ziel sie erzeugen würde: Zeit über die
     * bestehende Aggregation (Taktung!), Material je Verwendung.
     *
     * @return list<array{name: string, quantity: string, unit: string, unit_price: string, amount: string}>
     */
    private function previewPositions(BillingTransfer $transfer): array {
        $positions = [];

        if ($transfer->channel === TransferChannel::Time) {
            $entries = $transfer->items
                ->where('source_type', TimeEntry::class)
                ->map(fn($item) => $item->source)
                ->filter()
                ->values();

            if ($entries->isEmpty()) {
                return [];
            }

            $entries = TimeEntry::query()
                ->whereIn('id', $entries->pluck('id'))
                ->with(['project.parent', 'project.customer', 'project.foreignCustomer'])
                ->orderBy('date')
                ->get();
            $entriesById = $entries->keyBy('id');

            foreach (app(BillableTimeAggregator::class)->aggregate($entries) as $block) {
                $hours = $block->billedHours();
                if ($hours <= 0) {
                    continue;
                }
                $primary = $entriesById->get($block->primaryEntryId);
                $fallbackRate = $primary !== null && $primary->hourly_rate !== null
                    ? $primary->hourly_rate
                    : $transfer->customer->hourly_rate;
                $rate = $block->hourlyRate() ?? $fallbackRate?->toFloat() ?? 0.0;

                $projectName = $block->project?->name ?: (string) __('Leistung');
                $kindSuffix = $block->kind !== null ? ' [' . $block->kind->value . ']' : '';

                $positions[] = [
                    'name' => $projectName . $kindSuffix,
                    'quantity' => NumberHelper::toGermanFormat($hours, 2, withThousandsSeparator: true),
                    'unit' => 'h',
                    'unit_price' => NumberHelper::toGermanFormat($rate, 2, withThousandsSeparator: true),
                    'amount' => NumberHelper::toGermanFormat(round($hours * $rate, 2), 2, withThousandsSeparator: true),
                ];
            }

            return $positions;
        }

        foreach ($transfer->items->where('source_type', MaterialUsage::class) as $item) {
            /** @var MaterialUsage|null $usage */
            $usage = $item->source;
            $positions[] = [
                'name' => $usage !== null ? (trim((string) $usage->description) ?: (string) __('Material')) : (string) __('finance.field.source_deleted'),
                'quantity' => NumberHelper::toGermanFormat((float) ($item->quantity ?? 0), 2, withThousandsSeparator: true),
                'unit' => $usage->unit ?? '',
                'unit_price' => NumberHelper::toGermanFormat(($usage->unit_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true),
                'amount' => NumberHelper::toGermanFormat((float) ($item->amount ?? 0), 2, withThousandsSeparator: true),
            ];
        }

        return $positions;
    }
}
