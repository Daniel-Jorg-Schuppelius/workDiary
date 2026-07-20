<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Expense, User};
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

/**
 * REST-API Spesen (Feature 008 MVP „Kernobjekte"; Vollaudit 2026-07, M3):
 * read-first — ohne expense.viewAny nur die EIGENEN Belege (Sichtbarkeit wie
 * Web-Index und globale Suche).
 */
class ExpenseApiController extends Controller {
    #[OA\Get(
        path: '/expenses',
        summary: 'Spesen auflisten',
        tags: ['Expenses'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        // Sichtbarkeit wie die Web-Oberfläche: eigene Belege; nur Admins
        // (Freigabe-Rolle, ExpensePolicy::decide via Admin-Bypass) sehen alle.
        $query = Expense::query()
            ->when(! $user->isAdmin(), fn($q) => $q->where('user_id', $user->id))
            ->when($request->filled('from'), fn($q) => $q->whereDate('date', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('date', '<=', (string) $request->query('to')))
            ->when($request->filled('status'), fn($q) => $q->where('status', (string) $request->query('status')))
            ->with(['user:id,name', 'category:id,label'])
            ->orderByDesc('date');

        $page = $query->paginate(min(100, (int) $request->input('per_page', 25)));

        return response()->json([
            'data' => collect($page->items())->map(fn(Expense $expense): array => $this->serialize($expense))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    #[OA\Get(
        path: '/expenses/{expense}',
        summary: 'Spese anzeigen',
        tags: ['Expenses'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'expense', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK'), new OA\Response(response: 404, description: 'Not Found')],
    )]
    public function show(Request $request, Expense $expense): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $expense->user_id === (int) $user->id, 403);

        return response()->json(['data' => $this->serialize($expense->load(['user:id,name', 'category:id,label']))]);
    }

    /** @return array<string, mixed> */
    private function serialize(Expense $expense): array {
        return [
            'id' => $expense->id,
            'date' => $expense->date->toDateString(),
            'user' => ['id' => $expense->user_id, 'name' => $expense->user->name ?? null],
            'vendor' => $expense->vendor,
            'description' => $expense->description,
            'category' => $expense->category->label ?? null,
            'status' => $expense->status->value,
            'billable' => (bool) $expense->billable,
            'amount_net' => (string) $expense->amount_net,
            'amount_gross' => (string) $expense->amount_gross,
            'currency' => $expense->currency->value,
        ];
    }
}
