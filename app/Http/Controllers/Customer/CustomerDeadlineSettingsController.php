<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineSettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerDeadline;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Customer\Deadlines\{CustomerDeadlineGenerator, CustomerDeadlineRuleSets};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Einstellungen der Kundenfristen einer Organisation (Feature 197, MVP-1630/1633):
 * eingeschaltete Regelsätze und Vorwarnung (Vorgabe 7 Tage, je Regelsatz
 * überschreibbar). Nach dem Einschalten läuft der Jahreslauf der Organisation.
 */
class CustomerDeadlineSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineGenerator $generator,
    ) {}

    public function edit(): View {
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();

        return view('customer-deadlines._settings_dialog', [
            'available' => array_map(static fn (CustomerDeadlineRuleSet $set): string => $set->label(), $this->ruleSets->available($organization)),
            'enabled' => $this->ruleSets->enabledKeys($organization),
            'warnDays' => $this->ruleSets->defaultWarnDays($organization),
            'byRuleSet' => $this->ruleSets->warnDaysByRuleSet($organization),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();
        $keys = array_keys($this->ruleSets->available($organization));
        $data = $request->validate([
            'rule_sets' => ['nullable', 'array'],
            'rule_sets.*' => ['string', Rule::in($keys)],
            'warn_days' => ['required', 'integer', 'min:0', 'max:' . CustomerDeadlineRuleSets::MAX_WARN_DAYS],
            'warn_days_by_rule_set' => ['nullable', 'array'],
            'warn_days_by_rule_set.*' => ['nullable', 'integer', 'min:0', 'max:' . CustomerDeadlineRuleSets::MAX_WARN_DAYS],
        ]);

        $this->ruleSets->setEnabled($organization, array_values(array_map('strval', (array) ($data['rule_sets'] ?? []))));
        $overrides = [];
        foreach ((array) ($data['warn_days_by_rule_set'] ?? []) as $key => $days) {
            if (is_string($key) && in_array($key, $keys, true)) {
                $overrides[$key] = $days !== null && $days !== '' ? (int) $days : null;
            }
        }
        $this->ruleSets->saveWarnDays($organization, (int) $data['warn_days'], $overrides);
        $this->generator->syncOrganization($organization->refresh());

        return back()->with('success', __('customer_deadline.flash.settings_saved'));
    }
}
