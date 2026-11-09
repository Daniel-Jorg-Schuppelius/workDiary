<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\{ListsLegacySupportOrders, RequiresLegacyAdmin};
use App\Legacy\Http\Requests\SaveLegacySupportOrderRequest;
use App\Legacy\Models\{LegacySupportCustomer, LegacySupportMailSetting, LegacySupportOrder, LegacyUser};
use App\Legacy\Support\{LegacyClock, LegacyRoleResolver};
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Auftragsübersicht des Support-Portals für das Büro: im Altportal die
 * Masken der Bürokonten (Übersicht, Eintragung, Termin/Löschen, Kundenauswahl,
 * Auftrag anlegen).
 */
class LegacySupportOrderController extends Controller {
    use ListsLegacySupportOrders;
    use RequiresLegacyAdmin;

    private const int PER_PAGE = 50;

    public function index(Request $request): View {
        $this->ensureAdmin();

        $query = LegacySupportOrder::query()->with('customer:id,kunde')->where('status', '>', 0);
        $list = $this->applySupportOrderFilters($request, $query);

        $statusCounts = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $counts = ['all' => (int) $statusCounts->sum(), 'overdue' => (clone $query)->overdue()->count()];
        foreach (LegacySupportOrder::STATUSES as $status) {
            $counts[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        $statusFilter = (string) $request->query('status', '');
        if ($statusFilter === 'overdue') {
            $query->overdue();
        } elseif (ctype_digit($statusFilter) && in_array((int) $statusFilter, LegacySupportOrder::STATUSES, true)) {
            $query->where('status', (int) $statusFilter);
        } else {
            $statusFilter = '';
        }

        [$sort, $dir] = $this->applySupportOrderSort($request, $query);

        return view('legacy.support.orders.index', [
            ...$list,
            'orders' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'dir' => $dir,
            'customers' => $this->supportCustomersForSelect(),
            'mailSetting' => LegacySupportMailSetting::current(),
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function create(Request $request): View {
        $this->ensureAdmin();

        return view('legacy.support.orders._form_dialog', [
            'order' => null,
            'isEdit' => false,
            'customers' => $this->supportCustomersForSelect(),
            'selectedCustomer' => (string) $request->query('customer', ''),
            'orderer' => $this->officeOrderer(),
            'mechanics' => [],
        ]);
    }

    public function store(SaveLegacySupportOrderRequest $request): RedirectResponse {
        $this->ensureAdmin();
        $data = $request->validated();

        $order = LegacySupportOrder::query()->create([
            'kunde' => (int) $data['kunde'],
            'datum' => LegacyClock::nowString(),
            'kname' => $this->officeOrderer(),
            'telefon' => (string) ($data['telefon'] ?? ''),
            'objekt' => (string) ($data['objekt'] ?? ''),
            'auftrag' => (string) $data['auftrag'],
            'termin' => LegacySupportOrder::NO_APPOINTMENT,
            'wer' => '',
            'antwort' => '',
            'intern' => '',
            'dauer' => '',
            'status' => LegacySupportOrder::STATUS_NEW,
        ]);

        return redirect()->route('legacy.support.orders.index')
            ->with('success', __('legacy_support.flash.order_created', ['number' => $order->id]));
    }

    public function show(LegacySupportOrder $order): View {
        $this->ensureAdmin();
        $order->load('customer:id,kunde');

        return view('legacy.support.orders._show_dialog', [
            'order' => $order,
            'archived' => false,
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function edit(LegacySupportOrder $order): View {
        $this->ensureAdmin();
        $order->load('customer:id,kunde');

        $mechanics = LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->pluck('uname')->map(fn($name) => (string) $name)->all();
        if ($order->wer !== '' && ! in_array($order->wer, $mechanics, true)) {
            $mechanics[] = $order->wer;
        }

        return view('legacy.support.orders._form_dialog', [
            'order' => $order,
            'isEdit' => true,
            'customers' => $this->supportCustomersForSelect(),
            'selectedCustomer' => Sqid::encode(LegacySupportCustomer::class, $order->kunde),
            'orderer' => $order->kname,
            'mechanics' => $mechanics,
        ]);
    }

    public function update(SaveLegacySupportOrderRequest $request, LegacySupportOrder $order): RedirectResponse {
        $this->ensureAdmin();
        $data = $request->validated();

        $previous = $order->appointmentAt();
        $appointment = filled($data['termin'] ?? null) ? Carbon::parse((string) $data['termin'])->startOfMinute() : null;
        $status = (int) $data['status'];

        // Wie die Eintragung des Altportals: ein neuer Termin setzt „Termin festgelegt".
        // Erledigt und archiviert bleiben, wenn das Büro sie zugleich wählt.
        $appointmentChanged = $appointment !== null && ($previous === null || ! $previous->equalTo($appointment));
        if ($appointmentChanged && $status < LegacySupportOrder::STATUS_SCHEDULED) {
            $status = LegacySupportOrder::STATUS_SCHEDULED;
        }

        $keepRawAppointment = $appointment === null && $previous === null && filled($order->termin);

        $order->update([
            'kunde' => (int) $data['kunde'],
            'kname' => $order->isOfficeOrder() ? $order->kname : (string) ($data['kname'] ?? ''),
            'telefon' => (string) ($data['telefon'] ?? ''),
            'objekt' => (string) ($data['objekt'] ?? ''),
            'auftrag' => (string) $data['auftrag'],
            'termin' => $appointment?->format('Y-m-d H:i:s') ?? ($keepRawAppointment ? (string) $order->termin : LegacySupportOrder::NO_APPOINTMENT),
            'wer' => (string) ($data['wer'] ?? ''),
            'antwort' => (string) ($data['antwort'] ?? ''),
            'intern' => (string) ($data['intern'] ?? ''),
            'dauer' => (string) ($data['dauer'] ?? ''),
            'status' => $status,
        ]);

        return redirect()->route('legacy.support.orders.index')
            ->with('success', __('legacy_support.flash.order_updated', ['number' => $order->id]));
    }

    public function destroy(LegacySupportOrder $order): RedirectResponse {
        $this->ensureAdmin();

        $number = $order->id;
        $order->delete();

        return redirect()->route('legacy.support.orders.index')
            ->with('success', __('legacy_support.flash.order_deleted', ['number' => $number]));
    }

    /** Auftraggeber eines Bürovorgangs: die Kennung des Bürokontos, sonst der eigene Name. */
    private function officeOrderer(): string {
        $user = Auth::user();
        if (! $user instanceof User) {
            return '';
        }

        return LegacySupportOrder::OFFICE_ORDERERS[LegacyRoleResolver::resolveLegacyUserId($user)]
            ?? Str::substr((string) $user->name, 0, 30);
    }
}
