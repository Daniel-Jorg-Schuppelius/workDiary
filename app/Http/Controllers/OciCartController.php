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
use App\Models\{Organization, Supplier, SupplierCatalogSource, User, Warehouse};
use App\Services\Procurement\OciCartImportService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * OCI-/IDS-Warenkorb-Übernahme (Feature 050, MVP-096). Der externe Shop sendet
 * den Warenkorb per Form-POST (Felder `NEW_ITEM-*`) an diesen Hook; daraus
 * entsteht ein Bestellentwurf. Lieferant und Lagerort kommen als Sqid aus den
 * Punchout-Setup-Feldern. Modul-Gating über `oci-carts.*` → module.lager.
 * Der aktive Punchout-Rücksprung ({@see hookReturn()}) läuft sessionlos über
 * eine signierte HOOK_URL, da Cross-Site-POSTs kein Session-Cookie tragen.
 */
class OciCartController extends Controller {
    use ResolvesCurrentOrganization;

    public function import(Request $request, OciCartImportService $service): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);

        $supplier = $this->resolve(Supplier::class, (string) $request->input('supplier'));
        $warehouse = $this->resolve(Warehouse::class, (string) $request->input('warehouse'));
        if (! $supplier instanceof Supplier || ! $warehouse instanceof Warehouse) {
            return redirect()->route('purchase-orders.index')->with('error', __('procurement.oci.flash.missing_context'));
        }

        $lines = $this->parseCart($request);
        if ($lines === []) {
            return redirect()->route('purchase-orders.index')->with('error', __('procurement.oci.flash.empty_cart'));
        }

        $result = $service->import($this->currentOrganization(), $supplier, $warehouse, $lines, Auth::id() !== null ? (int) Auth::id() : null);

        $flash = __('procurement.oci.flash.imported', ['matched' => $result['matched'], 'unmatched' => $result['unmatched']]);

        return redirect()->route('purchase-orders.show', $result['order'])
            ->with($result['matched'] > 0 ? 'success' : 'error', $flash);
    }

    /**
     * Rücksprung des aktiven Punchout-Absprungs (MVP-096): Der Shop POSTet den
     * Warenkorb an die beim Absprung erzeugte, zeitlich begrenzte signierte
     * HOOK_URL. Die Autorisierung liegt in der Signatur (erzeugt von einem
     * berechtigten Nutzer beim Absprung,
     * {@see SupplierCatalogController::punchout()}); eine Session ist wegen
     * SameSite-Cookies bei Cross-Site-POSTs nicht vorhanden. Der Redirect nach
     * der Übernahme führt zurück in die eingeloggte Browser-Sitzung.
     */
    public function hookReturn(Request $request, OciCartImportService $service): RedirectResponse {
        // Quelle per ID (kein Sqid am Modell) — die Signatur der HOOK_URL
        // schützt gegen Manipulation/Enumeration.
        $source = SupplierCatalogSource::query()->find((int) $request->query('source'));
        $warehouse = $this->resolve(Warehouse::class, (string) $request->query('warehouse'));
        $user = $this->resolve(User::class, (string) $request->query('user'));

        abort_unless($source instanceof SupplierCatalogSource && $warehouse instanceof Warehouse && $user instanceof User, 404);
        abort_unless((int) $user->organization_id === (int) $source->organization_id, 404);
        abort_unless((int) $warehouse->organization_id === (int) $source->organization_id, 404);

        $organization = Organization::query()->withoutGlobalScopes()->find($source->organization_id);
        abort_unless($organization instanceof Organization, 404);

        // Mandantenkontext für die Folge-Queries (Global Scopes) binden.
        app()->instance('currentOrganization', $organization);

        $supplier = Supplier::query()->find($source->supplier_id);
        abort_unless($supplier instanceof Supplier, 404);

        $lines = $this->parseCart($request);
        if ($lines === []) {
            return redirect()->route('supplier-catalogs.show', $source)->with('error', __('procurement.oci.flash.empty_cart'));
        }

        $result = $service->import($organization, $supplier, $warehouse, $lines, (int) $user->id);

        return redirect()->route('purchase-orders.show', $result['order']);
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
