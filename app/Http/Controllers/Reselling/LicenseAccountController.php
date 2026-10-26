<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAccountController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\LinkOrigin;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reselling\Concerns\ProvidesHolderPicker;
use App\Http\Requests\Reselling\SaveResaleAllocationRequest;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResaleAccountAllocation, ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Register\{AllocationDeriver, LicenseAccountGroup, LicenseAccountOverview, LicenseAccounts};
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;

/**
 * Lizenzkonten (Feature 185, MVP-1400/1401): je Anbieterkonto die Lizenzen
 * je Produkt, die Aufteilung auf Halter, das Raster der Lizenzjahre und die
 * Prüffälle an den Bezügen; Aufteilungen werden hier gepflegt.
 */
class LicenseAccountController extends Controller {
    use ProvidesHolderPicker;
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly LicenseAccounts $accounts,
        private readonly LicenseAccountOverview $overview,
        private readonly AllocationDeriver $deriver,
    ) {}

    public function index(): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $rows = $this->overview->overview($organization);

        return view('finance.resale.accounts.index', [
            'rows' => $rows,
            'summary' => [
                'accounts' => count($rows),
                'open' => array_sum(array_column($rows, 'open')),
                'conflicts' => array_sum(array_column($rows, 'conflicts')),
            ],
        ]);
    }

    public function show(string $account): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOrAbort($organization, $account);

        return view('finance.resale.accounts.show', $this->overview->detail($organization, $group) + [
            'today' => ResalePeriod::today(),
            'canManage' => $this->authUser()->can(Permission::ResellingManage->value),
        ]);
    }

    public function allocationCreate(string $account): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOrAbort($organization, $account);

        return $this->dialog($group, null);
    }

    public function allocationStore(SaveResaleAllocationRequest $request, string $account): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOrAbort($organization, $account);
        $holder = $request->holder();
        $allocation = ResaleAccountAllocation::query()->create($this->attributes($request, $group) + [
            'organization_id' => $organization->id,
            'provider' => $group->provider,
            'provider_account' => $group->account,
            'created_by' => $request->user()?->id,
        ]);
        $allocation->audit('resale_account_allocation.saved', ['holder' => $allocation->holderLabel(), 'product' => $allocation->product_label, 'quantity' => $allocation->quantity]);

        return $this->applied($organization, $group, __('resale.account.allocation.flash.saved', ['holder' => $holder['own'] ? __('resale.holder.own') : ($holder['foreign'] !== null ? $holder['foreign']->name : ($holder['customer'] !== null ? $holder['customer']->name : '')), 'quantity' => $allocation->quantity]));
    }

    public function allocationEdit(ResaleAccountAllocation $allocation): View {
        $organization = $this->currentOrganizationOrAbort(404);

        return $this->dialog($this->groupOf($organization, $allocation), $allocation);
    }

    public function allocationUpdate(SaveResaleAllocationRequest $request, ResaleAccountAllocation $allocation): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOf($organization, $allocation);
        $allocation->fill($this->attributes($request, $group) + ['updated_by' => $request->user()?->id])->save();
        $allocation->audit('resale_account_allocation.saved', ['holder' => $allocation->holderLabel(), 'product' => $allocation->product_label, 'quantity' => $allocation->quantity]);

        return $this->applied($organization, $group, __('resale.account.allocation.flash.updated'));
    }

    public function allocationDestroy(ResaleAccountAllocation $allocation): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOf($organization, $allocation);
        $removed = $this->deriver->removeFor($allocation);
        $allocation->audit('resale_account_allocation.removed', ['holder' => $allocation->holderLabel(), 'product' => $allocation->product_label, 'assignments' => $removed]);
        $allocation->delete();

        return $this->applied($organization, $group, __('resale.account.allocation.flash.deleted'));
    }

    /**
     * Vorlagenposition einem Abo des Kontos zuordnen (Feature 185, MVP-1411):
     * ein Vorschlag wird bestätigt, sonst entsteht eine manuelle Zuordnung.
     */
    public function planLinkStore(Request $request, string $account): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $group = $this->groupOrAbort($organization, $account);
        $data = $request->validate([
            'subscription_id' => ['required', 'string', 'max:32'],
            'plan_key' => ['required', 'string', 'max:120'],
            'line_position' => ['required', 'integer', 'min:0', 'max:65535'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $subscriptionId = Sqid::decode(ResaleSubscription::class, (string) $data['subscription_id']);
        $subscription = $subscriptionId !== null ? $group->subscriptions->firstWhere('id', $subscriptionId) : null;
        if (! $subscription instanceof ResaleSubscription) {
            throw ValidationException::withMessages(['subscription_id' => (string) __('resale.plan.error.subscription')]);
        }
        $link = ResalePlanLink::query()->firstOrNew(['subscription_id' => $subscription->id, 'plan_key' => (string) $data['plan_key'], 'line_position' => (int) $data['line_position']]);
        $link->fill([
            'organization_id' => $organization->id,
            'quantity' => (int) $data['quantity'],
            'origin' => $link->exists && $link->origin === LinkOrigin::Proposed ? LinkOrigin::Confirmed : ($link->exists ? $link->origin : LinkOrigin::Manual),
            'confirmed_at' => now(),
            'created_by' => $link->exists ? $link->created_by : $request->user()?->id,
        ])->save();
        $link->audit('resale_plan_link.saved', ['plan' => $link->plan_key, 'position' => $link->line_position, 'quantity' => $link->quantity, 'subscription' => $subscription->label]);

        return redirect()->route('finance.resale.accounts.show', $group->routeKey())->with('success', __('resale.plan.flash.saved'));
    }

    public function planLinkDestroy(ResalePlanLink $link): RedirectResponse {
        $link->audit('resale_plan_link.removed', ['plan' => $link->plan_key, 'position' => $link->line_position, 'quantity' => $link->quantity]);
        $link->delete();

        return back()->with('success', __('resale.plan.flash.deleted'));
    }

    /** Aufteilung anwenden und mit Hinweis auf Konflikte zum Konto zurück. */
    private function applied(Organization $organization, LicenseAccountGroup $group, string $message): RedirectResponse {
        $fresh = $this->accounts->group($organization, $group->key) ?? $group;
        $result = $this->deriver->apply($organization, $fresh);
        $redirect = redirect()->route('finance.resale.accounts.show', $fresh->routeKey())->with('success', $message);
        if ($result['conflicts'] !== []) {
            $redirect->with('warning', implode(' · ', array_map(static fn(array $c): string => (string) __('resale.account.allocation.conflict', ['missing' => $c['missing'], 'product' => $c['allocation']->product_label]), $result['conflicts'])));
        }

        return $redirect;
    }

    /** @return array<string, mixed> */
    private function attributes(SaveResaleAllocationRequest $request, LicenseAccountGroup $group): array {
        $products = $group->products();
        $key = $request->productKey();
        if (! isset($products[$key])) {
            throw ValidationException::withMessages(['product_key' => (string) __('resale.account.allocation.error.product')]);
        }
        $holder = $request->holder();

        return [
            'product_key' => $key,
            'product_label' => $products[$key],
            'customer_id' => $holder['own'] ? null : ($holder['foreign'] === null ? $holder['customer']?->id : null),
            'foreign_customer_id' => $holder['own'] ? null : $holder['foreign']?->id,
            'is_own_holding' => $holder['own'],
            'quantity' => (int) $request->validated('quantity'),
            'valid_from' => (string) $request->validated('valid_from'),
            'valid_until' => $request->validated('valid_until') ?: null,
            'note' => $request->note(),
        ];
    }

    private function dialog(LicenseAccountGroup $group, ?ResaleAccountAllocation $allocation): View {
        return view('finance.resale.accounts._allocation_dialog', [
            'group' => $group,
            'allocation' => $allocation,
            'products' => $group->products(),
            'today' => ResalePeriod::today(),
        ] + $this->holderPicker());
    }

    private function groupOrAbort(Organization $organization, string $key): LicenseAccountGroup {
        return $this->accounts->group($organization, $key) ?? abort(404);
    }

    private function groupOf(Organization $organization, ResaleAccountAllocation $allocation): LicenseAccountGroup {
        return $this->groupOrAbort($organization, ResaleSubscription::accountKeyOf($allocation->provider, $allocation->provider_account));
    }
}
