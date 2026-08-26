<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\{PurchaseOrder, Supplier, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * REST-API Bestellungen (MVP-718, Vollscan J11): read-only. Rechte wie der
 * Web-Controller (inventory.viewAny ODER inventory.post), Ability
 * `purchase_orders:read`, Plan-Gating `module.lager`.
 */
class PurchaseOrderApiController extends Controller {
    #[OA\Get(
        path: '/purchase-orders',
        summary: 'Bestellungen auflisten',
        tags: ['Purchase Orders'],
        security: [['bearerAuth' => ['purchase_orders:read']]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'ordered', 'partially_received', 'received', 'cancelled'])),
            new OA\Parameter(name: 'supplier', in: 'query', required: false, description: 'Lieferanten-Sqid', schema: new OA\Schema(type: 'string', example: 'Lf8Qw1')),
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'ordered_at ≥ (Datum)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'ordered_at ≤ (Datum)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 423, description: 'Modul nicht lizenziert'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        $this->authorizeRead($request);

        $query = PurchaseOrder::query()
            ->with(['supplier:id,name', 'warehouse:id,name'])
            ->when(PurchaseOrderStatus::tryFrom((string) $request->query('status', '')) !== null, fn($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('supplier'), fn($q) => $q->where('supplier_id', Sqid::decodeOrAbort(Supplier::class, (string) $request->query('supplier'))))
            ->when($request->filled('from'), fn($q) => $q->whereDate('ordered_at', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('ordered_at', '<=', (string) $request->query('to')))
            ->orderByDesc('id');

        return PurchaseOrderResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/purchase-orders/{purchaseOrder}',
        summary: 'Bestellung anzeigen (inkl. Positionen)',
        tags: ['Purchase Orders'],
        security: [['bearerAuth' => ['purchase_orders:read']]],
        parameters: [new OA\Parameter(name: 'purchaseOrder', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'Bo3Hf6'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource {
        $this->authorizeRead($request);

        return new PurchaseOrderResource($purchaseOrder->load(['supplier:id,name', 'warehouse:id,name', 'lines' => fn($q) => $q->orderBy('id')]));
    }

    private function authorizeRead(Request $request): void {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null && ($user->can(Permission::InventoryViewAny->value) || $user->can(Permission::InventoryPost->value)), 403);
    }
}
