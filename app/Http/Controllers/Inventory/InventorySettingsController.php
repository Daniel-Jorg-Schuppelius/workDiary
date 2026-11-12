<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventorySettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Enums\Numbering\NumberScope;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\Inventory\{NegativeStockApproval, ValuationMethodResolver, ValuationMethodSwitch};
use App\Services\Numbering\NumberAuthority;
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lager-Einstellungen der Organisation (Feature 048, MVP-1579/1580):
 * Bewertungsverfahren und Reservierungszeitpunkt. Das Bewertungsverfahren läuft
 * über {@see ValuationMethodSwitch}, weil ein Wechsel den Bestand ins andere
 * Bewertungsbuch übertragen muss.
 */
class InventorySettingsController extends Controller {
    use ResolvesCurrentOrganization;

    private const string RESERVATION_TIMING = 'inventory.reservation_timing';

    public function edit(ValuationMethodResolver $resolver, SettingsRegistry $registry, NumberAuthority $authority): View {
        Gate::authorize(P::InventoryConfigure->value);

        $organization = $this->currentOrganization();

        return view('inventory._settings_dialog', [
            'valuationMethod' => $resolver->methodFor($organization),
            // SKU-/GTIN-Hoheit (MVP-1583): gewählt wird sie beim Nummernkreis „Artikel“.
            'articleNumberSource' => $authority->sourceLabel($authority->sourceFor($organization, NumberScope::Article)),
            'reservationTiming' => (string) $registry->effective(self::RESERVATION_TIMING, $organization)->value,
            'fourEyesCount' => (bool) $registry->effective('inventory.four_eyes.count', $organization)->value,
            'fourEyesThreshold' => (string) ($registry->effective('inventory.four_eyes.count_threshold', $organization)->value ?? '0'),
            'fourEyesNegative' => (bool) $registry->effective(NegativeStockApproval::SETTING, $organization)->value,
            'reservationTimings' => (array) $registry->definition(self::RESERVATION_TIMING)->resolvedOptions(),
        ]);
    }

    public function update(Request $request, ValuationMethodSwitch $switch, SettingsRegistry $registry): RedirectResponse {
        Gate::authorize(P::InventoryConfigure->value);

        $data = $request->validate([
            'valuation_method' => ['required', Rule::enum(ValuationMethod::class)],
            'reservation_timing' => ['required', Rule::in((array) $registry->definition(self::RESERVATION_TIMING)->resolvedOptions())],
            'four_eyes_count' => ['sometimes', 'boolean'],
            'four_eyes_count_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'four_eyes_negative' => ['sometimes', 'boolean'],
        ]);

        $organization = $this->currentOrganization();
        $layers = $switch->switchOrganization($organization, ValuationMethod::from((string) $data['valuation_method']));
        Setting::set(self::RESERVATION_TIMING, (string) $data['reservation_timing'], SettingScope::Organization, $organization);
        // Vier-Augen-Freigabe der Inventurdifferenzen (MVP-1585); Felder fehlen bei Alt-Clients → unverändert.
        if (array_key_exists('four_eyes_count', $data)) {
            Setting::set('inventory.four_eyes.count', (bool) $data['four_eyes_count'], SettingScope::Organization, $organization);
            Setting::set('inventory.four_eyes.count_threshold', (float) ($data['four_eyes_count_threshold'] ?? 0), SettingScope::Organization, $organization);
        }
        if (array_key_exists('four_eyes_negative', $data)) {
            Setting::set(NegativeStockApproval::SETTING, (bool) $data['four_eyes_negative'], SettingScope::Organization, $organization);
        }

        return redirect()->route('inventory.stock')->with('success', $layers > 0
            ? trans_choice('inventory.settings.flash.saved_layers', $layers, ['count' => $layers])
            : __('inventory.settings.flash.saved'));
    }
}
