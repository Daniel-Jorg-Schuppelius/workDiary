<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Inventory\SerialStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Models\StockSerial;
use App\Services\Inventory\SerialService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Seriennummern-Verwaltung (Feature 047/048, E2): Bestandsübersicht, Geräte-Pass
 * (Echtheits-/Statusprüfung), Sperren/Entsperren/Verschrotten. Modul-Gating über
 * `serials.*` → module.lager.
 */
class SerialController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(private readonly SerialService $serials) {}

    public function index(Request $request): View {
        $this->canView();

        $status = $request->string('status')->toString() ?: 'all';
        $search = $request->string('q')->toString();
        $range = $this->globalDateRange();

        $serials = StockSerial::query()
            ->with(['article', 'variant', 'customer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereLikeEscaped('serial_no', $search))
            ->whereBetween('created_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('serials.index', [
            'serials' => $serials,
            'status' => $status,
            'search' => $search,
            'statuses' => SerialStatus::cases(),
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
        ]);
    }

    public function show(StockSerial $serial): View {
        $this->canView();
        $serial->load(['article', 'variant', 'warehouse', 'customer', 'manufacturingOrder', 'delivery']);

        return view('serials.show', [
            'serial' => $serial,
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
        ]);
    }

    /** Geräte-Pass: Status einer Seriennummer per Eingabe prüfen (Echtheitsprüfung). */
    public function verify(Request $request): View {
        $this->canView();
        $serialNo = $request->string('serial')->toString();
        $serial = $serialNo !== ''
            ? $this->serials->lookup($this->currentOrganization()->id, $serialNo)
            : null;

        return view('serials.verify', [
            'query' => $serialNo,
            'serial' => $serial,
            'searched' => $serialNo !== '',
        ]);
    }

    public function block(Request $request, StockSerial $serial): RedirectResponse {
        $this->canManage();
        $reason = $request->string('reason')->toString() ?: __('inventory.serial.blocked_default');
        $this->serials->block($serial, $reason);

        return back()->with('success', __('inventory.serial.flash.blocked'));
    }

    public function unblock(StockSerial $serial): RedirectResponse {
        $this->canManage();
        $this->serials->unblock($serial);

        return back()->with('success', __('inventory.serial.flash.unblocked'));
    }

    public function scrap(StockSerial $serial): RedirectResponse {
        $this->canManage();
        $this->serials->scrap($serial);

        return back()->with('success', __('inventory.serial.flash.scrapped'));
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);
    }

    private function canManage(): void {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
    }
}
