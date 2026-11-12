<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Inventory;

use App\Enums\Inventory\{OwnershipType, StockState};
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Article\ArticleVariant;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockLevelSetting, StockLot, StockMovement, StockReservation, Warehouse, WarehouseBin};
use App\Services\Inventory\{CustomerStockAllocationService, EnteredQuantity, InventoryLedger, InventoryValuationManager, LotStockReader, NegativeStockApproval, ReservationService, StockLevelService};
use App\Support\{ErrorText, Sqid};
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Bestandsübersicht und manuelle Lagerbuchungen (Feature 048, MVP-067) über den
 * {@see InventoryLedger}. Lesen mit inventory.viewAny, Buchen mit inventory.post.
 */
class StockController extends Controller {
    use \App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
    use \App\Http\Controllers\Concerns\ResolvesGlobalDateRange;

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly InventoryValuationManager $valuation,
        private readonly ReservationService $reservations,
        private readonly StockLevelService $levels,
        private readonly LotStockReader $lotStock,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get();
        $selectedId = Sqid::decodeOrNumeric(Warehouse::class, $request->query('warehouse'));
        $selected = $selectedId !== null
            ? $warehouses->firstWhere('id', $selectedId)
            : $warehouses->first();

        $rows = null;
        $reservations = collect();
        $belowReorder = [];
        $bins = collect();
        $lotOptions = [];
        if ($selected instanceof Warehouse) {
            // Lagerplätze (MVP-706): Spalte + Buchungsauswahl nur, wenn das Lager welche hat.
            $bins = $selected->bins()->get();
            $binBalances = $bins->isNotEmpty() ? $this->ledger->binBalancesByVariant($selected) : [];
            $binsById = $bins->keyBy('id');
            $variantIds = StockMovement::query()
                ->where('warehouse_id', $selected->id)
                ->distinct()
                ->pluck('article_variant_id')
                ->all();

            /** @var array<int, StockLevelSetting> $levelByVariant */
            $levelByVariant = StockLevelSetting::query()
                ->where('warehouse_id', $selected->id)
                ->get()->keyBy('article_variant_id')->all();

            // Salden und Bewertung je Lager in je einer Query statt ≥ 8 Queries
            // je Variante (Vollscan 2026-08-23, A4).
            $balances = $this->ledger->balancesByVariant($selected);
            // Bewertung nach dem wirksamen Verfahren je Variante (Durchschnitt oder Schichten).
            $valuations = $this->valuation->summariesForWarehouse($selected, $this->currentOrganization());

            $rows = ArticleVariant::query()->with('article')->whereIn('id', $variantIds)->orderBy('id')
                ->paginate(50)->withQueryString()
                ->through(function (ArticleVariant $variant) use ($levelByVariant, $balances, $valuations, $binBalances, $binsById): array {
                    $level = $levelByVariant[$variant->id] ?? null;
                    $variantBalances = $balances[(int) $variant->id] ?? [];
                    $blocked = bcadd($variantBalances[StockState::Blocked->value] ?? '0', '0', InventoryLedger::SCALE);

                    return [
                        'variant' => $variant,
                        'available' => InventoryLedger::availableFromBalances($variantBalances),
                        'physical' => bcadd($variantBalances[StockState::Physical->value] ?? '0', '0', InventoryLedger::SCALE),
                        'reserved' => bcadd($variantBalances[StockState::Reserved->value] ?? '0', '0', InventoryLedger::SCALE),
                        // Gesperrt (Chargensperre, Quarantäne) nur, wenn ungleich null.
                        'blocked' => bccomp($blocked, '0', InventoryLedger::SCALE) !== 0 ? $blocked : null,
                        'avg' => $valuations[(int) $variant->id]['avg'] ?? '0',
                        'value' => $valuations[(int) $variant->id]['value'] ?? '0',
                        'reorder' => $level?->reorder_point,
                        // bin_code → physischer Saldo (0 = ohne Platz wird nicht gelistet).
                        'bins' => collect($binBalances[(int) $variant->id] ?? [])
                            ->filter(fn (string $sum, int $binId): bool => $binId > 0 && bccomp($sum, '0', InventoryLedger::SCALE) !== 0)
                            ->mapWithKeys(fn (string $sum, int $binId): array => [(string) ($binsById[$binId]->code ?? $binId) => $sum])
                            ->all(),
                    ];
                });

            $reservations = StockReservation::query()
                ->where('warehouse_id', $selected->id)
                ->where('status', \App\Enums\Inventory\ReservationStatus::Active->value)
                ->with('variant.article')
                ->orderBy('priority')->orderBy('reserved_at')
                ->get();

            $belowReorder = $this->levels->belowReorder($selected);

            // Chargenauswahl der Entnahme: aktive Chargen mit Bestand in diesem Lager, gefiltert nach der gewählten Variante.
            $lotOptions = $this->lotStock->issuableIn($selected);
            (new EloquentCollection(array_column($lotOptions, 0)))->load('variant');
        }

