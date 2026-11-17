<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCellController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\ServicePlan;

use App\Enums\Diary\{LocationMode, Mode};
use App\Enums\Period\PeriodKind;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Delegation\ActsForResolver;
use App\Services\ServicePlan\Dto\{ServiceKey, ServicePlanCell, ServicePlanQuery};
use App\Services\ServicePlan\{ServicePlanBuilder, ServicePlanCatalog, ServicePlanCells};
use App\Support\Period\PeriodKey;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Eine Zelle des Leistungsplans (Feature 199, MVP-1638): Dialog mit Stand,
 * Auftrag (öffnen oder vorbelegt anlegen), Fälligkeit, „vorangefordert am“
 * aus der Kundenfrist, „Unterlagen da am“, Bemerkung und „entfällt“.
 */
class ServicePlanCellController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ServicePlanBuilder $builder,
        private readonly ServicePlanCatalog $catalog,
        private readonly ServicePlanCells $cells,
        private readonly ActsForResolver $actsFor,
    ) {}

    public function edit(Request $request): View {
        [$customer, $service, $period, $cell] = $this->resolve($request);
        $order = $cell->orderId !== null ? DiaryEntry::query()->with(['assignedUser:id,name', 'entryType:id,label'])->find($cell->orderId) : null;
        $deadline = $cell->deadlineId !== null ? CustomerDeadline::query()->find($cell->deadlineId) : null;
        $waiver = $this->cells->waiver($customer, $service, $period);

        return view('service-plan._cell_dialog', [
            'customer' => $customer,
            'service' => $service,
            'serviceLabel' => $this->catalog->label((int) $customer->organization_id, $service),
            'period' => $period,
            'cell' => $cell,
            'order' => $order,
            'deadline' => $deadline,
            'waiver' => $waiver?->load('creator:id,name'),
            'responsible' => $cell->responsibleId !== null ? User::query()->find($cell->responsibleId, ['id', 'name']) : null,
            'canManage' => Gate::allows('manage', CustomerServiceWaiver::class),
            'canAnnotate' => Gate::allows('annotate', [CustomerServiceWaiver::class, $cell->responsibleId]),
            'canCreateOrder' => $order === null && $this->authUser()->can(Permission::DiaryCreate->value),
            'params' => $this->params($customer, $service, $period),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        [$customer, $service, $period, $cell] = $this->resolve($request);
        $actor = $this->authUser();
        $action = $request->string('action')->toString();

        if ($action === 'note') {
            Gate::authorize('annotate', [CustomerServiceWaiver::class, $cell->responsibleId]);
            $data = $request->validate([
                'documents_received_on' => ['nullable', 'date_format:Y-m-d'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]);
            $received = StringHelper::trimToNull($data['documents_received_on'] ?? null);
            $this->cells->saveNote($customer, $service, $period, $received !== null ? CarbonImmutable::parse($received)->startOfDay() : null, $data['note'] ?? null, $actor);

            return back()->with('success', __('service_plan.flash.note_saved'));
        }

        Gate::authorize('manage', CustomerServiceWaiver::class);
        if ($action === 'waive') {
            $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
            $this->cells->waive($customer, $service, $period, (string) $data['reason'], $actor);

            return back()->with('success', __('service_plan.flash.waived'));
        }
        abort_unless($action === 'unwaive', 422);
        $this->cells->unwaive($customer, $service, $period);

        return back()->with('success', __('service_plan.flash.unwaived'));
    }

    /**
     * Auftrag der Zelle vorbelegt anlegen: öffnet den Auftragsdialog mit Kunde,
     * Eintragsart, Produktgruppe, Periode, Zuständigkeit und Fälligkeit (aus der
     * Kundenfrist, unter dem Jahr sonst Periodenende). Gespeichert wird erst im
     * Dialog — Pflichtfelder und Klassifikationen prüft der Auftrag selbst.
     */
    public function order(Request $request): RedirectResponse {
        [$customer, $service, $period, $cell] = $this->resolve($request);
        abort_unless($this->authUser()->can(Permission::DiaryCreate->value), 403);
        if ($cell->orderId !== null) {
            $order = DiaryEntry::query()->find($cell->orderId);
            abort_unless($order instanceof DiaryEntry && Gate::allows('view', $order), 403);

            return redirect()->route('diary.show', $order);
        }

        $label = $this->catalog->label((int) $customer->organization_id, $service);
        $input = [
            'period_key' => $period->key(),
            'mode' => $cell->due !== null ? Mode::Deadline->value : Mode::Backlog->value,
            'due_date' => $cell->due?->toDateString(),
            'location_mode' => LocationMode::Remote->value,
        ];
        if ($service->productGroup !== null) {
            $input['product_group'] = $service->productGroup;
        }
        if ($cell->responsibleId !== null && $this->authUser()->can(Permission::DiaryCreateForOthers->value)) {
            $input['assigned_user_id'] = Sqid::encode(User::class, $cell->responsibleId);
        }

        return redirect()->route('diary.create', array_filter([
            'customer_id' => $customer->sqid,
            'entry_type_id' => $service->entryTypeId,
            'title' => $label . ' ' . $period->label(),
            'dialog' => $request->query('dialog'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''))->withInput(array_filter($input, static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Kunde, Leistung, Periode und berechnete Zelle aus der Anfrage; 404 für
     * Fremdes oder Unbekanntes, 403 für Zellen, die der Plan dem Betrachter verdeckt.
     *
     * @return array{0: Customer, 1: ServiceKey, 2: PeriodKey, 3: ServicePlanCell}
     */
    private function resolve(Request $request): array {
        Gate::authorize('viewAny', CustomerServiceWaiver::class);
        $organization = $this->currentOrganization();
        $customerId = Sqid::decode(Customer::class, StringHelper::trimToNull($request->input('customer')));
        $service = ServiceKey::fromParam($request->input('service'));
        $period = PeriodKey::tryParse(is_string($value = $request->input('period')) ? $value : null);
        abort_if($customerId === null || $service === null || $period === null, 404);
        $customer = Customer::query()->find($customerId);
        abort_unless($customer instanceof Customer, 404);
        $this->assertSameOrganization($customer);

        $viewer = $this->authUser();
        $result = $this->builder->build($organization, new ServicePlanQuery(
            view: $period->kind === PeriodKind::Year ? ServicePlanQuery::VIEW_YEAR : ServicePlanQuery::VIEW_PERIODS,
            year: $period->year,
            today: CarbonImmutable::today(),
            services: [$service],
            customerIds: [(int) $customer->id],
        ));
        $cell = null;
        foreach ($result->rows as $row) {
            $candidate = $row->cells[$period->kind === PeriodKind::Year ? $service->key() : $period->key()] ?? null;
            if ($candidate !== null && $candidate->period->equals($period) && $candidate->service->equals($service)) {
                $cell = $candidate;
            }
        }
        abort_unless($cell !== null && $cell->inScope(), 404);
        // Ohne Leserecht des Plans nur Zellen, für die die Person zuständig ist oder in Vertretung handelt.
        abort_unless(Gate::allows('viewAll', CustomerServiceWaiver::class) || $this->actsFor->actsFor($viewer, $cell->responsibleId), 403);

        return [$customer, $service, $period, $cell];
    }

    /** @return array{customer: string, service: string, period: string} */
    private function params(Customer $customer, ServiceKey $service, PeriodKey $period): array {
        return ['customer' => (string) $customer->sqid, 'service' => $service->param(), 'period' => $period->key()];
    }
}
