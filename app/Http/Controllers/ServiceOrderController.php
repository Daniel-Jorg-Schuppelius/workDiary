<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveServiceOrderRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ServiceOrderController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View {
        Gate::authorize('viewAny', ServiceOrder::class);

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $query = ServiceOrder::query()
            ->with(['customer:id,name', 'project:id,name', 'assignedUser:id,name', 'tour:id,tour_date,status'])
            ->whereBetween('scheduled_for', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->orderBy('scheduled_for')
            ->orderBy('time_window_start')
            ->orderBy('id');

        if ($target !== null) {
            $query->where(function ($q) use ($target): void {
                $q->where('assigned_user_id', $target->id)
                    ->orWhereNull('assigned_user_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('customer')) {
            $query->where('customer_id', (int) $request->integer('customer'));
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('service-orders.index', [
            'orders' => $orders,
            'from' => $from,
            'to' => $to,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'statuses' => ServiceOrder::STATUSES,
            'selectedStatus' => $request->query('status'),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', ServiceOrder::class);

        return view('service-orders._form_dialog', [
            'order' => null,
            'date' => $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'users' => $this->loadSelectableUsers(),
            'statuses' => ServiceOrder::STATUSES,
            'priorities' => ServiceOrder::PRIORITIES,
        ]);
    }

    public function store(SaveServiceOrderRequest $request): RedirectResponse {
        Gate::authorize('create', ServiceOrder::class);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();
        $data['organization_id'] = $auth->organization_id;
        $data['created_by'] = $auth->id;
        if (! isset($data['status'])) {
            $data['status'] = ServiceOrder::STATUS_PLANNED;
        }

        $order = ServiceOrder::create($data);

        return redirect()->route('service-orders.index')
            ->with('success', __('Auftrag :title angelegt.', ['title' => $order->title]));
    }

    public function show(ServiceOrder $serviceOrder): View {
        Gate::authorize('view', $serviceOrder);

        $serviceOrder->load(['customer', 'project', 'assignedUser', 'tour']);

        return view('service-orders.show', ['order' => $serviceOrder]);
    }

    public function edit(ServiceOrder $serviceOrder): View {
        Gate::authorize('update', $serviceOrder);

        return view('service-orders._form_dialog', [
            'order' => $serviceOrder,
            'date' => $serviceOrder->scheduled_for?->toDateString(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'users' => $this->loadSelectableUsers(),
            'statuses' => ServiceOrder::STATUSES,
            'priorities' => ServiceOrder::PRIORITIES,
        ]);
    }

    public function update(SaveServiceOrderRequest $request, ServiceOrder $serviceOrder): RedirectResponse {
        Gate::authorize('update', $serviceOrder);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();
        $data['updated_by'] = $auth->id;

        $serviceOrder->fill($data)->save();

        return redirect()->route('service-orders.index')
            ->with('success', __('Auftrag aktualisiert.'));
    }

    public function destroy(ServiceOrder $serviceOrder): RedirectResponse {
        Gate::authorize('delete', $serviceOrder);

        $serviceOrder->delete();

        return redirect()->route('service-orders.index')
            ->with('success', __('Auftrag gelöscht.'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array {
        if ($request->filled('from') && $request->filled('to')) {
            $from = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();
            $to = CarbonImmutable::parse((string) $request->query('to'))->endOfDay();

            return [$from, $to];
        }

        $range = $this->globalDateRange();

        return [$range['from']->startOfDay(), $range['to']->endOfDay()];
    }

    private function resolveTargetUser(Request $request, User $authUser): ?User {
        if (! $request->filled('user')) {
            return $authUser;
        }

        $raw = (string) $request->query('user');
        if ($raw === 'all') {
            if (! $authUser->isAdmin()) {
                throw new AccessDeniedHttpException('Nur Admins dürfen alle Aufträge einsehen.');
            }

            return null;
        }

        $requestedId = (int) $raw;
        if ($requestedId === (int) $authUser->id) {
            return $authUser;
        }

        if (! $authUser->isAdmin()) {
            throw new AccessDeniedHttpException('Nur Admins dürfen fremde Aufträge einsehen.');
        }

        $target = User::query()->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }

    /** @return Collection<int, User> */
    private function loadSelectableUsers(): Collection {
        /** @var Collection<int, User> $users */
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return $users;
    }
}