        return view('inventory.index', [
            'warehouses' => $warehouses,
            'selected' => $selected,
            'rows' => $rows,
            'reservations' => $reservations,
            'belowReorder' => $belowReorder,
            'bins' => $bins,
            'lotOptions' => $lotOptions,
            'showBlocked' => $rows !== null && collect($rows->items())->contains(fn (array $row): bool => $row['blocked'] !== null),
            'canPost' => Auth::user()?->can(P::InventoryPost->value) ?? false,
            'canReserve' => Auth::user()?->can(P::InventoryReserve->value) ?? false,
            'canConfigure' => Auth::user()?->can(P::InventoryConfigure->value) ?? false,
            'pickerVariants' => ArticleVariant::query()->with(['article.units' => fn ($q) => $q->where('active', true)->orderBy('code')])
                ->where('status', \App\Enums\Article\ArticleStatus::Active->value)
                ->orderBy('id')->limit(500)->get(),
            'ownerships' => OwnershipType::cases(),
            // Optionaler Kunde für Materialkosten-Buchung beim Eigenbestand-Abgang.
            'costCustomers' => Customer::query()->whereNull('archived_at')->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    /**
     * Lagerjournal (MVP-1584): die Bewegungen eines Lagerorts mit der Menge in
     * Basiseinheit und der Eingabe, wie sie erfasst wurde.
     */
    public function journal(Request $request): View {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get();
        $selectedId = Sqid::decodeOrNumeric(Warehouse::class, $request->query('warehouse'));
        $selected = $selectedId !== null ? $warehouses->firstWhere('id', $selectedId) : null;
        $search = $request->string('q')->toString();
        $range = $this->globalDateRange();

        return view('inventory.journal', [
            'warehouses' => $warehouses,
            'selected' => $selected,
            'search' => $search,
            'movements' => StockMovement::query()
                ->with(['variant.article', 'warehouse', 'bin', 'lot', 'actor'])
                ->when($selected instanceof Warehouse, fn ($q) => $q->where('warehouse_id', $selected?->id))
                ->when($search !== '', fn ($q) => $q->whereHas('variant', fn ($v) => $v
                    ->whereLikeEscaped('sku', $search)
                    ->orWhereHas('article', fn ($a) => $a->whereLikeEscaped('name', $search))))
                ->whereBetween('occurred_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    /**
     * Reservierung ändern (MVP-1585): Priorität setzen, Teilmenge freigeben; reservierte, verbrauchte und offene Menge im Blick.
     * Umverteilen (MVP-1626) an eine andere aktive Reservierung desselben Bestandstopfs.
     */
    public function editReservation(StockReservation $reservation): View {
        Gate::authorize(P::InventoryReserve->value);

        return view('inventory._reservation_dialog', [
            'reservation' => $reservation->load('variant.article', 'warehouse'),
            'redistributionTargets' => StockReservation::query()
                ->where('status', \App\Enums\Inventory\ReservationStatus::Active->value)
                ->where('article_variant_id', $reservation->article_variant_id)
                ->where('warehouse_id', $reservation->warehouse_id)
                ->where('bin_id', $reservation->bin_id)
                ->where('ownership_type', $reservation->ownership_type)
                ->whereKeyNot($reservation->id)
                ->orderBy('priority')->orderBy('reserved_at')
                ->get(),
        ]);
    }

    public function updateReservation(Request $request, StockReservation $reservation): RedirectResponse {
        Gate::authorize(P::InventoryReserve->value);

        $data = $request->validate([
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'release_qty' => ['nullable', 'numeric', 'gt:0'],
            'redistribute_to' => ['nullable', 'string'],
            'redistribute_qty' => ['nullable', 'numeric', 'gt:0'],
        ]);

        // Auditable hält die alte und neue Priorität fest.
        $reservation->update(['priority' => (int) $data['priority']]);
        if (($data['release_qty'] ?? null) !== null) {
            $this->reservations->release($reservation, (string) $data['release_qty']);
        }
        if (($data['redistribute_to'] ?? null) !== null) {
            $target = StockReservation::query()->find(Sqid::decodeOrNumeric(StockReservation::class, (string) $data['redistribute_to']));
            if (! $target instanceof StockReservation) {
                return back()->withErrors(['redistribute_to' => __('inventory.reservation.redistribute_invalid')]);
            }
            try {
                $this->reservations->redistribute($reservation->refresh(), $target, isset($data['redistribute_qty']) ? (string) $data['redistribute_qty'] : null, Auth::id() !== null ? (int) Auth::id() : null);
            } catch (RuntimeException $e) {
                return back()->withErrors(['redistribute_qty' => ErrorText::for($e)]);
            }
        }

        return redirect()->route('inventory.stock', ['warehouse' => $reservation->warehouse?->sqid])
            ->with('success', __('inventory.reservation.saved'));
    }

    public function setLevels(Request $request): RedirectResponse {
        Gate::authorize(P::InventoryConfigure->value);

        $data = $request->validate([
            'warehouse' => ['required', 'string'],
            'variant' => ['required', 'string'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
        ]);

        $warehouse = Warehouse::query()->findOrFail(Sqid::decodeOrNumeric(Warehouse::class, $data['warehouse']));
        $variant = ArticleVariant::query()->findOrFail(Sqid::decodeOrNumeric(ArticleVariant::class, $data['variant']));
        $this->levels->setLevels($variant, $warehouse, (string) $data['min_stock'], (string) $data['reorder_point']);

        return redirect()->route('inventory.stock', ['warehouse' => $warehouse->sqid])
            ->with('success', __('inventory.overview.levels_saved'));
    }

    public function storeMovement(Request $request): RedirectResponse {
        // MVP-1585: Reservieren und Freigeben ist ein eigenes Recht neben dem Buchen.
        Gate::authorize(in_array($request->input('movement'), ['reserve', 'release'], true) ? P::InventoryReserve->value : P::InventoryPost->value);

        $data = $request->validate([
            'warehouse' => ['required', 'string'],
            'variant' => ['required', 'string'],
            'movement' => ['required', 'in:receipt,issue,reserve,release'],
            'qty' => ['required', 'numeric', 'gt:0'],
            // Einheit des Artikels (MVP-1584); leer = Basiseinheit.
            'unit' => ['nullable', 'string', 'max:20'],
            'ownership' => ['required', \Illuminate\Validation\Rule::enum(OwnershipType::class)],
            'allow_negative' => ['sometimes', 'boolean'],
            'cost_customer' => ['nullable', 'string'],
            'bin' => ['nullable', 'string'],
            'lot' => ['nullable', 'string'],
        ]);

        $warehouse = Warehouse::query()->findOrFail(Sqid::decodeOrNumeric(Warehouse::class, $data['warehouse']));
        $variant = ArticleVariant::query()->findOrFail(Sqid::decodeOrNumeric(ArticleVariant::class, $data['variant']));

        // Optionaler Lagerplatz (MVP-706): muss zum gewählten Lager gehören.
        $bin = null;
        if (! empty($data['bin'])) {
            $bin = $warehouse->bins()->find(Sqid::decodeOrNumeric(WarehouseBin::class, (string) $data['bin']));
            if (! $bin instanceof WarehouseBin) {
                return back()->with('error', __('inventory.error.bin_foreign'));
            }
        }
        $ownership = OwnershipType::from((string) $data['ownership']);
        // Gebucht wird in Basiseinheit; Eingabe und Einheit bleiben an der Bewegung.
        try {
            $entered = EnteredQuantity::of($variant, (string) $data['qty'], $data['unit'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }
        $qty = $entered->base;
        $actor = Auth::id() !== null ? (int) Auth::id() : null;

        // Charge nur für die Entnahme (leer = FEFO); dass sie zur Variante gehört und aktiv ist, prüft die Zuteilung.
        $lot = null;
        if ((string) $data['movement'] === 'issue' && ! empty($data['lot'])) {
            $lotId = Sqid::decodeOrNumeric(StockLot::class, (string) $data['lot']);
            $lot = $lotId !== null ? StockLot::query()->find($lotId) : null;
            if (! $lot instanceof StockLot) {
                return back()->with('error', __('inventory.lot.flash.unknown'));
            }
        }

        // Eigenbestand-Abgang, der zugleich Materialkosten auf einen Kunden
        // bucht (gleiche Buchung wie in der Kundenakte): nur bei movement=issue
        // und Eigenbestand — sonst normaler Abgang.
        $costCustomer = null;
        if (! empty($data['cost_customer'])) {
            $costCustomerId = Sqid::decodeOrNumeric(Customer::class, (string) $data['cost_customer']);
            $costCustomer = $costCustomerId !== null ? Customer::query()->find($costCustomerId) : null;
        }
        // Der Kundenpfad läuft über die Bewertung ohne Platzbezug — nicht still den Platz verlieren.
        if ($costCustomer instanceof Customer && $bin instanceof WarehouseBin) {
            return back()->with('error', __('inventory.error.bin_with_customer'));
        }

        // Vollaudit 2026-07 (M19, E2): chargen-/serienpflichtige Artikel nicht still als anonymer Bestand buchen.
        // Zugang nur über den Wareneingang, Serien gar nicht; die Entnahme chargenpflichtiger Artikel muss ganz
        // aus Chargen kommen (gewählt oder FEFO). Reservierung/Freigabe bleibt zulässig.
        $article = $variant->article;
        $lotTracked = (bool) ($article->batch_required ?? false);
        if (
            in_array((string) $data['movement'], ['receipt', 'issue'], true)
            && (($article->serial_required ?? false) || ($lotTracked && (string) $data['movement'] === 'receipt'))
        ) {
            return back()->with('error', __('inventory.error.tracked_article_manual_move'));
        }

        // MHD-Pflicht (MVP-1579): der manuelle Zugang kennt weder Charge noch Datum.
        if ((string) $data['movement'] === 'receipt' && ($article->shelf_life_required ?? false)) {
            return back()->with('error', __('inventory.error.shelf_life_manual_receipt'));
        }

        // Vollaudit 2026-07 (M22): negative Bestände sind eine eigene,
        // rollenbasierte und auditierte Freigabe — nicht Teil von inventory.post.
        $allowNegative = (bool) ($data['allow_negative'] ?? false);
        if ($allowNegative) {
            Gate::authorize(P::InventoryNegative->value);
            // Vier Augen (MVP-1626): die Entnahme wird Antrag, gebucht wird nach Freigabe einer zweiten Person.
            $negative = app(NegativeStockApproval::class);
            $organization = $warehouse->organization;
            if ((string) $data['movement'] === 'issue' && $organization !== null && $negative->required($organization)) {
                if ($costCustomer instanceof Customer) {
                    return back()->withInput()->with('error', __('inventory.negative.no_customer_cost'));
                }
                $negative->request($variant, $warehouse, $entered, $ownership, $this->authUser(), $bin, $lot);

                return redirect()->route('inventory.stock', ['warehouse' => $warehouse->sqid])
                    ->with('success', __('inventory.negative.requested'));
            }
            \App\Models\Audit\AuditLog::query()->create([
                'organization_id' => $variant->organization_id,
                'user_id' => $actor,
                'event' => 'inventory.negativeApproved',
                'auditable_type' => MorphMap::stableKey(ArticleVariant::class),
                'auditable_id' => $variant->id,
                'changes' => ['warehouse_id' => $warehouse->id, 'qty' => $qty],
            ]);
        }

        try {
            match ((string) $data['movement']) {
                'receipt' => $this->ledger->receipt($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin, entered: $entered),
                'issue' => $costCustomer instanceof Customer && $ownership === OwnershipType::Own
                    ? app(CustomerStockAllocationService::class)->issueForCustomer($costCustomer, $variant, $warehouse, $qty, actorUserId: $actor, lot: $lot, requireLot: $lotTracked, entered: $entered)
                    : $this->ledger->issue($variant, $warehouse, $qty, $ownership, allowNegative: $allowNegative, actorUserId: $actor, bin: $bin, lot: $lot, requireLot: $lotTracked, entered: $entered),
                'reserve' => $this->ledger->reserve($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin, entered: $entered),
                'release' => $this->ledger->releaseReservation($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin, entered: $entered),
                default => throw new RuntimeException('Unbekannte Bewegungsart.'),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.stock', ['warehouse' => $warehouse->sqid])
            ->with('success', __('inventory.flash.movement_posted'));
    }
}
