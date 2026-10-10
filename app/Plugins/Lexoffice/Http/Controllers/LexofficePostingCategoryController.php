<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePostingCategoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficePostingCategory;
use App\Plugins\Lexoffice\Services\LexofficeIncomingInvoiceTarget;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Buchungskategorie je Lieferant bzw. Kunde für die Übergabe aus dem
 * Rechnungseingang (Feature 163, MVP-1111). Sie geht der Vorgabe aus den
 * Plugin-Einstellungen vor.
 */
class LexofficePostingCategoryController extends Controller {
    public function supplier(Request $request, Supplier $supplier): RedirectResponse {
        Gate::authorize('update', $supplier);

        return $this->save($request, $supplier, 'outgo');
    }

    public function customer(Request $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        return $this->save($request, $customer, 'income');
    }

    private function save(Request $request, Supplier|Customer $party, string $kind): RedirectResponse {
        $data = $request->validate([
            'category' => ['nullable', 'string', Rule::exists(LexofficePostingCategory::class, 'external_id')
                ->where('organization_id', $party->organization_id)
                ->where('kind', $kind)],
        ]);
        $category = $data['category'] ?? null;

        $reference = ExternalReference::query()
            ->forPlugin((int) $party->organization_id, LexofficePlugin::ID, LexofficeIncomingInvoiceTarget::EXT_TYPE_POSTING_CATEGORY)
            ->forReferenceable($party);
        if ($category === null) {
            $reference->delete();

            return back()->with('success', __('lexoffice::incoming.category.cleared'));
        }

        ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficeIncomingInvoiceTarget::EXT_TYPE_POSTING_CATEGORY,
                'referenceable_type' => $party->getMorphClass(),
                'referenceable_id' => $party->getKey(),
            ],
            ['organization_id' => $party->organization_id, 'external_id' => $category, 'synced_at' => now()],
        );

        return back()->with('success', __('lexoffice::incoming.category.saved'));
    }
}
