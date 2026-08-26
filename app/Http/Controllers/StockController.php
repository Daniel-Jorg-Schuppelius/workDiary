<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Inventory\OwnershipType;
use App\Enums\User\Permission as P;
use App\Models\{ArticleVariant, Customer, StockLevelSetting, StockMovement, StockReservation, Warehouse, WarehouseBin};
use App\Services\Inventory\{CustomerStockAllocationService, InventoryLedger, ReservationService, StockLevelService, ValuationService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Bestandsübersicht und manuelle Lagerbuchungen (Feature 048, MVP-067) über den
 * {@see InventoryLedger}. Lesen mit inventory.viewAny, Buchen mit inventory.post.
 */
class StockController extends Controller {
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly ValuationService $valuation,
        private readonly ReservationService $reservations,
        private readonly StockLevelService $levels,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get();
        $selectedId = Sqid::decodeOrNumeric(Warehouse::class, $request->query('warehouse'));
        $selected = $selectedId !== null
            ? $warehouses->firstWhere('id', $selectedId)
            : $warehouses->first();

        $rows = [];
        $reservations = collect();
        $belowReorder = collect();
        $bins = collect();
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
            $valuations = $this->valuation->summariesForWarehouse($selected);

            $variants = ArticleVariant::query()->with('article')->whereIn('id', $variantIds)->get();
            foreach ($variants as $variant) {
                $level = $levelByVariant[$variant->id] ?? null;
                $variantBalances = $balances[(int) $variant->id] ?? [];
                $rows[] = [
                    'variant' => $variant,
                    'available' => InventoryLedger::availableFromBalances($variantBalances),
                    'physical' => bcadd($variantBalances[\App\Enums\Inventory\StockState::Physical->value] ?? '0', '0', InventoryLedger::SCALE),
                    'reserved' => bcadd($variantBalances[\App\Enums\Inventory\StockState::Reserved->value] ?? '0', '0', InventoryLedger::SCALE),
                    'avg' => $valuations[(int) $variant->id]['avg'] ?? '0',
                    'value' => $valuations[(int) $variant->id]['value'] ?? '0',
                    'reorder' => $level?->reorder_point,
                    // bin_code → physischer Saldo (0 = ohne Platz wird nicht gelistet).
                    'bins' => collect($binBalances[(int) $variant->id] ?? [])
                        ->filter(fn (string $sum, int $binId): bool => $binId > 0 && bccomp($sum, '0', InventoryLedger::SCALE) !== 0)
                        ->mapWithKeys(fn (string $sum, int $binId): array => [(string) ($binsById[$binId]->code ?? $binId) => $sum])
                        ->all(),
                ];
            }

            $reservations = StockReservation::query()
                ->where('warehouse_id', $selected->id)
                ->where('status', \App\Enums\Inventory\ReservationStatus::Active->value)
                ->with('variant.article')
                ->orderBy('priority')->orderBy('reserved_at')
                ->get();

            $belowReorder = $this->levels->belowReorder($selected);
        }

        return view('inventory.index', [
            'warehouses' => $warehouses,
            'selected' => $selected,
            'rows' => $rows,
            'reservations' => $reservations,
            'belowReorder' => $belowReorder,
            'bins' => $bins,
            'canPost' => Auth::user()?->can(P::InventoryPost->value) ?? false,
            'canConfigure' => Auth::user()?->can(P::InventoryConfigure->value) ?? false,
            'pickerVariants' => ArticleVariant::query()->with('article')
                ->where('status', \App\Enums\Article\ArticleStatus::Active->value)
                ->orderBy('id')->limit(500)->get(),
            'ownerships' => OwnershipType::cases(),
            // Optionaler Kunde für Materialkosten-Buchung beim Eigenbestand-Abgang.
            'costCustomers' => Customer::query()->whereNull('archived_at')->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function releaseReservation(StockReservation $reservation): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);

        $this->reservations->release($reservation);

        return back()->with('success', __('inventory.overview.reservation_released'));
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
        Gate::authorize(P::InventoryPost->value);

        $data = $request->validate([
            'warehouse' => ['required', 'string'],
            'variant' => ['required', 'string'],
            'movement' => ['required', 'in:receipt,issue,reserve,release'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'ownership' => ['required', \Illuminate\Validation\Rule::enum(OwnershipType::class)],
            'allow_negative' => ['sometimes', 'boolean'],
            'cost_customer' => ['nullable', 'string'],
            'bin' => ['nullable', 'string'],
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
        $qty = (string) $data['qty'];
        $actor = Auth::id() !== null ? (int) Auth::id() : null;

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

        // Vollaudit 2026-07 (M19, E2): chargen-/serienpflichtige Artikel nicht
        // still als anonymer Bestand buchen (Reservierung/Freigabe bleibt zulässig).
        $article = $variant->article;
        if (
            in_array((string) $data['movement'], ['receipt', 'issue'], true)
            && (($article->batch_required ?? false) || ($article->serial_required ?? false))
        ) {
            return back()->with('error', __('inventory.error.tracked_article_manual_move'));
        }

        // Vollaudit 2026-07 (M22): negative Bestände sind eine eigene,
        // rollenbasierte und auditierte Freigabe — nicht Teil von inventory.post.
        $allowNegative = (bool) ($data['allow_negative'] ?? false);
        if ($allowNegative) {
            Gate::authorize(P::InventoryNegative->value);
            \App\Models\AuditLog::query()->create([
                'organization_id' => $variant->organization_id,
                'user_id' => $actor,
                'event' => 'inventory.negativeApproved',
                'auditable_type' => ArticleVariant::class,
                'auditable_id' => $variant->id,
                'changes' => ['warehouse_id' => $warehouse->id, 'qty' => $qty],
            ]);
        }

        try {
            match ((string) $data['movement']) {
                'receipt' => $this->ledger->receipt($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin),
                'issue' => $costCustomer instanceof Customer && $ownership === OwnershipType::Own
                    ? app(CustomerStockAllocationService::class)->issueForCustomer($costCustomer, $variant, $warehouse, $qty, actorUserId: $actor)
                    : $this->ledger->issue($variant, $warehouse, $qty, $ownership, allowNegative: $allowNegative, actorUserId: $actor, bin: $bin),
                'reserve' => $this->ledger->reserve($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin),
                'release' => $this->ledger->releaseReservation($variant, $warehouse, $qty, $ownership, actorUserId: $actor, bin: $bin),
                default => throw new RuntimeException('Unbekannte Bewegungsart.'),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.stock', ['warehouse' => $warehouse->sqid])
            ->with('success', __('inventory.flash.movement_posted'));
    }
}
