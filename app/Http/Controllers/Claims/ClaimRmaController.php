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
use App\Models\Article\ArticleVariant;
use App\Models\Claims\{ClaimCase, ClaimInspection, ClaimRmaReturn};
use App\Models\Inventory\{StockLot, StockSerial};
use App\Models\Shipping\Shipment;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Claims\ClaimRmaService;
use App\Services\Claims\Contracts\RmaReturnLabelIssuer;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * RMA-/Rückläuferprozess (Feature 072, MVP-250): Ankündigung mit
 * Rücksendenummer, Wareneingang in Quarantäne, Prüfung mit Seriennummern-
 * abgleich und Verwendungsentscheidung — alles claim.warehouse. Lager,
 * Variante, Menge, Seriennummer und Charge kommen aus Ankündigung oder
 * Wareneingang; ohne sie bleibt der Rückläufer reine Dokumentation (MVP-1576).
 */
class ClaimRmaController extends Controller {
    private const array STOCK_FIELDS = [
        'warehouse_id' => \App\Models\Inventory\Warehouse::class,
        'article_variant_id' => ArticleVariant::class,
        'stock_serial_id' => StockSerial::class,
        'stock_lot_id' => StockLot::class,
    ];

    public function __construct(private readonly ClaimRmaService $service) {}

    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('warehouse', $claim);

        $this->decodeStockFields($request, ['article_id' => \App\Models\Article\Article::class]);
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

        $rma = $this->service->announce($claim, $this->stockReference($data));

        return back()->with('status', __('Rücksendung :number angekündigt.', ['number' => $rma->rma_number]));
    }

    public function receive(Request $request, ClaimRmaReturn $rma): RedirectResponse {
        Gate::authorize('warehouse', $rma->claimCase);

        $this->decodeStockFields($request);
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('warehouses')],
            'article_variant_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('article_variants')],
            'stock_serial_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_serials')],
            'stock_lot_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_lots')],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
            'stock_state' => ['required', Rule::enum(StockState::class)->only(StockState::quarantine())],
            'condition_note' => ['nullable', 'string', 'max:4000'],
        ]);
        $data = $this->stockReference($data, $rma);

        // Mit Variante wird gebucht — dann müssen Lager und Menge feststehen, sonst bliebe die Buchung still aus.
        if (app(FeatureFlagResolver::class)->isEnabled('module.lager') && ($data['article_variant_id'] ?? $rma->article_variant_id) !== null) {
            $missing = array_filter(['warehouse_id', 'qty'], fn (string $field): bool => ($data[$field] ?? $rma->getAttribute($field)) === null);
            if ($missing !== []) {
                throw ValidationException::withMessages(array_map(
                    fn (string $field): string => (string) __('validation.required', ['attribute' => __('validation.attributes.' . $field)]),
                    array_combine($missing, $missing),
                ));
            }
        }

        $received = $this->service->receive($rma, $request->user() ?? abort(401), $data);

        return back()->with('status', $received->stockBooked
            ? __('claims.rma.flash.received_booked', ['state' => StockState::from($data['stock_state'])->label()])
            : __('claims.rma.flash.received_unbooked'));
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
            $decided = $this->service->decideDisposition($rma, $request->user() ?? abort(401), ClaimRmaDisposition::from($data['disposition']), $data['disposition_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['disposition' => ErrorText::for($e)]);
        }

        return back()->with('status', $decided->stockBooked ? __('claims.rma.flash.disposition_booked') : __('claims.rma.flash.disposition_unbooked'));
    }

    /** @param array<string, class-string<\Illuminate\Database\Eloquent\Model>> $extra */
    private function decodeStockFields(Request $request, array $extra = []): void {
        foreach (self::STOCK_FIELDS + $extra as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }
    }

    /**
     * Lagerbezug stimmig machen: Seriennummer und Charge gehören zur Variante,
     * die Variante bestimmt den Artikel, eine Seriennummer ist genau ein Stück.
     * Eine nur als Text genannte Seriennummer wird dem Lager zugeordnet, wenn
     * sie dort eindeutig ist.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stockReference(array $data, ?ClaimRmaReturn $rma = null): array {
        $variantId = $data['article_variant_id'] ?? $rma?->article_variant_id;

        $serial = ($data['stock_serial_id'] ?? null) !== null ? StockSerial::query()->whereKey((int) $data['stock_serial_id'])->first() : null;
        $serialNo = trim((string) ($data['serial_no'] ?? $rma->serial_no ?? ''));
        $qty = $data['qty'] ?? $rma?->qty;
        // Freitext nur zuordnen, wenn er zur Menge passt — eine Seriennummer ist ein Stück.
        if ($serial === null && $rma?->stock_serial_id === null && $serialNo !== '' && ($qty === null || (float) $qty === 1.0)) {
            $matches = StockSerial::query()->where('serial_no', $serialNo)
                ->when($variantId !== null, fn ($q) => $q->where('article_variant_id', $variantId))
                ->limit(2)->get();
            $serial = $matches->count() === 1 ? $matches->first() : null;
        }
        if ($serial !== null) {
            if ($variantId !== null && (int) $serial->article_variant_id !== (int) $variantId) {
                throw ValidationException::withMessages(['stock_serial_id' => __('claims.rma.error.serial_variant')]);
            }
            if ($qty !== null && (float) $qty !== 1.0) {
                throw ValidationException::withMessages(['qty' => __('claims.rma.error.serial_qty')]);
            }
            $variantId = (int) $serial->article_variant_id;
            $data['stock_serial_id'] = $serial->id;
            $data['serial_no'] = $serial->serial_no;
            $data['qty'] = '1';
        }

        if (($data['stock_lot_id'] ?? null) !== null) {
            $lot = StockLot::query()->whereKey((int) $data['stock_lot_id'])->firstOrFail();
            if ($variantId !== null && (int) $lot->article_variant_id !== (int) $variantId) {
                throw ValidationException::withMessages(['stock_lot_id' => __('claims.rma.error.lot_variant')]);
            }
            $variantId = (int) $lot->article_variant_id;
        }

        if ($variantId !== null) {
            $data['article_variant_id'] = $variantId;
            $data['article_id'] = ArticleVariant::query()->whereKey($variantId)->value('article_id');
        }

        return $data;
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
