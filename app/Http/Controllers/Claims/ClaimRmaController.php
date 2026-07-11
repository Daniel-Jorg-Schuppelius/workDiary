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
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimCase, ClaimInspection, ClaimRmaReturn};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Claims\ClaimRmaService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            'warehouse_id' => \App\Models\Warehouse::class,
            'article_id' => \App\Models\Article::class,
            'article_variant_id' => \App\Models\ArticleVariant::class,
            'stock_serial_id' => \App\Models\StockSerial::class,
            'stock_lot_id' => \App\Models\StockLot::class,
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
            $request->merge(['warehouse_id' => Sqid::decodeOrNumeric(\App\Models\Warehouse::class, $request->input('warehouse_id'))]);
        }
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('warehouses')],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
            'stock_state' => ['required', Rule::in(ClaimRmaService::QUARANTINE_STATES)],
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
            return back()->withErrors(['disposition' => $e->getMessage()]);
        }

        return back()->with('status', __('Verwendungsentscheidung gebucht.'));
    }
}
