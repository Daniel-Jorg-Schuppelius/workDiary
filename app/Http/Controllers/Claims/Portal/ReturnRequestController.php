<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReturnRequestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims\Portal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Inventory\StockDelivery;
use App\Models\Platform\User;
use App\Models\Shipping\Shipment;
use App\Services\Attachments\FileAttacher;
use App\Services\Claims\PortalReturnService;
use App\Services\CustomerPortal\PortalVisibility;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Retourenanmeldung im Kundenportal (MVP-935). */
class ReturnRequestController extends Controller {
    public function __construct(private readonly PortalReturnService $returns) {}

    public function create(): View {
        $customer = $this->customer();
        $deliveries = $this->returns->deliveries($customer);

        return view('customer.claims.return', [
            'deliveries' => $deliveries,
            'serials' => $this->returns->serialsByDelivery($deliveries),
            'assets' => $this->returns->assets($customer),
            // Eigene Rücksendungen samt Label — auch ohne Freigabe „Reklamationen“.
            'rmas' => ClaimRmaReturn::query()
                ->whereHas('claimCase', fn (Builder $q) => $q->where('customer_id', $customer->id))
                ->with(['claimCase:id,number,title', 'returnShipments'])
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function store(Request $request, PortalVisibility $visibility): RedirectResponse {
        $customer = $this->customer();
        foreach (['delivery_id' => StockDelivery::class, 'asset_id' => Asset::class] as $field => $class) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($class, $request->string($field)->toString())]);
            }
        }
        $data = $request->validate([
            'delivery_id' => ['nullable', 'integer', 'required_without:asset_id'],
            'asset_id' => ['nullable', 'integer'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'numeric', 'min:0.001', 'max:1000000'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => FileAttacher::rule(),
        ]);
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $result = $this->returns->submit($user, $customer, $data, array_values((array) $request->file('photos', [])));

        $flash = __('claims.portal_return.flash.submitted', ['number' => $result['claim']->number, 'rma' => $result['rma']->rma_number]);
        if ($visibility->allows($customer, PortalCapability::Claims)) {
            return redirect()->route('customer.claims.show', $result['claim'])->with('status', $flash);
        }

        return redirect()->route('customer.returns.create')->with('status', $flash);
    }

    public function label(ClaimRmaReturn $rma, Shipment $shipment): BinaryFileResponse {
        $customer = $this->customer();
        abort_unless((int) $rma->claimCase?->customer_id === $customer->id && $shipment->claim_rma_return_id === $rma->id, 404);
        $label = $shipment->labelAttachment() ?? abort(404);
        $disk = Storage::disk($label->disk);
        abort_unless($disk->exists($label->path), 404);

        return response()->download($disk->path($label->path), 'retoure-' . $rma->rma_number . '.' . pathinfo($label->original_name, PATHINFO_EXTENSION));
    }

    private function customer(): Customer {
        $user = Auth::guard('customer')->user();

        return $user instanceof User && $user->customer instanceof Customer ? $user->customer : abort(403);
    }
}
