<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Platform\TenantPlanRequestStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\TenantPlanRequest;
use CommonToolkit\Helper\Data\VatNumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rechnungsdaten und Tarifwechsel-Anfrage des Mandanten (MVP-957). Die
 * Anschrift liegt in `organizations.settings.billing_contact`; den Tarif
 * ändert nur der Betreiber über die Lizenz.
 */
class BillingProfileController extends Controller {
    use ResolvesCurrentOrganization;

    public const CONTACT_FIELDS = ['name', 'email', 'street', 'zip', 'city', 'country', 'vat_id', 'reference'];

    public function show(): View {
        $this->authorizeBilling();
        $organization = $this->currentOrganization();

        return view('admin.billing-profile.show', [
            'organization' => $organization,
            'contact' => (array) (((array) ($organization->settings ?? []))['billing_contact'] ?? []),
            'plans' => array_keys((array) config('plans.tiers', [])),
            'requests' => TenantPlanRequest::query()->with('requester:id,name')->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'street' => ['nullable', 'string', 'max:200'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'vat_id' => ['nullable', 'string', 'max:20', static function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value !== '' && ! VatNumberHelper::isVatId($value)) {
                    $fail(__('platform_usage.billing_profile.invalid_vat'));
                }
            }],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($data['vat_id']) && $data['vat_id'] !== '') {
            $data['vat_id'] = VatNumberHelper::normalize($data['vat_id']);
        }
        $organization = $this->currentOrganization();
        $settings = (array) ($organization->settings ?? []);
        $settings['billing_contact'] = array_intersect_key($data, array_flip(self::CONTACT_FIELDS));
        $organization->forceFill(['settings' => $settings])->save();
        $organization->audit('organization.billingContactUpdated', ['fields' => array_keys(array_filter($data))]);

        return back()->with('success', __('platform_usage.billing_profile.flash.saved'));
    }

    public function requestPlan(Request $request): RedirectResponse {
        $this->authorizeBilling();
        $plans = array_keys((array) config('plans.tiers', []));
        $data = $request->validate([
            'requested_plan' => ['required', Rule::in($plans)],
            'requested_addons' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $open = TenantPlanRequest::query()->where('status', TenantPlanRequestStatus::Open->value)->exists();
        if ($open) {
            return back()->withErrors(['requested_plan' => __('platform_usage.plan_request.already_open')]);
        }
        $addons = array_values(array_filter(array_map('trim', explode(',', (string) ($data['requested_addons'] ?? '')))));
        $planRequest = TenantPlanRequest::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'requested_plan' => $data['requested_plan'],
            'requested_addons' => $addons,
            'note' => $data['note'] ?? null,
            'status' => TenantPlanRequestStatus::Open,
            'requester_user_id' => $this->authUser()->id,
        ]);
        $planRequest->audit('tenantPlanRequest.created', ['plan' => $data['requested_plan']]);

        return back()->with('success', __('platform_usage.plan_request.flash.sent'));
    }

    public function withdraw(TenantPlanRequest $planRequest): RedirectResponse {
        $this->authorizeBilling();
        abort_unless($planRequest->status->canTransitionTo(TenantPlanRequestStatus::Withdrawn), 409);
        $planRequest->forceFill(['status' => TenantPlanRequestStatus::Withdrawn, 'decided_at' => now()])->save();
        $planRequest->audit('tenantPlanRequest.withdrawn', []);

        return back()->with('success', __('platform_usage.plan_request.flash.withdrawn'));
    }

    private function authorizeBilling(): void {
        abort_unless(Gate::allows(Permission::OrganizationBilling->value), 403);
    }
}
