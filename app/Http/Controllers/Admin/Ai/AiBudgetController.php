<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiBudgetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Enums\Ai\AiFamily;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiProviderConnection;
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Monatsbudget je Familie (Feature 025, MVP-399): `settings.ai.budget.monthly_units.<familie>`
 * der Organisation; leer gilt die Vorgabe des Betreibers aus `config('ai.budget')`.
 */
class AiBudgetController extends Controller {
    use ResolvesCurrentOrganization;

    public function edit(): View {
        Gate::authorize('create', AiProviderConnection::class);

        $stored = (array) data_get($this->currentOrganization()->settings, 'ai.budget.monthly_units', []);

        return view('admin.ai._budget_dialog', [
            'families' => AiFamily::cases(),
            'stored' => $stored,
        ]);
    }

    public function update(Request $request, SettingsRegistry $registry): RedirectResponse {
        Gate::authorize('create', AiProviderConnection::class);

        $data = $request->validate([
            'monthly_units' => ['array'],
            'monthly_units.llm' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'monthly_units.translation' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
        ]);

        $organization = $this->currentOrganization();
        foreach (AiFamily::cases() as $family) {
            $key = 'ai.budget.monthly_units.' . $family->value;
            $value = $data['monthly_units'][$family->value] ?? null;
            if ($value === null || $value === '') {
                $registry->reset($key, SettingScope::Organization, $organization);
            } else {
                $registry->set($key, (int) $value, SettingScope::Organization, $organization, $this->authUser()->id);
            }
        }

        return redirect()->route('admin.ai.usage')->with('success', __('ai.budget.saved'));
    }
}
