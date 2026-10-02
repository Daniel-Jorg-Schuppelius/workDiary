<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OciCartController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\B2bCatalog;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\SupplierCatalogController;
use App\Models\Inventory\Warehouse;
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\{Supplier, SupplierCatalogSource};
use App\Services\Procurement\{OciCartImportService, PunchoutHandoff};
use App\Support\SqidEncoder;
use ERechnungToolkit\Entities\IdsConnect\IdsCartItem;
use ERechnungToolkit\Enums\IdsItemCharacter;
use ERechnungToolkit\Parsers\IdsCartParser;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use RuntimeException;

/**
 * OCI-/IDS-Warenkorb-Übernahme (Feature 050, MVP-096). Der externe Shop sendet
 * den Warenkorb per Form-POST (Felder `NEW_ITEM-*`) an diesen Hook; daraus
 * entsteht ein Bestellentwurf. Lieferant und Lagerort kommen als Sqid aus den
 * Punchout-Setup-Feldern. Modul-Gating über `oci-carts.*` → module.lager.
 * Die aktiven Rücksprünge ({@see hookReturn()} OCI, {@see idsReturn()} IDS)
 * laufen sitzungslos, da Cross-Site-POSTs kein Session-Cookie tragen.
 */
class OciCartController extends Controller {
    use ResolvesCurrentOrganization;

    /**
     * Passiver Warenkorb-Import aus der eigenen Oberfläche.
     *
     * Die CSRF-Ausnahme für diese Route ist am 2026-08-31 entfallen
     * (Sicherheitsscan S-43). Sie half nur, wenn ein Betreiber
     * `SESSION_SAME_SITE=none` setzte — und genau dann war der Endpunkt
     * klassisch CSRF-anfällig: jede fremde Seite konnte im Browser eines
     * angemeldeten Einkäufers Bestellentwürfe anlegen, denn Supplier- und
     * Warehouse-Sqids stehen sichtbar in der Oberfläche. Beim Standard `lax`
     * trug der Cross-Site-POST ohnehin kein Session-Cookie, die Ausnahme war
     * also wirkungslos, solange sie ungefährlich war.
     *
     * Externe Shops binden über {@see hookReturn()} an: sessionlos und über
     * die signierte HOOK_URL autorisiert.
     */
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
     * Rücksprung des aktiven OCI-Absprungs (MVP-096): Der Shop POSTet den
     * Warenkorb an die beim Absprung erzeugte, zeitlich begrenzte signierte
     * HOOK_URL. Die Autorisierung liegt in der Signatur (erzeugt von einem
     * berechtigten Nutzer beim Absprung,
     * {@see SupplierCatalogController::punchout()}). Die Route läuft sitzungslos
     * (`routes/punchout.php`); die Meldungen holt {@see result()} ab.
     */
    public function hookReturn(Request $request, OciCartImportService $service, PunchoutHandoff $handoff): RedirectResponse {
        // Quelle per ID (kein Sqid am Modell) — die Signatur der HOOK_URL
        // schützt gegen Manipulation/Enumeration.
        $source = SupplierCatalogSource::query()->find((int) $request->query('source'));
        $warehouse = $this->resolve(Warehouse::class, (string) $request->query('warehouse'));
        $user = $this->resolve(User::class, (string) $request->query('user'));
        abort_unless($source instanceof SupplierCatalogSource && $warehouse instanceof Warehouse && $user instanceof User, 404);
        [$organization, $supplier] = $this->returnContext($source, $warehouse, $user);

        $lines = $this->parseCart($request);
        if ($lines === []) {
            return $this->relay($handoff, $user, route('supplier-catalogs.show', $source), ['error' => __('procurement.oci.flash.empty_cart')]);
        }

        $result = $service->import($organization, $supplier, $warehouse, $lines, (int) $user->id);

        return $this->relay($handoff, $user, route('purchase-orders.show', $result['order']), [
            $result['matched'] > 0 ? 'success' : 'error' => __('procurement.oci.flash.imported', ['matched' => $result['matched'], 'unmatched' => $result['unmatched']]),
        ]);
    }

