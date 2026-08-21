<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Supplier\{SupplierCredential, SupplierCredentialType};
use App\Services\Supplier\SupplierCredentialService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pflichtnachweise am Lieferanten (Feature 117, MVP-606).
 */
class SupplierCredentialController extends Controller {
    public function __construct(private readonly SupplierCredentialService $credentials) {}

    /** Ampel-Übersicht über alle Lieferanten mit Pflichtnachweisen. */
    public function index(): View {
        Gate::authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()->orderBy('name')->get();
        $rows = $suppliers->map(fn (Supplier $supplier): array => [
            'supplier' => $supplier,
            'status' => $this->credentials->overallStatus($supplier),
            'items' => $this->credentials->statusFor($supplier),
        ])->sortByDesc(fn (array $row): int => $row['status']->isBlocking() ? 2 : ($row['status']->value === 'expiring' ? 1 : 0));

        return view('suppliers.credentials.index', [
            'rows' => $rows->values(),
            'blockingEnabled' => $suppliers->isNotEmpty() && $this->credentials->blockingEnabled($suppliers->first()),
        ]);
    }

    public function form(Supplier $supplier, ?SupplierCredential $credential = null): View {
        Gate::authorize('update', $supplier);

        return view('suppliers.credentials._form_dialog', [
            'supplier' => $supplier,
            'credential' => $credential,
            'types' => SupplierCredentialType::query()
                ->where('is_active', true)
                ->where(function ($query) use ($supplier): void {
                    $query->whereNull('organization_id')->orWhere('organization_id', $supplier->organization_id);
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Supplier $supplier): RedirectResponse {
        Gate::authorize('update', $supplier);
        $data = $this->validated($request, $supplier);

        SupplierCredential::query()->create($data + [
            'organization_id' => $supplier->organization_id,
            'supplier_id' => $supplier->id,
            'checked_by' => $request->user()?->id,
            'checked_at' => now()->toDateString(),
        ]);

        return back()->with('status', __('procurement.credentials.saved'));
    }

    public function destroy(Supplier $supplier, SupplierCredential $credential): RedirectResponse {
        Gate::authorize('update', $supplier);
        abort_unless((int) $credential->supplier_id === (int) $supplier->id, 404);

        $credential->delete();

        return back()->with('status', __('procurement.credentials.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Supplier $supplier): array {
        $request->merge([
            'supplier_credential_type_id' => Sqid::decodeOrNumeric(
                SupplierCredentialType::class,
                (string) $request->input('supplier_credential_type_id'),
            ),
        ]);

        $data = $request->validate([
            'supplier_credential_type_id' => ['required', 'integer'],
            'issuer' => ['nullable', 'string', 'max:191'],
            'reference' => ['nullable', 'string', 'max:64'],
            'issued_on' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Der Typ muss aus dem Katalog ODER der eigenen Organisation stammen —
        // ein rohes `exists:` würde fremde Org-Typen zulassen.
        $typeExists = SupplierCredentialType::query()
            ->whereKey((int) $data['supplier_credential_type_id'])
            ->where(function ($query) use ($supplier): void {
                $query->whereNull('organization_id')->orWhere('organization_id', $supplier->organization_id);
            })
            ->exists();
        abort_unless($typeExists, 422);

        return $data;
    }
}
