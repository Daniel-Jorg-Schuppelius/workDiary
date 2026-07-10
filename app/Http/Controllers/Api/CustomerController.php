<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\{Request, Response};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class CustomerController extends Controller {
    #[OA\Get(
        path: '/customers',
        summary: 'Kunden auflisten',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'archived', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Customer::class);
        $query = Customer::query();
        if ($request->boolean('archived') === false) {
            $query->whereNull('archived_at');
        }
        if ($search = $request->string('search')->toString()) {
            $query->whereLikeEscaped('name', $search);
        }

        return CustomerResource::collection($query->orderBy('name')->paginate((int) $request->input('per_page', 25)));
    }

    #[OA\Post(
        path: '/customers',
        summary: 'Kunde anlegen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Created')],
    )]
    public function store(SaveCustomerRequest $request): CustomerResource {
        Gate::authorize('create', Customer::class);
        $data = $request->validated();
        $customer = Customer::create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $request->user()?->organization_id,
        ]);

        return new CustomerResource($customer);
    }

    #[OA\Get(
        path: '/customers/{customer}',
        summary: 'Kunde anzeigen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK'), new OA\Response(response: 404, description: 'Not Found')],
    )]
    public function show(Customer $customer): CustomerResource {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    #[OA\Put(
        path: '/customers/{customer}',
        summary: 'Kunde aktualisieren',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Customer $customer, SaveCustomerRequest $request): CustomerResource {
        Gate::authorize('update', $customer);
        $customer->update($request->validated());

        return new CustomerResource($customer->fresh() ?? $customer);
    }

    #[OA\Delete(
        path: '/customers/{customer}',
        summary: 'Kunde löschen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'No Content')],
    )]
    public function destroy(Customer $customer): Response {
        Gate::authorize('delete', $customer);
        $customer->delete();

        return response()->noContent();
    }
}
