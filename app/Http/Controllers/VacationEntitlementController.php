<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationEntitlementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{Organization, User, VacationEntitlement};
use App\Services\Absence\VacationBalanceService;
use App\Support\{LookupCache, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Urlaubskonto (MVP-413): Jahresansprüche + Übertrag pflegen. */
class VacationEntitlementController extends Controller {
    public function index(Request $request, VacationBalanceService $balanceService): View {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $year = $this->resolveYear($request);

        $entitlements = VacationEntitlement::query()
            ->with('user:id,name')
            ->where('year', $year)
            ->get()
            ->sortBy(fn(VacationEntitlement $e): string => (string) $e->user?->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $balances = $balanceService->balancesFor(
            $entitlements->pluck('user_id')->map(fn($id): int => (int) $id)->values()->all(),
            $year,
        );

        $usersWithoutEntitlement = User::query()
            ->where('organization_id', $auth->organization_id)
            ->whereNotIn('id', $entitlements->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        /** @var Organization $organization */
        $organization = $auth->organization;

        return view('vacation-entitlements.index', [
            'year' => $year,
            'entitlements' => $entitlements,
            'balances' => $balances,
            'usersWithoutEntitlement' => $usersWithoutEntitlement,
            'defaultDays' => $organization->vacationDefaultDays(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        return view('vacation-entitlements._form_dialog', [
            'entitlement' => null,
            'isEdit' => false,
            'isDialog' => true,
            'year' => $this->resolveYear($request),
            'assignableUsers' => LookupCache::userDropdown(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $this->validateEntitlement($request);

        VacationEntitlement::query()->updateOrCreate(
            [
                'organization_id' => $auth->organization_id,
                'user_id' => $data['user_id'],
                'year' => $data['year'],
            ],
            [
                'entitled_days' => $data['entitled_days'],
                'severely_disabled_days' => $data['severely_disabled_days'] ?? 0,
                'other_days' => $data['other_days'] ?? 0,
                'carryover_days' => $data['carryover_days'] ?? 0,
                'carryover_expires_on' => $data['carryover_expires_on'] ?? null,
                'note' => $data['note'] ?? null,
            ],
        );

        return redirect()->route('vacation-entitlements.index', ['year' => $data['year']])
            ->with('success', __('Urlaubsanspruch gespeichert.'));
    }

    public function edit(VacationEntitlement $vacationEntitlement): View {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        return view('vacation-entitlements._form_dialog', [
            'entitlement' => $vacationEntitlement,
            'isEdit' => true,
            'isDialog' => true,
            'year' => (int) $vacationEntitlement->year,
            'assignableUsers' => LookupCache::userDropdown(),
        ]);
    }

    public function update(Request $request, VacationEntitlement $vacationEntitlement): RedirectResponse {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        $data = $this->validateEntitlement($request, $vacationEntitlement);

        $vacationEntitlement->update([
            'entitled_days' => $data['entitled_days'],
            'severely_disabled_days' => $data['severely_disabled_days'] ?? 0,
            'other_days' => $data['other_days'] ?? 0,
            'carryover_days' => $data['carryover_days'] ?? 0,
            'carryover_expires_on' => $data['carryover_expires_on'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('vacation-entitlements.index', ['year' => $vacationEntitlement->year])
            ->with('success', __('Urlaubsanspruch aktualisiert.'));
    }

    public function destroy(VacationEntitlement $vacationEntitlement): RedirectResponse {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        $year = (int) $vacationEntitlement->year;
        $vacationEntitlement->delete();

        return redirect()->route('vacation-entitlements.index', ['year' => $year])
            ->with('success', __('Urlaubsanspruch gelöscht.'));
    }

    /** Bulk-Anlage: fehlende Ansprüche eines Jahres mit Default-Tagen anlegen; merkt den Default in den Org-Einstellungen. */
    public function bulk(Request $request): RedirectResponse {
        Gate::authorize(Permission::VacationEntitlementsManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'default_days' => ['required', 'numeric', 'between:0,365'],
        ]);
        $year = (int) $data['year'];
        $defaultDays = round((float) $data['default_days'], 1);

        /** @var Organization $organization */
        $organization = $auth->organization;
        $settings = (array) ($organization->settings ?? []);
        data_set($settings, 'vacation.default_days', $defaultDays);
        $organization->update(['settings' => $settings]);

        $existing = VacationEntitlement::query()
            ->where('year', $year)
            ->pluck('user_id');

        $missingUsers = User::query()
            ->where('organization_id', $auth->organization_id)
            ->whereNotIn('id', $existing)
            ->pluck('id');

        foreach ($missingUsers as $userId) {
            VacationEntitlement::create([
                'organization_id' => $auth->organization_id,
                'user_id' => (int) $userId,
                'year' => $year,
                'entitled_days' => $defaultDays,
                'carryover_days' => 0,
            ]);
        }

        return redirect()->route('vacation-entitlements.index', ['year' => $year])
            ->with('success', __(':count Urlaubsansprüche angelegt.', ['count' => $missingUsers->count()]));
    }

    private function resolveYear(Request $request): int {
        $year = (int) $request->query('year', (string) now()->year);

        return ($year >= 2000 && $year <= 2100) ? $year : (int) now()->year;
    }

    /** @return array<string, mixed> */
    private function validateEntitlement(Request $request, ?VacationEntitlement $existing = null): array {
        if ($existing === null) {
            $request->merge([
                'user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id')),
            ]);
        }

        return $request->validate([
            'user_id' => $existing === null
                ? ['required', new \App\Rules\ExistsInCurrentOrganization()]
                : ['prohibited'],
            'year' => $existing === null
                ? ['required', 'integer', 'between:2000,2100']
                : ['prohibited'],
            'entitled_days' => ['required', 'numeric', 'between:0,365'],
            // MVP-535: getrennte Anspruchskomponenten (SGB IX, Sonstige).
            'severely_disabled_days' => ['nullable', 'numeric', 'between:0,365'],
            'other_days' => ['nullable', 'numeric', 'between:0,365'],
            'carryover_days' => ['nullable', 'numeric', 'between:0,365'],
            'carryover_expires_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
