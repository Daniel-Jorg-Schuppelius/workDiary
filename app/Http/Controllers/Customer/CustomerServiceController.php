<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerDeadline, CustomerService};
use App\Services\Classification\ClassificationResolver;
use App\Services\Customer\Deadlines\CustomerServiceManager;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Leistungsumfang eines Kunden im Cockpit-Dialog (Feature 197, MVP-1630):
 * Leistungen (Eintragsart + Produktgruppe, Pflicht oder Wahl, Periodenart,
 * Gültigkeit) anlegen und beenden. Gelesen von den Fristen-Regelsätzen und vom
 * Leistungsplan (Feature 199).
 */
class CustomerServiceController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly CustomerServiceManager $services,
        private readonly ClassificationResolver $classifications,
    ) {}

    public function index(Customer $customer): View {
        $this->assertSameOrganization($customer);
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();

        return view('customer-deadlines._services_dialog', [
            'customer' => $customer,
            'services' => $this->services->forCustomer($customer),
            'entryTypes' => EntryType::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('sort')->orderBy('label')->get(['id', 'slug', 'label']),
            'productGroups' => $this->classifications->list((int) $organization->id, ClassificationDomain::ProductGroup),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse {
        $this->assertSameOrganization($customer);
        Gate::authorize('manage', CustomerDeadline::class);
        $organization = $this->currentOrganization();
        $request->merge(['entry_type_id' => is_scalar($value = $request->input('entry_type_id')) && $value !== '' ? Sqid::decodeOrNumeric(EntryType::class, (string) $value) : null]);
        $data = $request->validate([
            'entry_type_id' => ['required', 'integer', Rule::exists('entry_types', 'id')->where('organization_id', $organization->id)],
            'product_group_code' => ['nullable', 'string', 'max:64'],
            'is_mandatory' => ['nullable', 'boolean'],
            'period_kind' => ['required', Rule::enum(PeriodKind::class)],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $productGroup = StringHelper::trimToNull($data['product_group_code'] ?? null);
        if ($productGroup !== null && $this->classifications->resolveCode((int) $organization->id, ClassificationDomain::ProductGroup, $productGroup) === null) {
            return back()->withErrors(['product_group_code' => __('validation.exists', ['attribute' => __('customer_deadline.service.product_group')])])->withInput();
        }

        $this->services->add(
            $customer,
            EntryType::query()->where('organization_id', $organization->id)->findOrFail((int) $data['entry_type_id']),
            $productGroup,
            $request->boolean('is_mandatory'),
            PeriodKind::from((string) $data['period_kind']),
            CarbonImmutable::parse((string) $data['valid_from'])->startOfDay(),
            isset($data['valid_until']) ? CarbonImmutable::parse((string) $data['valid_until'])->startOfDay() : null,
            StringHelper::trimToNull($data['note'] ?? null),
            $this->authUser(),
        );

        return back()->with('success', __('customer_deadline.flash.service_added'));
    }

    public function end(Request $request, Customer $customer, CustomerService $customerService): RedirectResponse {
        $this->assertSameOrganization($customer);
        $this->assertSameOrganization($customerService);
        abort_unless((int) $customerService->customer_id === (int) $customer->id, 404);
        Gate::authorize('manage', CustomerDeadline::class);
        $data = $request->validate(['end_on' => ['required', 'date_format:Y-m-d']]);

        $this->services->end($customerService, CarbonImmutable::parse((string) $data['end_on'])->startOfDay());

        return back()->with('success', __('customer_deadline.flash.service_ended'));
    }
}
