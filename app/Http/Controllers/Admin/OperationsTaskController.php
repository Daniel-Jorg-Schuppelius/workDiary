<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTaskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus, OperationsTaskType};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{OperationsTask, Organization, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-Aufgabencenter (Feature 041, MVP-058): priorisierte Betriebs-
 * aufgaben mit erledigen/zurückstellen/delegieren/ignorieren.
 * Sichtbarkeit ist org-gebunden; installationsweite Aufgaben liegen in
 * der Betreiber-Org (is_system-Badge). Alle Statuswechsel sind auditiert
 * (Auditable am Model).
 */
class OperationsTaskController extends Controller {
    public function index(Request $request): View {
        Gate::authorize(Permission::PlatformOperationsView->value);

        $status = OperationsTaskStatus::tryFrom((string) $request->query('status', ''));
        $type = OperationsTaskType::tryFrom((string) $request->query('type', ''));
        $severity = OperationsTaskSeverity::tryFrom((string) $request->query('severity', ''));

        $query = OperationsTask::query()
            ->where('organization_id', $this->currentOrganizationId())
            ->when($status !== null, fn($q) => $q->where('status', $status?->value))
            ->when($status === null, fn($q) => $q->active())
            ->when($type !== null, fn($q) => $q->where('type', $type?->value))
            ->when($severity !== null, fn($q) => $q->where('severity', $severity?->value))
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->orderByDesc('last_seen_at');

        return view('admin.operations.index', [
            'tasks' => $query->paginate((int) Setting::get('pagination.notifications', 25))->withQueryString(),
            'statusFilter' => $status,
            'typeFilter' => $type,
            'severityFilter' => $severity,
            'canManage' => $request->user()?->can(Permission::PlatformOperationsManage->value) ?? false,
        ]);
    }

    public function done(Request $request, OperationsTask $operationsTask): RedirectResponse {
        $this->authorizeManage($operationsTask);
        $this->act($operationsTask, $request, [
            'status' => OperationsTaskStatus::Done,
            'resolved_at' => CarbonImmutable::now(),
        ]);

        return back()->with('status', __('operations.flash.done'));
    }

    public function reopen(Request $request, OperationsTask $operationsTask): RedirectResponse {
        $this->authorizeManage($operationsTask);
        $this->act($operationsTask, $request, [
            'status' => OperationsTaskStatus::Open,
            'resolved_at' => null,
            'snoozed_until' => null,
            'note' => null,
        ]);

        return back()->with('status', __('operations.flash.reopened'));
    }

    public function snooze(Request $request, OperationsTask $operationsTask): RedirectResponse {
        $this->authorizeManage($operationsTask);
        $days = (int) Setting::get('operations.snooze_days', 7);
        $until = CarbonImmutable::now()->addDays(max(1, $days));
        $this->act($operationsTask, $request, [
            'status' => OperationsTaskStatus::Snoozed,
            'snoozed_until' => $until,
        ]);

        return back()->with('status', __('operations.flash.snoozed', ['date' => $until->format('d.m.Y')]));
    }

    public function delegate(Request $request, OperationsTask $operationsTask): RedirectResponse {
        $this->authorizeManage($operationsTask);

        // Sqid → ID vor der Validierung (Formulare transportieren nie rohe IDs).
        $request->merge([
            'assigned_user_id' => \App\Support\Sqid::decode(User::class, (string) $request->input('assigned_user')),
        ]);
        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
        ]);

        $this->act($operationsTask, $request, [
            'status' => OperationsTaskStatus::Delegated,
            'assigned_user_id' => (int) $validated['assigned_user_id'],
        ]);

        return back()->with('status', __('operations.flash.delegated'));
    }

    public function ignore(Request $request, OperationsTask $operationsTask): RedirectResponse {
        $this->authorizeManage($operationsTask);
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ]);

        $this->act($operationsTask, $request, [
            'status' => OperationsTaskStatus::Ignored,
            'note' => $validated['note'],
        ]);

        return back()->with('status', __('operations.flash.ignored'));
    }

    /** @param array<string, mixed> $attributes */
    private function act(OperationsTask $task, Request $request, array $attributes): void {
        $task->update($attributes + [
            'acted_by_user_id' => $request->user()?->id,
            'acted_at' => CarbonImmutable::now(),
        ]);
    }

    private function authorizeManage(OperationsTask $task): void {
        Gate::authorize(Permission::PlatformOperationsManage->value);
        abort_unless((int) $task->organization_id === $this->currentOrganizationId(), 404);
    }

    private function currentOrganizationId(): int {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return $org instanceof Organization ? (int) $org->id : 0;
    }
}
