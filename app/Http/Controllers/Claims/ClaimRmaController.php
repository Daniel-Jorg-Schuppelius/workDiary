<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Enums\Claims\ClaimRmaDisposition;
use App\Enums\Inventory\StockState;
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimCase, ClaimInspection, ClaimRmaReturn};
use App\Models\Shipping\Shipment;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Claims\ClaimRmaService;
use App\Services\Claims\Contracts\RmaReturnLabelIssuer;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * RMA-/Rückläuferprozess (Feature 072, MVP-250): Ankündigung mit
 * Rücksendenummer, Wareneingang in Quarantäne, Prüfung mit Seriennummern-
 * abgleich und Verwendungsentscheidung — alles claim.warehouse.
 */
class ClaimRmaController extends Controller {
    public function __construct(private readonly ClaimRmaService $service) {}

    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('warehouse', $claim);

        $fieldModels = [
            'warehouse_id' => \App\Models\Inventory\Warehouse::class,
            'article_id' => \App\Models\Article\Article::class,
            'article_variant_id' => \App\Models\Article\ArticleVariant::class,
            'stock_serial_id' => \App\Models\Inventory\StockSerial::class,
            'stock_lot_id' => \App\Models\Inventory\StockLot::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }
        $data = $request->validate([
            'expected_at' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('warehouses')],
            'article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('articles')],
            'article_variant_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('article_variants')],
            'stock_serial_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_serials')],
            'stock_lot_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_lots')],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
        ]);

        $rma = $this->service->announce($claim, $data);

        return back()->with('status', __('Rücksendung :number angekündigt.', ['number' => $rma->rma_number]));
    }

    public function receive(Request $request, ClaimRmaReturn $rma): RedirectResponse {
        Gate::authorize('warehouse', $rma->claimCase);

        if ($request->filled('warehouse_id')) {
            $request->merge(['warehouse_id' => Sqid::decodeOrNumeric(\App\Models\Inventory\Warehouse::class, $request->input('warehouse_id'))]);
        }
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('warehouses')],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
            'stock_state' => ['required', Rule::enum(StockState::class)->only(StockState::quarantine())],
            'condition_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->service->receive($rma, $request->user() ?? abort(401), $data);

        return back()->with('status', __('Wareneingang in Quarantäne (:state) gebucht.', ['state' => $data['stock_state']]));
    }

    public function inspect(Request $request, ClaimRmaReturn $rma): RedirectResponse {
        Gate::authorize('warehouse', $rma->claimCase);

        $data = $request->validate([
            'result' => ['required', Rule::in(ClaimInspection::RESULTS)],
            'findings' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->service->inspect($rma, $request->user() ?? abort(401), $data);

        return back()->with('status', __('Prüfergebnis dokumentiert.'));
    }

    public function disposition(Request $request, ClaimRmaReturn $rma): RedirectResponse {
        Gate::authorize('warehouse', $rma->claimCase);

        $data = $request->validate([
            'disposition' => ['required', Rule::enum(ClaimRmaDisposition::class)],
            'disposition_note' => ['nullable', 'string', 'max:4000'],
        ]);

        try {
            $this->service->decideDisposition($rma, $request->user() ?? abort(401), ClaimRmaDisposition::from($data['disposition']), $data['disposition_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['disposition' => ErrorText::for($e)]);
        }

        return back()->with('status', __('Verwendungsentscheidung gebucht.'));
    }

    /** Retourenlabel (MVP-917): Versand über die Carrier-Anbindung, Absender der Kunde. */
    public function returnLabel(Request $request, ClaimRmaReturn $rma, RmaReturnLabelIssuer $issuer): RedirectResponse {
        Gate::authorize('warehouse', $rma->claimCase);

        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:24'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        try {
            $shipment = $issuer->issue($rma, $request->user() ?? abort(401), (string) $data['carrier'], (int) $data['weight_grams']);
        } catch (\RuntimeException $e) {
            return back()->with('error', __('shipping.flash.label_failed', ['reason' => ErrorText::for($e)]));
        }

        return back()->with('status', __('claims.return_label.created', ['tracking' => (string) $shipment->tracking_number]));
    }

    public function downloadReturnLabel(ClaimRmaReturn $rma, Shipment $shipment): BinaryFileResponse {
        Gate::authorize('warehouse', $rma->claimCase);
        abort_unless($shipment->claim_rma_return_id === $rma->id, 404);
        $label = $shipment->labelAttachment() ?? abort(404);
        $disk = Storage::disk($label->disk);
        abort_unless($disk->exists($label->path), 404);

        return response()->download($disk->path($label->path), 'retoure-' . $rma->rma_number . '.' . pathinfo($label->original_name, PATHINFO_EXTENSION));
    }
}