    /**
     * IDS-Connect-Rücksprung (MVP-1071): Der Shop POSTet den Warenkorb als XML
     * (Feld `warenkorb`) an die Adresse mit dem Einmal-Token aus dem Absprung.
     * Übernommen werden fehlerfreie Normalpositionen; Alternativ- und
     * Bedarfspositionen, Fehler und Hinweise des Shops gehen in die Meldung.
     */
    public function idsReturn(Request $request, string $token, PunchoutHandoff $handoff, IdsCartParser $parser, OciCartImportService $service): RedirectResponse {
        $context = $handoff->consumeHook($token);
        abort_if($context === null, 404);

        $source = SupplierCatalogSource::query()->find($context['source']);
        $warehouse = Warehouse::query()->find($context['warehouse']);
        $user = User::query()->find($context['user']);
        abort_unless($source instanceof SupplierCatalogSource && $warehouse instanceof Warehouse && $user instanceof User, 404);
        [$organization, $supplier] = $this->returnContext($source, $warehouse, $user);

        try {
            $cart = $parser->parse((string) $request->input('warenkorb', ''));
        } catch (RuntimeException) {
            return $this->relay($handoff, $user, route('supplier-catalogs.show', $source), ['error' => __('procurement.ids.flash.unreadable')]);
        }

        $items = $cart->getItems();
        $failed = array_filter($items, static fn (IdsCartItem $item): bool => $item->hasError());
        $orderable = array_filter($items, static fn (IdsCartItem $item): bool => ! $item->hasError() && $item->getItemCharacter() === IdsItemCharacter::Normal);
        if ($orderable === []) {
            return $this->relay($handoff, $user, route('supplier-catalogs.show', $source), ['error' => __('procurement.oci.flash.empty_cart')]);
        }

        $result = $service->import($organization, $supplier, $warehouse, array_values(array_map(static fn (IdsCartItem $item): array => [
            'vendormat' => $item->getArticleNumber(),
            'description' => $item->getShortText() ?? $item->getArticleNumber(),
            'quantity' => (string) $item->getQuantity(),
            'price' => $item->getNetUnitPrice()?->getAmount(),
        ], $orderable)), (int) $user->id, (string) __('procurement.ids.note'));

        $skipped = count($items) - count($orderable) - count($failed);
        $notes = array_values(array_filter([
            $cart->isOrdered() ? (string) __('procurement.ids.flash.ordered_in_shop') : null,
            $skipped > 0 ? (string) __('procurement.ids.flash.not_ordered', ['count' => $skipped]) : null,
            ...array_map(static fn (IdsCartItem $item): string => $item->getArticleNumber() . ': ' . ($item->getErrorText() ?? (string) $item->getErrorCode()), $failed),
            ...array_map(static fn (IdsCartItem $item): ?string => $item->getNotice() !== null ? $item->getArticleNumber() . ': ' . $item->getNotice() : null, $orderable),
            ...$cart->getWarnings(),
        ]));

        return $this->relay($handoff, $user, route('purchase-orders.show', $result['order']), array_filter([
            $result['matched'] > 0 ? 'success' : 'error' => (string) __('procurement.oci.flash.imported', ['matched' => $result['matched'], 'unmatched' => $result['unmatched']]),
            'warning' => implode(' · ', $notes),
        ]));
    }

    /** Meldungen eines Shop-Rücksprungs in der angemeldeten Sitzung anzeigen. */
    public function result(string $key, PunchoutHandoff $handoff): RedirectResponse {
        $result = $handoff->pullResult($key, (int) Auth::id());
        if ($result === null) {
            return redirect()->route('purchase-orders.index');
        }

        $redirect = redirect()->to($result['target']);
        foreach ($result['flash'] as $level => $message) {
            $redirect->with($level, $message);
        }

        return $redirect;
    }

    /**
     * Prüft, dass Lager und Nutzer zur Quelle gehören, und bindet den
     * Mandantenkontext für die Folgeabfragen (Global Scopes).
     *
     * @return array{0: Organization, 1: Supplier}
     */
    private function returnContext(SupplierCatalogSource $source, Warehouse $warehouse, User $user): array {
        abort_unless((int) $user->organization_id === (int) $source->organization_id, 404);
        abort_unless((int) $warehouse->organization_id === (int) $source->organization_id, 404);

        $organization = Organization::query()->withoutGlobalScopes()->find($source->organization_id);
        abort_unless($organization instanceof Organization, 404);
        app()->instance('currentOrganization', $organization);

        $supplier = Supplier::query()->find($source->supplier_id);
        abort_unless($supplier instanceof Supplier, 404);

        return [$organization, $supplier];
    }

    /** @param  array<string, string>  $flash */
    private function relay(PunchoutHandoff $handoff, User $user, string $target, array $flash): RedirectResponse {
        return redirect()->route('oci-carts.result', $handoff->stashResult((int) $user->id, $target, $flash));
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
