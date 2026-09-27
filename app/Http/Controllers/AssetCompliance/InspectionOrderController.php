<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionScheduleStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionOrder, AssetInspectionSchedule};
use App\Models\Supplier\Supplier;
use App\Services\AssetCompliance\InspectionOrderService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Prüfaufträge an Dienstleister (MVP-938). */
class InspectionOrderController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly InspectionOrderService $orders) {}

    public function index(): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        return view('asset-compliance.orders.index', [
            'orders' => AssetInspectionOrder::query()->with('supplier')->withCount('items')->orderByDesc('id')->paginate(25),
            'canManage' => Gate::allows('create', AssetComplianceProfile::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', AssetComplianceProfile::class);

        return view('asset-compliance.orders._form_dialog', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'email']),
            'schedules' => AssetInspectionSchedule::query()
                ->whereIn('status', [AssetInspectionScheduleStatus::Planned->value, AssetInspectionScheduleStatus::Announced->value])
                ->with('asset:id,name,asset_no')
                ->orderBy('due_on')
                ->limit(200)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        $request->merge([
            'supplier_id' => Sqid::decodeOrNumeric(Supplier::class, $request->string('supplier_id')->toString()),
            'schedule_ids' => array_values(array_filter(array_map(
                static fn (mixed $v): ?int => Sqid::decodeOrNumeric(AssetInspectionSchedule::class, (string) $v),
                (array) $request->input('schedule_ids', []),
            ))),
        ]);
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
        ]);
        $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
        $order = $this->orders->create($this->currentOrganization(), $supplier, $data['title'], $data['recipient_email'], array_values(array_map('intval', $data['schedule_ids'])), $this->authUser());

        return redirect()->route('asset-compliance.orders.show', $order)->with('success', __('inspection_order.flash.sent', ['email' => $data['recipient_email']]));
    }

    public function show(AssetInspectionOrder $order): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        return view('asset-compliance.orders.show', [
            'order' => $order->load(['supplier', 'items.asset', 'items.attachments']),
            'canManage' => Gate::allows('create', AssetComplianceProfile::class),
            'canInspect' => Gate::allows('inspect', AssetComplianceProfile::class),
        ]);
    }

    public function decide(Request $request, AssetInspectionOrder $order): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        $data = $request->validate(['decision' => ['required', 'in:accept,reject']]);
        $this->orders->decide($order, $data['decision'] === 'accept', $this->authUser());

        return back()->with('success', __('inspection_order.flash.decided'));
    }

    public function takeOver(AssetInspectionOrder $order): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);
        try {
            $count = $this->orders->takeOver($order, $this->authUser());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('inspection_order.flash.taken_over', ['count' => $count]));
    }

    public function cancel(AssetInspectionOrder $order): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        $this->orders->cancel($order, $this->authUser());

        return back()->with('success', __('inspection_order.flash.cancelled'));
    }
}
