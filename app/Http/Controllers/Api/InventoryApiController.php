<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Inventory\StockState;
use App\Http\Controllers\Controller;
use App\Models\{Article, ArticleVariant, Warehouse, WarehouseBin};
use App\Services\Inventory\InventoryLedger;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Bestände (MVP-718, Vollscan J11): read-only Bestandsübersicht je
 * Lager × Variante über das append-only Bewegungsjournal
 * (`InventoryLedger::balancesByVariant`) inkl. Lagerplatz-Salden (MVP-706,
 * `binBalancesByVariant`). Ability `inventory:read`, WarehousePolicy wie im
 * Web, Plan-Gating `module.lager`.
 */
class InventoryApiController extends Controller {
    public function __construct(private readonly InventoryLedger $ledger) {}

    #[OA\Get(
        path: '/inventory',
        summary: 'Bestände je Lager und Variante (Salden je Bestandszustand + Lagerplatz)',
        tags: ['Inventory'],
        security: [['bearerAuth' => ['inventory:read']]],
        parameters: [
            new OA\Parameter(name: 'warehouse', in: 'query', required: false, description: 'Lager-Sqid (Default: alle aktiven Lager)', schema: new OA\Schema(type: 'string', example: 'Wh3Kd9')),
            new OA\Parameter(name: 'variant', in: 'query', required: false, description: 'Varianten-Sqid', schema: new OA\Schema(type: 'string', example: 'Va8Qm2')),
            new OA\Parameter(name: 'article', in: 'query', required: false, description: 'Artikel-Sqid (alle Varianten)', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'include_zero', in: 'query', required: false, description: 'Auch Nullsalden ausgeben', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK — data[]: {warehouse{id,name}, variant{id,sku,article{id,name}}, balances{physical,reserved,…}, available, bins[{id,code,name,qty}]}'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Lager/Variante nicht gefunden'),
            new OA\Response(response: 423, description: 'Modul nicht lizenziert'),
        ],
    )]
    public function index(Request $request): JsonResponse {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->when($request->filled('warehouse'), fn($q) => $q->whereKey(Sqid::decodeOrAbort(Warehouse::class, (string) $request->query('warehouse'))))
            ->when(! $request->filled('warehouse'), fn($q) => $q->where('active', true))
            ->orderBy('name')
            ->get();
        // Fremdes/unbekanntes Lager: Sqid dekodiert, aber der Org-Scope findet nichts → 404 wie bei Route-Bindings.
        abort_if($request->filled('warehouse') && $warehouses->isEmpty(), 404);

        $variantId = $request->filled('variant') ? Sqid::decodeOrAbort(ArticleVariant::class, (string) $request->query('variant')) : null;
        $articleId = $request->filled('article') ? Sqid::decodeOrAbort(Article::class, (string) $request->query('article')) : null;
        $includeZero = $request->boolean('include_zero');

        $rows = [];
        foreach ($warehouses as $warehouse) {
            $balances = $this->ledger->balancesByVariant($warehouse);
            $binBalances = $this->ledger->binBalancesByVariant($warehouse);
            $variantIds = array_keys($balances);
            if ($variantId !== null) {
                $variantIds = array_values(array_intersect($variantIds, [$variantId]));
            }
            if ($variantIds === []) {
                continue;
            }

            $variants = ArticleVariant::query()
                ->whereKey($variantIds)
                ->when($articleId !== null, fn($q) => $q->where('article_id', $articleId))
                ->with('article:id,name,number')
                ->get()
                ->keyBy('id');
            $bins = WarehouseBin::query()->where('warehouse_id', $warehouse->id)->get()->keyBy('id');

            foreach ($variantIds as $id) {
                $variant = $variants->get($id);
                if ($variant === null) {
                    continue;
                }
                $states = $balances[$id];
                $physical = $states[StockState::Physical->value] ?? '0';
                $reserved = $states[StockState::Reserved->value] ?? '0';
                $hasStock = false;
                foreach ($states as $qty) {
                    if (bccomp($qty, '0', InventoryLedger::SCALE) !== 0) {
                        $hasStock = true;
                        break;
                    }
                }
                if (! $includeZero && ! $hasStock) {
                    continue;
                }

                $binRows = [];
                foreach ($binBalances[$id] ?? [] as $binId => $qty) {
                    if ($binId === 0 || bccomp($qty, '0', InventoryLedger::SCALE) === 0) {
                        continue;
                    }
                    $bin = $bins->get($binId);
                    $binRows[] = [
                        'id' => $bin?->sqid,
                        'code' => $bin?->code,
                        'name' => $bin?->name,
                        'qty' => $qty,
                    ];
                }

                $rows[] = [
                    'warehouse' => ['id' => $warehouse->sqid, 'name' => $warehouse->name],
                    'variant' => [
                        'id' => $variant->sqid,
                        'sku' => $variant->sku,
                        'article' => [
                            'id' => Sqid::encode(Article::class, $variant->article_id),
                            'name' => $variant->article->name ?? null,
                            'number' => $variant->article->number ?? null,
                        ],
                    ],
                    'balances' => $states,
                    'available' => bcsub($physical, $reserved, InventoryLedger::SCALE),
                    'bins' => $binRows,
                ];
            }
        }

        $perPage = ArticleApiController::perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        $total = count($rows);

        return response()->json([
            'data' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
            ],
        ]);
    }

    #[OA\Get(
        path: '/inventory/warehouses',
        summary: 'Lager (inkl. Lagerplätze) auflisten',
        tags: ['Inventory'],
        security: [['bearerAuth' => ['inventory:read']]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function warehouses(): JsonResponse {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->with(['bins' => fn($q) => $q->orderBy('sort_order')->orderBy('code')])
            ->orderBy('name')
            ->get()
            ->map(static fn(Warehouse $warehouse): array => [
                'id' => $warehouse->sqid,
                'name' => $warehouse->name,
                'kind' => $warehouse->kind->value,
                'active' => (bool) $warehouse->active,
                'blocked' => (bool) $warehouse->blocked,
                'bins' => $warehouse->bins->map(static fn(WarehouseBin $bin): array => [
                    'id' => $bin->sqid,
                    'code' => $bin->code,
                    'name' => $bin->name,
                    'active' => (bool) $bin->active,
                    'blocked' => (bool) $bin->blocked,
                ])->all(),
            ])
            ->all();

        return response()->json(['data' => $warehouses]);
    }
}
