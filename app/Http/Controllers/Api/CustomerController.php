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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);
        $query = Customer::query();
        if ($request->boolean('archived') === false) {
            $query->whereNull('archived_at');
        }
        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return CustomerResource::collection($query->orderBy('name')->paginate((int) $request->input('per_page', 25)));
    }

    public function store(SaveCustomerRequest $request): CustomerResource
    {
        Gate::authorize('create', Customer::class);
        $data = $request->validated();
        $customer = Customer::create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $request->user()?->organization_id,
        ]);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(Customer $customer, SaveCustomerRequest $request): CustomerResource
    {
        Gate::authorize('update', $customer);
        $customer->update($request->validated());

        return new CustomerResource($customer->fresh() ?? $customer);
    }

    public function destroy(Customer $customer): Response
    {
        Gate::authorize('delete', $customer);
        $customer->delete();

        return response()->noContent();
    }
}
