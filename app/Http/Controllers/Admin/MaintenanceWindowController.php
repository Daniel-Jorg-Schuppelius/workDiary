<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceWindowController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{MaintenanceWindow, Organization};
use App\Services\Operations\MaintenanceWindowService;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wartungsfenster-Verwaltung (MVP-055): planen, ankündigen, starten,
 * verlängern, beenden, Rollback — system- oder organisationsweit.
 * Jede Aktion ist auditiert (Auditable am Model).
 */
class MaintenanceWindowController extends Controller {
    public function __construct(private readonly MaintenanceWindowService $service) {}

    public function index(): View {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        return view('admin.maintenance-windows.index', [
            'windows' => MaintenanceWindow::query()
                ->orderByDesc('starts_at')
                ->paginate((int) Setting::get('pagination.notifications', 25)),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        return view('admin.maintenance-windows._form_dialog');
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        $validated = $request->validate([
            'scope' => ['required', Rule::in([MaintenanceWindow::SCOPE_SYSTEM, MaintenanceWindow::SCOPE_ORGANIZATION])],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'announce_from' => ['nullable', 'date', 'before:starts_at'],
            'message' => ['nullable', 'string', 'max:300'],
            'read_only' => ['nullable', 'boolean'],
            'block_ingest' => ['nullable', 'boolean'],
        ]);

        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $this->service->plan([
            'scope' => $validated['scope'],
            'organization_id' => $validated['scope'] === MaintenanceWindow::SCOPE_ORGANIZATION && $organization instanceof Organization
                ? $organization->id
                : null,
            'starts_at' => CarbonImmutable::parse($validated['starts_at']),
            'ends_at' => CarbonImmutable::parse($validated['ends_at']),
            'announce_from' => isset($validated['announce_from']) ? CarbonImmutable::parse($validated['announce_from']) : null,
            'message' => $validated['message'] ?? null,
            'read_only' => (bool) ($validated['read_only'] ?? false),
            'block_ingest' => (bool) ($validated['block_ingest'] ?? false),
        ], $request->user()?->id);

        return redirect()->route('admin.maintenance-windows.index')
            ->with('status', __('maintenance.window.flash.planned'));
    }

    public function transition(Request $request, MaintenanceWindow $maintenanceWindow, string $action): RedirectResponse {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        try {
            match ($action) {
                'announce' => $this->service->announce($maintenanceWindow),
                'start' => $this->service->start($maintenanceWindow),
                'complete' => $this->service->complete($maintenanceWindow),
                'extend' => $this->service->extend(
                    $maintenanceWindow,
                    CarbonImmutable::parse((string) $request->validate(['ends_at' => ['required', 'date']])['ends_at']),
                ),
                'rollback' => $this->service->rollback($maintenanceWindow, $request->input('notes')),
                'cancel' => $this->service->cancel($maintenanceWindow),
                default => abort(404),
            };
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.maintenance-windows.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.maintenance-windows.index')
            ->with('status', __('maintenance.window.flash.' . $action));
    }
}
