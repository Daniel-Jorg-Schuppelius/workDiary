<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwarePlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Enums\Lexoffice\{LexwareFeature, LexwarePlan};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Plugins\Lexoffice\Tariff\{LexwareFeatureResolver, LexwarePlanMatrix, LexwareTariffService};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * „Ergänzungen zu Ihrem Lexware-Tarif" (Feature 158, MVP-831): Tarifprofil
 * pflegen, Funktionsmatrix mit Zuständen anzeigen, lokale Ergänzungen bewusst
 * aktivieren, Tarifwechsel mit Vorschau. Funktioniert ohne API-Verbindung.
 */
class LexwarePlanController extends Controller {
    public function __construct(
        private readonly LexwareTariffService $tariffs,
        private readonly LexwareFeatureResolver $features,
        private readonly LexwarePlanMatrix $matrix,
    ) {}

    public function index(): View {
        Gate::authorize(Permission::InvoiceViewAny->value);
        $user = $this->authUser();
        $organization = $user->organization ?? abort(403);
        $profile = $this->tariffs->profile();

        return view('lexoffice::plan.index', [
            'profile' => $profile,
            'effectivePlan' => $profile->effectivePlan(),
            'availabilities' => $this->features->resolve($organization, $user),
            'matrix' => $this->matrix,
            'plans' => LexwarePlan::cases(),
            'localFeatures' => array_values(array_filter(LexwareFeature::cases(), static fn (LexwareFeature $f): bool => $f->isLocalMvp())),
            'preview' => $this->tariffs->changePreview($organization, $profile->effectivePlan()),
            'billsLocally' => $this->features->organizationBillsLocally($organization),
            'canEdit' => Gate::allows(Permission::FinanceConfig->value),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize(Permission::FinanceConfig->value);
        $user = $this->authUser();
        $organization = $user->organization ?? abort(403);

        $data = $request->validate([
            'plan' => ['required', Rule::enum(LexwarePlan::class)],
            'plan_source' => ['required', Rule::in(['user', 'provider'])],
            'plan_confirmed_on' => ['nullable', 'date'],
            'trial_ends_on' => ['nullable', 'date'],
            'trial_successor_plan' => ['nullable', Rule::enum(LexwarePlan::class)],
            'handover_channel' => ['required', Rule::in([LexwareTariffService::CHANNEL_MANUAL, LexwareTariffService::CHANNEL_API])],
            'local_features' => ['nullable', 'array'],
            'local_features.*' => [Rule::enum(LexwareFeature::class)],
        ]);

        // Automatische Übergabe erst nach nachgewiesenem Übergabeweg (Paket A/MVP-834):
        // der Kanal ist wählbar, aber ohne XL-Zugang nicht freischaltbar.
        $plan = LexwarePlan::from((string) $data['plan']);
        if ($data['handover_channel'] === LexwareTariffService::CHANNEL_API && ! $this->matrix->allowsOwnApiKey($plan)) {
            return back()->withErrors(['handover_channel' => (string) __('lexware.error.api_channel_needs_xl')])->withInput();
        }

        $this->tariffs->save($organization, $user, [
            'plan' => $plan->value,
            'plan_source' => (string) $data['plan_source'],
            'plan_confirmed_on' => filled($data['plan_confirmed_on'] ?? null) ? \Carbon\CarbonImmutable::parse((string) $data['plan_confirmed_on'])->toDateString() : null,
            'trial_ends_on' => filled($data['trial_ends_on'] ?? null) ? \Carbon\CarbonImmutable::parse((string) $data['trial_ends_on'])->toDateString() : null,
            'trial_successor_plan' => (string) ($data['trial_successor_plan'] ?? LexwarePlan::Unknown->value),
            'handover_channel' => (string) $data['handover_channel'],
            'local_features' => array_values(array_map('strval', (array) ($data['local_features'] ?? []))),
        ]);

        return redirect()->route('lexoffice.plan.index')->with('status', __('lexware.flash.saved'));
    }
}
