<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fristmerkmale eines Kunden im Cockpit-Dialog (Feature 197, MVP-1630): je
 * eingeschaltetem Regelsatz eine Fassung ab Stichtag mit zuständiger Person.
 * Vorbelegt wie die jüngste Fassung („wie Vorjahr“); Merkmale aus der
 * Klassifikation (Pflegegrad) stehen nur zur Anzeige.
 */
class CustomerDeadlineProfileController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineProfileService $profiles,
    ) {}

    public function edit(Request $request, Customer $customer): View {
        $this->assertSameOrganization($customer);
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();
        $enabled = $this->ruleSets->enabled($organization);
        abort_if($enabled === [], 404);
        $ruleSet = $enabled[$request->string('rule_set')->toString()] ?? reset($enabled);

        $latest = $this->profiles->latest($customer, $ruleSet->key());

        return view('customer-deadlines._profile_dialog', [
            'customer' => $customer,
            'ruleSet' => $ruleSet,
            'ruleSets' => array_map(static fn (CustomerDeadlineRuleSet $set): string => $set->label(), $enabled),
            'schema' => $ruleSet->traitSchema(),
            'values' => $latest?->traitValues(),
            'derived' => $ruleSet->derivedTraits($customer),
            'versions' => $this->profiles->versions($customer, $ruleSet->key()),
            'responsibleId' => $latest?->responsible_user_id,
            'validFrom' => CarbonImmutable::today(),
            'members' => User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse {
        $this->assertSameOrganization($customer);
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();
        $enabled = $this->ruleSets->enabled($organization);

        $request->merge(['responsible_user_id' => is_scalar($value = $request->input('responsible_user_id')) && $value !== '' ? Sqid::decodeOrNumeric(User::class, (string) $value) : null]);
        $base = $request->validate([
            'rule_set' => ['required', 'string', Rule::in(array_keys($enabled))],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization()],
        ]);
        $ruleSet = $enabled[$request->string('rule_set')->toString()];
        $data = $request->validate($this->profiles->rules($ruleSet));

        $responsible = $request->input('responsible_user_id') !== null
            ? User::query()->where('organization_id', $organization->id)->find((int) $request->input('responsible_user_id'))
            : null;

        $this->profiles->save(
            $customer,
            $ruleSet,
            is_array($data['traits'] ?? null) ? $data['traits'] : [],
            CarbonImmutable::parse((string) $base['valid_from'])->startOfDay(),
            $responsible instanceof User ? $responsible : null,
            $this->authUser(),
        );

        return back()->with('success', __('customer_deadline.flash.profile_saved', ['rule_set' => $ruleSet->label()]));
    }
}
