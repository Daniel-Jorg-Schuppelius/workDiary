<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageStockBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{InventoryMode, NegativeStockRequestStatus, OwnershipType, StockMovementType, StockState};
use App\Enums\Material\MaterialStockStatus;
use App\Enums\User\Permission as P;
use App\Exceptions\MaterialStockException;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Inventory\{NegativeStockRequest, StockLot, StockMovement, Warehouse};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Material\Contracts\MaterialStockBooking;
use App\Support\{DecimalQty, ErrorText, MorphMap};
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use ERechnungToolkit\Enums\UnitCode;
use Illuminate\Support\Facades\{DB, Gate};
use RuntimeException;

/**
 * Materialverbrauch eines lagergeführten Artikels (`articles.stockable`)
 * bucht den Bestand (MVP-1679): Abgang aus dem Standardlager der
 * Organisation, in der erfassten Einheit, bewertet nach dem Verfahren der
 * Variante, mit den Regeln des Lagerwegs (Negativbestand nur mit Recht und
 * ggf. Vier-Augen-Antrag, Charge über FEFO, Seriennummer nur über das Lager).
 * Jede Bewegung trägt den Verbrauch als Quelle; der gebuchte Stand ist die
 * Summe dieser Bewegungen. Eine Änderung bucht die Differenz, Löschen bucht
 * zurück. Externe Bestandsführung meldet jede Bewegung über den Ledger an
 * das führende System; „nur lesen“ bucht nichts.
 */
final class MaterialUsageStockBooking implements MaterialStockBooking {
    public const string KEY_PREFIX = 'material_usage:';

    /** Felder, deren Änderung den Bestand berührt. */
    private const array STOCK_FIELDS = ['material_id', 'article_variant_id', 'quantity', 'unit'];

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly InventoryValuationManager $valuation,
        private readonly InventoryProviderResolver $providers,
        private readonly NegativeStockApproval $negative,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function sync(MaterialUsage $usage, User $actor, bool $allowNegative = false): void {
        if (! $usage->wasRecentlyCreated) {
            // Text oder Preis berühren den Bestand nicht; ein offener oder abgelehnter Antrag bleibt stehen.
            if (! $usage->wasChanged(self::STOCK_FIELDS) && ! $allowNegative) {
                return;
            }
            // Eine Zeile ohne Bestandsbezug (vor MVP-1679 erfasst oder damals nicht lagergeführt)
            // bekommt ihn erst mit einem anderen Material — eine Mengenkorrektur bucht dort nichts nach.
            if ($usage->stock_status === null && ! $usage->wasChanged('material_id')) {
                return;
            }
        }

        DB::transaction(function () use ($usage, $actor, $allowNegative): void {
            $locked = MaterialUsage::query()->withoutGlobalScopes()->whereKey($usage->id)->lockForUpdate()->firstOrFail();
            $organization = $this->organizationOf($locked);
            if ($organization === null) {
                return;
            }
            $this->withdrawOpenRequests($locked, $actor);
            $this->apply($locked, $organization, $actor, $allowNegative, null);
        });
    }

    public function release(MaterialUsage $usage, ?User $actor): void {
        DB::transaction(function () use ($usage, $actor): void {
            $locked = MaterialUsage::query()->withoutGlobalScopes()->whereKey($usage->id)->lockForUpdate()->first();
            if (! $locked instanceof MaterialUsage) {
                return;
            }
            $this->withdrawOpenRequests($locked, $actor);
            $organization = $this->organizationOf($locked);
            if ($organization === null || $this->providers->modeFor($organization) === InventoryMode::ReadOnly) {
                return;
            }
            $this->returnParts($locked, $organization, $this->bookedParts($locked), null, $actor);
        });
    }

