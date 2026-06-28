<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OciCartController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\{Supplier, Warehouse};
use App\Services\Procurement\OciCartImportService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * OCI-/IDS-Warenkorb-Übernahme (Feature 050, MVP-096). Der externe Shop sendet
 * den Warenkorb per Form-POST (Felder `NEW_ITEM-*`) an diesen Hook; daraus
 * entsteht ein Bestellentwurf. Lieferant und Lagerort kommen als Sqid aus den
 * Punchout-Setup-Feldern. Modul-Gating über `oci-carts.*` → module.lager.
 */
class OciCartController extends Controller {
    use ResolvesCurrentOrganization;

    public function import(Request $request, OciCartImportService $service): RedirectResponse {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);

        $supplier = $this->resolve(Supplier::class, (string) $request->input('supplier'));
        $warehouse = $this->resolve(Warehouse::class, (string) $request->input('warehouse'));
        if (! $supplier instanceof Supplier || ! $warehouse instanceof Warehouse) {
            return redirect()->route('purchase-orders.index')->with('error', __('procurement.oci.flash.missing_context'));
        }

        $lines = $this->parseCart($request);
        if ($lines === []) {
            return redirect()->route('purchase-orders.index')->with('error', __('procurement.oci.flash.empty_cart'));
        }

        $result = $service->import($this->currentOrganization(), $supplier, $warehouse, $lines);

        $flash = __('procurement.oci.flash.imported', ['matched' => $result['matched'], 'unmatched' => $result['unmatched']]);

        return redirect()->route('purchase-orders.show', $result['order'])
            ->with($result['matched'] > 0 ? 'success' : 'error', $flash);
    }

    /**
     * Liest die `NEW_ITEM-*`-Arrays des OCI-Warenkorbs aus.
     *
     * @return list<array{vendormat: ?string, description: ?string, quantity: ?string, price: ?string}>
     */
    private function parseCart(Request $request): array {
        $descriptions = (array) $request->input('NEW_ITEM-DESCRIPTION', []);
        $vendormats = (array) $request->input('NEW_ITEM-VENDORMAT', []);
        $quantities = (array) $request->input('NEW_ITEM-QUANTITY', []);
        $prices = (array) $request->input('NEW_ITEM-PRICE', []);

        $keys = array_keys($descriptions + $vendormats);
        $lines = [];
        foreach ($keys as $i) {
            $lines[] = [
                'vendormat' => isset($vendormats[$i]) ? (string) $vendormats[$i] : null,
                'description' => isset($descriptions[$i]) ? (string) $descriptions[$i] : null,
                'quantity' => isset($quantities[$i]) ? (string) $quantities[$i] : null,
                'price' => isset($prices[$i]) ? (string) $prices[$i] : null,
            ];
        }

        return $lines;
    }

    /**
     * @param  class-string  $model
     */
    private function resolve(string $model, string $sqid): ?object {
        $id = app(SqidEncoder::class)->decode($model, $sqid);

        return $id !== null ? $model::query()->find($id) : null;
    }
}
