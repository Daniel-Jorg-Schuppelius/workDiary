<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetalQuotationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\MetalQuotation;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Metallnotierungen (Feature 107, MVP-564): org-weite Tagespreise je Rohstoff
 * (Kupfer-DEL & Co., €/kg) für die Kupferzuschlag-Berechnung der
 * DATANORM-Katalogartikel. Schlanke Pflegeseite: Liste, Anlage, Löschung.
 */
class MetalQuotationController extends Controller {
    use ResolvesCurrentOrganization;

    /** Rohstoffmerker der DATANORM-Tabelle. */
    private const METALS = ['CU', 'AL', 'AG', 'AU', 'MS', 'NI', 'PB', 'SN', 'ZN', 'W', 'CR', 'MG', 'PL'];

    public function index(): View {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);

        return view('supplier-catalogs.metal_quotations', [
            'quotations' => MetalQuotation::query()->orderBy('metal')->orderByDesc('quoted_at')->limit(100)->get(),
            'metals' => self::METALS,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);
        $data = $request->validate([
            'metal' => ['required', 'string', 'in:' . implode(',', self::METALS)],
            'price_per_kg' => ['required', 'numeric', 'min:0', 'max:999999'],
            'quoted_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        MetalQuotation::query()->updateOrCreate(
            [
                'organization_id' => $this->currentOrganization()->id,
                'metal' => strtoupper($data['metal']),
                'quoted_at' => $data['quoted_at'],
            ],
            ['price_per_kg' => $data['price_per_kg'], 'currency' => 'EUR']
        );

        return back()->with('success', (string) __('procurement.metal.flash.saved'));
    }

    public function destroy(MetalQuotation $metalQuotation): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);
        abort_unless($metalQuotation->organization_id === $this->currentOrganization()->id, 404);
        $metalQuotation->delete();

        return back()->with('success', (string) __('procurement.metal.flash.deleted'));
    }
}
