<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicInspectionOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\AssetCompliance\{AssetInspectionOrderStatus, AssetInspectionResult};
use App\Http\Controllers\Controller;
use App\Models\AssetCompliance\AssetInspectionOrder;
use App\Models\Platform\Organization;
use App\Services\AssetCompliance\InspectionOrderService;
use App\Services\Attachments\FileAttacher;
use App\Support\OrganizationContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Link des Prüfdienstleisters (MVP-938): Angebot abgeben, Ergebnisse melden. */
class PublicInspectionOrderController extends Controller {
    public function __construct(private readonly InspectionOrderService $orders) {}

    public function show(string $token): View {
        $order = $this->order($token);
        $organization = $this->organization($order);

        return OrganizationContext::run($organization, fn (): View => view('public.inspection-order', [
            'order' => $order->load(['items.asset']),
            'orgName' => $organization->name,
            'token' => $token,
        ]));
    }

    public function offer(Request $request, string $token): RedirectResponse {
        $order = $this->order($token);
        $data = $request->validate([
            'offer_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'offer_planned_on' => ['required', 'date', 'after_or_equal:today'],
            'offer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        OrganizationContext::run($this->organization($order), fn () => $this->orders->offer($order, $data));

        return redirect()->route('inspection-order.public', $token)->with('status', __('inspection_order.flash.offered'));
    }

    public function report(Request $request, string $token): RedirectResponse {
        $order = $this->order($token);
        abort_unless($order->status === AssetInspectionOrderStatus::Accepted, 404);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.result' => ['nullable', Rule::enum(AssetInspectionResult::class)],
            'items.*.performed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.valid_until' => ['nullable', 'date'],
            'items.*.certificate_no' => ['nullable', 'string', 'max:120'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'certificates' => ['nullable', 'array'],
            'certificates.*' => FileAttacher::rule(),
        ]);
        OrganizationContext::run($this->organization($order), fn () => $this->orders->report($order, (array) $data['items'], (array) $request->file('certificates', [])));

        return redirect()->route('inspection-order.public', $token)->with('status', __('inspection_order.flash.reported'));
    }

    private function order(string $token): AssetInspectionOrder {
        return $this->orders->resolve($token) ?? abort(404);
    }

    private function organization(AssetInspectionOrder $order): Organization {
        return Organization::query()->withoutGlobalScopes()->findOrFail($order->organization_id);
    }
}