    /** Freigegebener Vier-Augen-Antrag eines Verbrauchs: bucht den offenen Abgang in den Negativbestand. */
    public function bookApproved(NegativeStockRequest $request): void {
        $usage = MaterialUsage::query()->withoutGlobalScopes()->whereKey((int) $request->source_id)->lockForUpdate()->first();
        $organization = $usage instanceof MaterialUsage ? $this->organizationOf($usage) : null;
        $requester = $request->requested_by !== null ? User::query()->find((int) $request->requested_by) : null;
        if (! $usage instanceof MaterialUsage || $organization === null || ! $requester instanceof User) {
            throw new RuntimeException((string) __('material.stock.usage_gone'));
        }

        $this->apply($usage, $organization, $requester, true, $request);
    }

    /** Abgelehnter Antrag: der Verbrauch bleibt erfasst, der Bestand unberührt. */
    public function rejected(NegativeStockRequest $request): void {
        $usage = MaterialUsage::query()->withoutGlobalScopes()->whereKey((int) $request->source_id)->first();
        if ($usage instanceof MaterialUsage && $usage->stock_status === MaterialStockStatus::Requested) {
            $usage->forceFill(['stock_status' => MaterialStockStatus::Rejected])->save();
        }
    }

    private function apply(MaterialUsage $usage, Organization $organization, User $actor, bool $allowNegative, ?NegativeStockRequest $approved): void {
        $booked = $this->bookedParts($usage);
        $variant = $this->stockVariant($usage);
        if (! $variant instanceof ArticleVariant) {
            $this->returnParts($usage, $organization, $booked, null, $actor);
            $this->mark($usage, null, null);

            return;
        }
        if ($this->providers->modeFor($organization) === InventoryMode::ReadOnly) {
            $this->mark($usage, MaterialStockStatus::ReadOnly, null);

            return;
        }

        // Eine bestehende Buchung bleibt in ihrem Lager, auch wenn sich das Standardlager inzwischen geändert hat.
        $warehouse = $this->bookedWarehouse($booked, $variant) ?? $this->defaultWarehouse($organization);
        $same = [];
        $other = [];
        foreach ($booked as $part) {
            if ($warehouse instanceof Warehouse && (int) $part['variant']->id === (int) $variant->id && (int) $part['warehouse']->id === (int) $warehouse->id) {
                $same[] = $part;
            } else {
                $other[] = $part;
            }
        }
        // Material oder Variante gewechselt: die alte Variante geht vollständig zurück.
        $this->returnParts($usage, $organization, $other, null, $actor);
        if (! $warehouse instanceof Warehouse) {
            $this->mark($usage, MaterialStockStatus::NoWarehouse, null);

            return;
        }

        $article = $variant->article;
        if ($article instanceof Article && $article->serial_required) {
            throw MaterialStockException::on('material_id', (string) __('material.stock.serial_required'));
        }

        $entered = $this->enteredQuantity($usage, $variant);
        $net = '0';
        foreach ($same as $part) {
            $net = NumberHelper::addPrecise($net, $part['qty'], InventoryLedger::SCALE);
        }
        $delta = NumberHelper::subtractPrecise($entered->base, $net, InventoryLedger::SCALE);

        if (NumberHelper::comparePrecise($delta, '0', InventoryLedger::SCALE) < 0) {
            $this->returnParts($usage, $organization, $same, DecimalQty::positive($delta), $actor);
        } elseif (NumberHelper::comparePrecise($delta, '0', InventoryLedger::SCALE) > 0
            && ! $this->issue($usage, $organization, $variant, $warehouse, $delta, $entered, $actor, $allowNegative, $approved)) {
            $this->mark($usage, MaterialStockStatus::Requested, $warehouse);

            return;
        }

        $this->mark($usage, MaterialStockStatus::Booked, $warehouse);
    }

