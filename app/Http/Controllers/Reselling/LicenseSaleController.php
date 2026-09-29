<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseSaleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\LicenseUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reselling\Concerns\ProvidesHolderPicker;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseProduct, ResaleLicenseUnit};
use App\Services\Reselling\License\{LicenseStockException, LicenseStockService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Schlüsselpflege, Verkauf und Korrekturen einer Einzellizenz (MVP-1024).
 * Klartext der Schlüssel gibt es nur über {@see keysShow()} mit eigenem Recht
 * und `no-store` — nie in Listen, Formularen oder data-Attributen.
 */
class LicenseSaleController extends Controller {
    use ProvidesHolderPicker;

    public function __construct(private readonly LicenseStockService $stock) {}

    public function keysEdit(ResaleLicenseUnit $unit): View {
        $unit->load(['batch.product', 'keys', 'activeAssignment']);

        return view('finance.resale.licenses._keys_dialog', [
            'unit' => $unit,
            'present' => $unit->keys->pluck('role')->all(),
        ]);
    }

    public function keysUpdate(Request $request, ResaleLicenseUnit $unit): RedirectResponse {
        $data = $request->validate([
            'license_keys' => ['nullable', 'array', 'max:' . LicenseStockService::MAX_KEY_ROLES],
            'license_keys.*' => ['nullable', 'string', 'max:' . LicenseStockService::MAX_KEY_LENGTH],
            'remove' => ['nullable', 'array', 'max:' . LicenseStockService::MAX_KEY_ROLES],
            'remove.*' => ['string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->attempt(fn (): int => $this->stock->saveKeys(
            $unit,
            array_map(static fn (mixed $value): ?string => is_string($value) ? $value : null, (array) ($data['license_keys'] ?? [])),
            array_values((array) ($data['remove'] ?? [])),
            $data['reason'] ?? null,
            $this->actor(),
        ));

        return redirect()->route('finance.resale.licenses.batches.show', $unit->batch)
            ->with('success', trans_choice('resale.license.flash.keys_saved', $count, ['count' => $count, 'license' => $unit->label()]));
    }

    /** Geschützte Anzeige des Schlüsselsatzes; der Zugriff wird ohne Werte protokolliert. */
    public function keysShow(ResaleLicenseUnit $unit): Response {
        $unit->load(['batch.product', 'activeAssignment.customer', 'activeAssignment.foreignCustomer']);

        return response()
            ->view('finance.resale.licenses._keys_show_dialog', ['unit' => $unit, 'keys' => $this->stock->revealKeys($unit)])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function sellCreate(Request $request): View {
        $unit = ResaleLicenseUnit::query()->find(Sqid::decodeOrNumeric(ResaleLicenseUnit::class, $request->string('unit')->toString()) ?? 0);
        $product = $unit?->batch->product
            ?? ResaleLicenseProduct::query()->find(Sqid::decodeOrNumeric(ResaleLicenseProduct::class, $request->string('product')->toString()) ?? 0);
        $available = ResaleLicenseUnit::query()
            ->available()
            ->with('batch.product')
            ->join('resale_license_batches as sb', 'sb.id', '=', 'resale_license_units.batch_id')
            ->join('resale_license_products as sp', 'sp.id', '=', 'sb.product_id')
            ->when($product?->id, static fn ($q, int $productId) => $q->where('sb.product_id', $productId))
            ->orderBy('sp.name')->orderBy('sb.purchased_on')->orderBy('sb.id')->orderBy('resale_license_units.position')
            ->select('resale_license_units.*')
            ->limit(300)
            ->get();
        $suggested = match (true) {
            $unit !== null && $unit->status() === LicenseUnitStatus::Available => $unit,
            $product !== null => $this->stock->nextAvailable($product),
            default => $available->first(),
        };

        return view('finance.resale.licenses._sell_dialog', [
            'product' => $product,
            'available' => $available,
            'suggested' => $suggested,
            'token' => Str::random(32),
        ] + $this->holderPicker());
    }

    public function sellStore(Request $request): RedirectResponse {
        $data = $request->validate([
            'unit_id' => ['required', 'string', 'max:32'],
            'customer_id' => ['required', 'string', 'max:32'],
            'foreign_customer_id' => ['nullable', 'string', 'max:32'],
            'sold_on' => ['required', 'date_format:Y-m-d'],
            'invoice_reference' => ['nullable', 'string', 'max:80'],
            'token' => ['required', 'string', 'size:32'],
        ]);
        $unit = ResaleLicenseUnit::query()->find(Sqid::decodeOrNumeric(ResaleLicenseUnit::class, $data['unit_id']) ?? 0)
            ?? throw ValidationException::withMessages(['unit_id' => __('resale.license.error.not_available')]);
        [$customer, $foreign] = $this->holder($data);

        $assignment = $this->attempt(fn (): ResaleLicenseAssignment => $this->stock->sell(
            $unit, $customer, $foreign, CarbonImmutable::parse($data['sold_on']), $data['invoice_reference'] ?? null, $data['token'], $this->actor(),
        ));

        return redirect()->route('finance.resale.licenses.batches.show', $unit->batch)
            ->with('success', __('resale.license.flash.sold', ['license' => $assignment->unit->label(), 'customer' => $assignment->holderLabel()]));
    }

    public function reassignCreate(ResaleLicenseAssignment $assignment): View {
        return view('finance.resale.licenses._reassign_dialog', ['assignment' => $assignment->load(['unit.batch', 'customer', 'foreignCustomer'])] + $this->holderPicker());
    }

    public function reassignStore(Request $request, ResaleLicenseAssignment $assignment): RedirectResponse {
        $data = $request->validate([
            'customer_id' => ['required', 'string', 'max:32'],
            'foreign_customer_id' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        [$customer, $foreign] = $this->holder($data);

        $next = $this->attempt(fn (): ResaleLicenseAssignment => $this->stock->reassign($assignment, $customer, $foreign, $data['reason'], $this->actor()));

        return redirect()->route('finance.resale.licenses.batches.show', $assignment->unit->batch)
            ->with('success', __('resale.license.flash.reassigned', ['license' => $assignment->unit->label(), 'customer' => $next->holderLabel()]));
    }

    public function returnCreate(ResaleLicenseAssignment $assignment): View {
        return view('finance.resale.licenses._reason_dialog', [
            'mode' => 'return',
            'action' => route('finance.resale.licenses.assignments.return.store', $assignment),
            'license' => $assignment->unit->label(),
        ]);
    }

    public function returnStore(Request $request, ResaleLicenseAssignment $assignment): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->attempt(fn () => $this->stock->returnSale($assignment, $data['reason'], $this->actor()));

        return redirect()->route('finance.resale.licenses.batches.show', $assignment->unit->batch)
            ->with('success', __('resale.license.flash.returned', ['license' => $assignment->unit->label()]));
    }

    public function blockCreate(ResaleLicenseUnit $unit): View {
        return view('finance.resale.licenses._reason_dialog', [
            'mode' => 'block',
            'action' => route('finance.resale.licenses.units.block.store', $unit),
            'license' => $unit->label(),
        ]);
    }

    public function blockStore(Request $request, ResaleLicenseUnit $unit): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->attempt(fn () => $this->stock->block($unit, $data['reason'], $this->actor()));

        return redirect()->route('finance.resale.licenses.batches.show', $unit->batch)
            ->with('success', __('resale.license.flash.blocked', ['license' => $unit->label()]));
    }

    public function unblockCreate(ResaleLicenseUnit $unit): View {
        return view('finance.resale.licenses._reason_dialog', [
            'mode' => 'unblock',
            'action' => route('finance.resale.licenses.units.unblock.store', $unit),
            'license' => $unit->label(),
            'blockedReason' => $unit->blocked_reason,
        ]);
    }

    public function unblockStore(Request $request, ResaleLicenseUnit $unit): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'confirmed' => ['nullable', 'boolean'],
        ]);
        $this->attempt(fn () => $this->stock->unblock($unit, $data['reason'], (bool) ($data['confirmed'] ?? false), $this->actor()));

        return redirect()->route('finance.resale.licenses.batches.show', $unit->batch)
            ->with('success', __('resale.license.flash.unblocked', ['license' => $unit->label()]));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function attempt(callable $action): mixed {
        try {
            return $action();
        } catch (LicenseStockException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Customer, 1: ForeignCustomer|null}
     */
    private function holder(array $data): array {
        $customer = Customer::query()->find(Sqid::decodeOrNumeric(Customer::class, (string) $data['customer_id']) ?? 0)
            ?? throw ValidationException::withMessages(['customer_id' => __('resale.license.error.customer_foreign')]);
        $foreign = null;
        if (($data['foreign_customer_id'] ?? '') !== '' && $data['foreign_customer_id'] !== null) {
            $foreign = ForeignCustomer::query()->find(Sqid::decodeOrNumeric(ForeignCustomer::class, (string) $data['foreign_customer_id']) ?? 0)
                ?? throw ValidationException::withMessages(['foreign_customer_id' => __('resale.license.error.holder_mismatch')]);
        }

        return [$customer, $foreign];
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
