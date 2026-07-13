<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\UserRole;
use App\Http\Controllers\Controller;
use App\Models\{BusinessService, Customer, FormTemplate, ProcedureTemplate, RequestItem, ServiceOffering, ServiceRequest, SlaContract, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Servicekatalog-Pflege (Feature 065, MVP-154): EINE Index-Seite mit drei
 * gruppierten Ebenen (BusinessService → ServiceOffering → RequestItem),
 * je Ebene Modal-CRUD (Muster ServiceQueueController). Die Genehmigungs-
 * kette wird als strukturierte Step-Liste gepflegt (user/role — kein
 * Freitext-JSON); Löschen nur ohne abhängige Inhalte (kein stilles
 * Kaskadieren trotz DB-Cascade).
 */
class ServiceCatalogController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', RequestItem::class);

        return view('helpdesk.catalog.index', [
            'services' => BusinessService::query()
                ->with([
                    'offerings' => fn($q) => $q->orderBy('name'),
                    'offerings.requestItems' => fn($q) => $q->orderBy('name'),
                    'offerings.requestItems.formTemplate:id,name',
                    'offerings.requestItems.slaContract:id,label',
                ])
                ->orderBy('name')
                ->get(),
            'canManage' => Gate::allows('create', RequestItem::class),
        ]);
    }

    // ── Ebene 1: Fachdienste ────────────────────────────────────────────

    public function createService(): View {
        Gate::authorize('create', RequestItem::class);

        return view('helpdesk.catalog._service_dialog', [
            'service' => new BusinessService(['active' => true]),
            'isEdit' => false,
        ]);
    }

    public function editService(BusinessService $service): View {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $service->organization_id);

        return view('helpdesk.catalog._service_dialog', [
            'service' => $service,
            'isEdit' => true,
        ]);
    }

    public function storeService(Request $request): RedirectResponse {
        Gate::authorize('create', RequestItem::class);

        BusinessService::query()->create([
            ...$this->validatedService($request),
            'organization_id' => $this->orgId(),
        ]);

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Fachdienst angelegt.'));
    }

    public function updateService(Request $request, BusinessService $service): RedirectResponse {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $service->organization_id);

        $service->update($this->validatedService($request, $service));

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Fachdienst gespeichert.'));
    }

    public function destroyService(BusinessService $service): RedirectResponse {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $service->organization_id);

        if ($service->offerings()->exists()) {
            return back()->with('error', __('Der Fachdienst enthält Angebote und kann nicht gelöscht werden.'));
        }

        $service->delete();

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Fachdienst gelöscht.'));
    }

    // ── Ebene 2: Serviceangebote ────────────────────────────────────────

    public function createOffering(Request $request): View {
        Gate::authorize('create', RequestItem::class);

        return view('helpdesk.catalog._offering_dialog', [
            'offering' => new ServiceOffering(['active' => true]),
            'isEdit' => false,
            'services' => BusinessService::query()->orderBy('name')->get(['id', 'name']),
            'preselectedService' => Sqid::decode(BusinessService::class, $request->query('service')),
        ]);
    }

    public function editOffering(ServiceOffering $offering): View {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $offering->organization_id);

        return view('helpdesk.catalog._offering_dialog', [
            'offering' => $offering,
            'isEdit' => true,
            'services' => BusinessService::query()->orderBy('name')->get(['id', 'name']),
            'preselectedService' => (int) $offering->business_service_id,
        ]);
    }

    public function storeOffering(Request $request): RedirectResponse {
        Gate::authorize('create', RequestItem::class);

        ServiceOffering::query()->create([
            ...$this->validatedOffering($request),
            'organization_id' => $this->orgId(),
        ]);

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Serviceangebot angelegt.'));
    }

    public function updateOffering(Request $request, ServiceOffering $offering): RedirectResponse {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $offering->organization_id);

        $offering->update($this->validatedOffering($request));

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Serviceangebot gespeichert.'));
    }

    public function destroyOffering(ServiceOffering $offering): RedirectResponse {
        Gate::authorize('create', RequestItem::class);
        $this->assertSameOrg((int) $offering->organization_id);

        if ($offering->requestItems()->exists()) {
            return back()->with('error', __('Das Angebot enthält Katalogeinträge und kann nicht gelöscht werden.'));
        }

        $offering->delete();

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Serviceangebot gelöscht.'));
    }

    // ── Ebene 3: Katalogeinträge (Request-Items) ────────────────────────

    public function createItem(Request $request): View {
        Gate::authorize('create', RequestItem::class);

        return view('helpdesk.catalog._item_dialog', [
            'item' => new RequestItem(['active' => true, 'fulfillment' => 'task']),
            'isEdit' => false,
            'preselectedOffering' => Sqid::decode(ServiceOffering::class, $request->query('offering')),
            ...$this->itemDialogOptions(),
        ]);
    }

    public function editItem(RequestItem $item): View {
        Gate::authorize('update', $item);

        return view('helpdesk.catalog._item_dialog', [
            'item' => $item,
            'isEdit' => true,
            'preselectedOffering' => (int) $item->service_offering_id,
            ...$this->itemDialogOptions(),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse {
        Gate::authorize('create', RequestItem::class);

        RequestItem::query()->create([
            ...$this->validatedItem($request),
            'organization_id' => $this->orgId(),
        ]);

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Katalogeintrag angelegt.'));
    }

    public function updateItem(Request $request, RequestItem $item): RedirectResponse {
        Gate::authorize('update', $item);

        // Katalog ist versioniert (MVP-154): jede Änderung erhöht die Version;
        // laufende Requests bleiben über ihre Snapshots unberührt.
        $item->update([
            ...$this->validatedItem($request),
            'version' => (int) $item->version + 1,
        ]);

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Katalogeintrag gespeichert.'));
    }

    public function destroyItem(RequestItem $item): RedirectResponse {
        Gate::authorize('delete', $item);

        if (ServiceRequest::query()->where('request_item_id', $item->id)->exists()) {
            return back()->with('error', __('Zum Katalogeintrag existieren Requests — bitte stattdessen deaktivieren.'));
        }

        $item->delete();

        return redirect()->route('servicedesk.catalog.index')
            ->with('success', __('Katalogeintrag gelöscht.'));
    }

    // ── Validierung / Helfer ────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validatedService(Request $request, ?BusinessService $service = null): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150',
                Rule::unique('business_services', 'name')
                    ->where('organization_id', $this->orgId())
                    ->ignore($service?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }

    /** @return array<string, mixed> */
    private function validatedOffering(Request $request): array {
        $data = $request->validate([
            'business_service_id' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ]);

        $serviceId = Sqid::decode(BusinessService::class, $data['business_service_id']);
        validator(['business_service_id' => $serviceId], [
            'business_service_id' => ['required', new ExistsInCurrentOrganization('business_services')],
        ])->validate();

        return [
            'business_service_id' => (int) $serviceId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedItem(Request $request): array {
        $internalRoles = array_values(array_filter(
            array_map(fn(UserRole $r) => $r->value, UserRole::cases()),
            fn(string $role): bool => $role !== UserRole::Kunde->value,
        ));

        $data = $request->validate([
            'service_offering_id' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'form_template_id' => ['nullable', 'string'],
            'sla_contract_id' => ['nullable', 'string'],
            'fulfillment' => ['required', 'in:task,project,diary,procedure'],
            'procedure_template_id' => ['nullable', 'string', 'required_if:fulfillment,procedure'],
            'approval_steps' => ['nullable', 'array', 'max:10'],
            'approval_steps.*.type' => ['required', 'in:user,role'],
            'approval_steps.*.user' => ['nullable', 'string'],
            'approval_steps.*.role' => ['nullable', 'string', Rule::in($internalRoles)],
            'visibility_roles' => ['nullable', 'array'],
            'visibility_roles.*' => ['string', Rule::in($internalRoles)],
            'visibility_portal' => ['nullable', 'boolean'],
            'visibility_customer_ids' => ['nullable', 'array'],
            'visibility_customer_ids.*' => ['string'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Sqid-Referenzen je Zielklasse dekodieren und org-gescopt prüfen.
        $offeringId = Sqid::decode(ServiceOffering::class, $data['service_offering_id']);
        $formTemplateId = Sqid::decode(FormTemplate::class, $data['form_template_id'] ?? null);
        $slaContractId = Sqid::decode(SlaContract::class, $data['sla_contract_id'] ?? null);
        $procedureTemplateId = Sqid::decode(ProcedureTemplate::class, $data['procedure_template_id'] ?? null);

        validator([
            'service_offering_id' => $offeringId,
            'form_template_id' => $formTemplateId,
            'sla_contract_id' => $slaContractId,
            'procedure_template_id' => $procedureTemplateId,
        ], [
            'service_offering_id' => ['required', new ExistsInCurrentOrganization('service_offerings')],
            'form_template_id' => ['nullable', new ExistsInCurrentOrganization('form_templates')],
            'sla_contract_id' => ['nullable', new ExistsInCurrentOrganization('sla_contracts')],
            'procedure_template_id' => [
                $data['fulfillment'] === 'procedure' ? 'required' : 'nullable',
                new ExistsInCurrentOrganization('procedure_templates'),
            ],
        ])->validate();

        $customerIds = [];
        foreach ((array) ($data['visibility_customer_ids'] ?? []) as $sqid) {
            $customerId = Sqid::decode(Customer::class, (string) $sqid);
            validator(['customer_id' => $customerId], [
                'customer_id' => ['required', new ExistsInCurrentOrganization('customers')],
            ])->validate();
            $customerIds[] = (int) $customerId;
        }

        $visibility = [
            'roles' => array_values((array) ($data['visibility_roles'] ?? [])),
            'portal' => (bool) ($data['visibility_portal'] ?? false),
            'customer_ids' => $customerIds,
        ];
        if ($visibility['roles'] === [] && ! $visibility['portal'] && $visibility['customer_ids'] === []) {
            $visibility = null;
        }

        return [
            'service_offering_id' => (int) $offeringId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'form_template_id' => $formTemplateId,
            'sla_contract_id' => $slaContractId,
            'fulfillment' => $data['fulfillment'],
            'fulfillment_config' => $data['fulfillment'] === 'procedure'
                ? ['procedure_template_id' => (int) $procedureTemplateId]
                : null,
            'approval_chain' => $this->buildApprovalChain((array) ($data['approval_steps'] ?? [])),
            'visibility' => $visibility,
            'active' => (bool) ($data['active'] ?? false),
        ];
    }

    /**
     * Strukturierte Step-Liste → Genehmigungskette in der von
     * {@see \App\Services\ServiceTicket\ApprovalService::createChain}
     * erwarteten Form [{approver: {type, value}}].
     *
     * @param array<int, array<string, mixed>> $steps
     * @return array<int, array<string, mixed>>|null
     */
    private function buildApprovalChain(array $steps): ?array {
        $chain = [];
        foreach (array_values($steps) as $index => $step) {
            $type = (string) ($step['type'] ?? '');
            if ($type === 'user') {
                $userId = Sqid::decode(User::class, (string) ($step['user'] ?? ''));
                $exists = $userId !== null && User::query()
                    ->whereKey($userId)
                    ->where('organization_id', $this->orgId())
                    ->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([
                        "approval_steps.{$index}.user" => (string) __('Bitte einen Benutzer der eigenen Organisation wählen.'),
                    ]);
                }
                $chain[] = ['approver' => ['type' => 'user', 'value' => (int) $userId]];
            } else {
                $role = (string) ($step['role'] ?? '');
                if ($role === '') {
                    throw ValidationException::withMessages([
                        "approval_steps.{$index}.role" => (string) __('Bitte eine Rolle wählen.'),
                    ]);
                }
                $chain[] = ['approver' => ['type' => 'role', 'value' => $role]];
            }
        }

        return $chain === [] ? null : $chain;
    }

    /** @return array<string, mixed> */
    private function itemDialogOptions(): array {
        return [
            'offerings' => ServiceOffering::query()->with('businessService:id,name')->orderBy('name')->get(['id', 'name', 'business_service_id']),
            'formTemplates' => FormTemplate::query()->active()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->orderBy('label')->get(['id', 'label']),
            'procedureTemplates' => ProcedureTemplate::query()->orderBy('name')->get(['id', 'name']),
            'orgUsers' => User::query()
                ->where('organization_id', $this->orgId())
                ->orderBy('name')
                ->get(['id', 'name']),
            'roles' => array_values(array_filter(UserRole::cases(), fn(UserRole $r) => $r !== UserRole::Kunde)),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function orgId(): int {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->organization_id;
    }

    private function assertSameOrg(int $organizationId): void {
        abort_unless($organizationId === $this->orgId(), 404);
    }
}