    /**
     * Abgang der Differenz. Reicht der verfügbare Bestand nicht, gelten die
     * Regeln des Lagerwegs: ohne „Negativen Bestand zulassen“ oder ohne Recht
     * ein Eingabefehler, mit Vier-Augen-Pflicht ein Antrag.
     *
     * @param numeric-string $delta
     * @return bool false, wenn der Abgang als Antrag auf die Freigabe wartet
     */
    private function issue(MaterialUsage $usage, Organization $organization, ArticleVariant $variant, Warehouse $warehouse, string $delta, EnteredQuantity $entered, User $actor, bool $allowNegative, ?NegativeStockRequest $approved): bool {
        $negative = $approved !== null;
        $available = $this->ledger->availableForUpdate($variant, $warehouse);
        if (! $negative && NumberHelper::comparePrecise($available, $delta, InventoryLedger::SCALE) < 0) {
            if (! $allowNegative) {
                throw MaterialStockException::shortage((string) __('material.stock.insufficient', [
                    'warehouse' => (string) $warehouse->name,
                    'available' => NumberHelper::toGermanFormat($available, InventoryLedger::SCALE, trimTrailingZeros: true),
                    'unit' => (string) ($variant->article->base_unit ?? ''),
                ]));
            }
            if (Gate::forUser($actor)->denies(P::InventoryNegative->value)) {
                throw MaterialStockException::on('allow_negative', (string) __('material.stock.negative_forbidden'));
            }
            if ($this->negative->required($organization)) {
                $this->negative->request($variant, $warehouse, $this->deltaEntered($variant, $entered, $delta), OwnershipType::Own, $actor, source: $usage);

                return false;
            }
            $negative = true;
            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $actor->id,
                'event' => 'inventory.negativeApproved',
                'auditable_type' => MorphMap::stableKey(ArticleVariant::class),
                'auditable_id' => $variant->id,
                'changes' => ['warehouse_id' => $warehouse->id, 'qty' => $delta, 'material_usage_id' => $usage->id],
            ]);
        }

        try {
            $this->valuation->forVariant($variant, $organization)->issue(
                $variant, $warehouse, $delta,
                allowNegative: $negative,
                actorUserId: (int) $actor->id,
                requireLot: (bool) ($variant->article->batch_required ?? false),
                entered: $entered,
                idempotencyKey: $approved !== null ? 'negative_stock_request:' . $approved->id : $this->nextKey($usage),
                source: $usage,
            );
        } catch (RuntimeException $e) {
            throw MaterialStockException::on('quantity', (string) __('material.stock.booking_failed', ['reason' => ErrorText::for($e)]));
        }

        return true;
    }

    /**
     * Rückbuchung in die Chargen des Abgangs, jüngste zuerst, zum
     * Stückkostenwert des Abgangs; ohne Menge alles.
     *
     * @param list<array{variant: ArticleVariant, warehouse: Warehouse, lot: StockLot|null, qty: numeric-string, unit_cost: numeric-string, currency: string, last: int}> $parts
     * @param numeric-string|null $qty
     */
    private function returnParts(MaterialUsage $usage, Organization $organization, array $parts, ?string $qty, ?User $actor): void {
        usort($parts, static fn (array $a, array $b): int => $b['last'] <=> $a['last']);
        $remaining = $qty;
        foreach ($parts as $part) {
            $take = $remaining === null ? $part['qty'] : NumberHelper::minPrecise($part['qty'], $remaining, InventoryLedger::SCALE);
            if (NumberHelper::comparePrecise($take, '0', InventoryLedger::SCALE) <= 0) {
                break;
            }
            try {
                $this->valuation->forVariant($part['variant'], $organization)->returnToStock(
                    $part['variant'], $part['warehouse'], $take, $part['unit_cost'], $part['currency'],
                    $actor?->id, $usage, $part['lot'], $this->nextKey($usage),
                );
            } catch (RuntimeException $e) {
                throw MaterialStockException::on('quantity', (string) __('material.stock.return_failed', ['reason' => ErrorText::for($e)]));
            }
            if ($remaining !== null) {
                $remaining = NumberHelper::subtractPrecise($remaining, $take, InventoryLedger::SCALE);
            }
        }
    }

    /**
     * Gebuchter Stand des Verbrauchs je Variante, Lager und Charge: Summe der
     * physischen Bewegungen mit dem Verbrauch als Quelle (Abgang positiv).
     *
     * @return list<array{variant: ArticleVariant, warehouse: Warehouse, lot: StockLot|null, qty: numeric-string, unit_cost: numeric-string, currency: string, last: int}>
     */
    private function bookedParts(MaterialUsage $usage): array {
        $groups = [];
        $movements = StockMovement::query()->withoutGlobalScopes()
            ->with(['variant.article', 'warehouse', 'lot'])
            ->where('source_type', $usage->getMorphClass())
            ->where('source_id', $usage->id)
            ->where('stock_state', StockState::Physical->value)
            ->orderBy('id')
            ->get();
        foreach ($movements as $movement) {
            $key = $movement->article_variant_id . ':' . $movement->warehouse_id . ':' . ($movement->stock_lot_id ?? 0);
            $qty = NumberHelper::normalizeDecimalString((string) $movement->qty_base);
            $groups[$key] ??= ['movement' => $movement, 'qty' => '0', 'issued' => '0', 'cost' => '0', 'last' => 0];
            $groups[$key]['qty'] = NumberHelper::subtractPrecise($groups[$key]['qty'], $qty, InventoryLedger::SCALE);
            if ($movement->movement_type === StockMovementType::Issue) {
                $groups[$key]['issued'] = NumberHelper::subtractPrecise($groups[$key]['issued'], $qty, InventoryLedger::SCALE);
                $groups[$key]['cost'] = NumberHelper::addPrecise($groups[$key]['cost'], $movement->cost_total?->getAmount() ?? '0', InventoryLedger::SCALE);
                $groups[$key]['last'] = (int) $movement->id;
            }
        }

        $parts = [];
        foreach ($groups as $group) {
            $movement = $group['movement'];
            if (NumberHelper::comparePrecise($group['qty'], '0', InventoryLedger::SCALE) <= 0 || ! $movement->variant instanceof ArticleVariant || ! $movement->warehouse instanceof Warehouse) {
                continue;
            }
            $parts[] = [
                'variant' => $movement->variant,
                'warehouse' => $movement->warehouse,
                'lot' => $movement->lot,
                'qty' => $group['qty'],
                'unit_cost' => NumberHelper::divideOrDefault($group['cost'], $group['issued'], InventoryLedger::SCALE),
                'currency' => $movement->currency->value ?? 'EUR',
                'last' => $group['last'],
            ];
        }

        return $parts;
    }

    /** @param list<array{variant: ArticleVariant, warehouse: Warehouse, lot: StockLot|null, qty: numeric-string, unit_cost: numeric-string, currency: string, last: int}> $parts */
    private function bookedWarehouse(array $parts, ArticleVariant $variant): ?Warehouse {
        foreach ($parts as $part) {
            if ((int) $part['variant']->id === (int) $variant->id) {
                return $part['warehouse'];
            }
        }

        return null;
    }

    /** Standardlager: aktives Lager mit Kennzeichen „Standard“, bei mehreren das erste nach Name wie in der Lagerauswahl. */
    private function defaultWarehouse(Organization $organization): ?Warehouse {
        return Warehouse::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->where('active', true)
            ->orderBy('name')->orderBy('id')
            ->first();
    }

    /**
     * Bestandsführende Variante: die am Verbrauch gewählte (MVP-1690), sonst
     * die Standard- oder erste Variante des lagergeführten Artikels.
     */
    private function stockVariant(MaterialUsage $usage): ?ArticleVariant {
        $material = $usage->material_id !== null
            ? Material::query()->withoutGlobalScopes()->where('organization_id', $usage->organization_id)->find($usage->material_id)
            : null;
        $article = $material instanceof Material && $material->article_id !== null
            ? Article::query()->withoutGlobalScopes()->where('organization_id', $usage->organization_id)->find($material->article_id)
            : null;
        if (! $article instanceof Article || ! $article->stockable) {
            return null;
        }

        $variants = ArticleVariant::query()->withoutGlobalScopes()->with('article')->where('article_id', $article->id);
        $chosen = $usage->article_variant_id !== null ? (clone $variants)->find($usage->article_variant_id) : null;

        return $chosen instanceof ArticleVariant ? $chosen : $variants->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * Erfasste Menge in der Einheit des Verbrauchs. Eine Schreibweise der
     * Basiseinheit („Stk.“ statt „Stk“) zählt als Basiseinheit; eine fremde
     * Einheit ohne gepflegten Faktor ist ein Eingabefehler.
     */
    private function enteredQuantity(MaterialUsage $usage, ArticleVariant $variant): EnteredQuantity {
        $article = $variant->article;
        $unit = trim((string) $usage->unit);
        if ($article instanceof Article && $unit !== '' && $unit !== $article->base_unit
            && ! $article->units()->where('code', $unit)->where('active', true)->exists()) {
            $code = UnitCode::fromText($unit);
            if ($code !== null && $code === UnitCode::fromText((string) $article->base_unit)) {
                $unit = (string) $article->base_unit;
            }
        }

        try {
            return EnteredQuantity::of($variant, (string) ($usage->quantity?->getNumericValue() ?? '0'), $unit);
        } catch (RuntimeException $e) {
            throw MaterialStockException::on('unit', ErrorText::for($e));
        }
    }

    /**
     * Die Differenz als Eingabe in der Einheit des Verbrauchs (für den Antrag).
     *
     * @param numeric-string $delta
     */
    private function deltaEntered(ArticleVariant $variant, EnteredQuantity $entered, string $delta): EnteredQuantity {
        return EnteredQuantity::of($variant, NumberHelper::dividePrecise($delta, $entered->factor, InventoryLedger::SCALE, RoundingMode::HalfUp), $entered->originalUnit);
    }

    /** Erster Abgang `material_usage:<id>`, jeder weitere Schritt mit fortlaufender Nummer — eindeutig, solange die Zeile gesperrt ist. */
    private function nextKey(MaterialUsage $usage): string {
        $count = StockMovement::query()->withoutGlobalScopes()
            ->where('organization_id', $usage->organization_id)
            ->where('source_type', $usage->getMorphClass())
            ->where('source_id', $usage->id)
            ->count();

        return self::KEY_PREFIX . $usage->id . ($count > 0 ? ':' . ($count + 1) : '');
    }

    private function withdrawOpenRequests(MaterialUsage $usage, ?User $actor): void {
        NegativeStockRequest::query()->withoutGlobalScopes()
            ->where('source_type', $usage->getMorphClass())
            ->where('source_id', $usage->id)
            ->where('status', NegativeStockRequestStatus::Pending->value)
            ->lockForUpdate()
            ->get()
            ->each(fn (NegativeStockRequest $request) => $this->negative->withdraw($request, $actor));
    }

    private function mark(MaterialUsage $usage, ?MaterialStockStatus $status, ?Warehouse $warehouse): void {
        $usage->forceFill(['stock_status' => $status, 'stock_warehouse_id' => $warehouse?->id]);
        if ($usage->isDirty()) {
            $usage->save();
        }
    }

    /** Organisation des Verbrauchs, wenn dort das Lagermodul aktiv ist. */
    private function organizationOf(MaterialUsage $usage): ?Organization {
        $organization = $usage->organization_id !== null ? Organization::query()->find($usage->organization_id) : null;

        return $organization instanceof Organization && $this->modules->isActiveFor($organization, 'module.lager') ? $organization : null;
    }
}
