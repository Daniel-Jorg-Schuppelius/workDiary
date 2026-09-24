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
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\Asset\MaintenanceWindow;
use App\Models\Platform\Organization;
use App\Services\Operations\MaintenanceWindowService;
use App\Support\{ErrorText, Setting, Tz};
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
    use RequiresPlatformOperator;

    public function __construct(private readonly MaintenanceWindowService $service) {}

    public function index(): View {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        // MaintenanceWindow trägt keinen OrganizationScope: ohne Filter listete
        // die Seite die Fenster **aller** Mandanten (S-02). Der Betreiber
        // braucht diese Sicht, ein Org-Admin nur seine eigene.
        $query = MaintenanceWindow::query()->orderByDesc('starts_at');

        if (! $this->isPlatformOperator()) {
            $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
            abort_unless($organization instanceof Organization, 403);

            $query->where('scope', MaintenanceWindow::SCOPE_ORGANIZATION)
                ->where('organization_id', $organization->id);
        }

        return view('admin.maintenance-windows.index', [
            'windows' => $query->paginate((int) Setting::get('pagination.notifications', 25)),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        // System-Fenster sind Betreiber-Sache (store prüft das) — Org-Admins bekamen sie vorausgewählt und landeten auf 403.
        return view('admin.maintenance-windows._form_dialog', ['systemScope' => $this->isPlatformOperator()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        $validated = $request->validate([
            'scope' => ['required', Rule::in([MaintenanceWindow::SCOPE_SYSTEM, MaintenanceWindow::SCOPE_ORGANIZATION])],
            // Eingabe ist Ortszeit: „nach jetzt“ gegen die lokale Uhr prüfen, gespeichert wird UTC (MVP-823).
            'starts_at' => ['required', 'date', 'after:' . Tz::now()->toDateTimeString(), new \App\Rules\TimestampRange()],
            'ends_at' => ['required', 'date', 'after:starts_at', new \App\Rules\TimestampRange()],
            'announce_from' => ['nullable', 'date', 'before:starts_at', new \App\Rules\TimestampRange()],
            'message' => ['nullable', 'string', 'max:300'],
            'read_only' => ['nullable', 'boolean'],
            'block_ingest' => ['nullable', 'boolean'],
        ]);

        // Ein System-Fenster sperrt ALLE Mandanten (read_only/block_ingest) —
        // das ist eine Betreiber-Entscheidung, keine Mandanten-Einstellung
        // (Sicherheitsscan 2026-08-23, S-02).
        $this->assertMayUseSystemScope($validated['scope']);

        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $this->service->plan([
            'scope' => $validated['scope'],
            'organization_id' => $validated['scope'] === MaintenanceWindow::SCOPE_ORGANIZATION && $organization instanceof Organization
                ? $organization->id
                : null,
            'starts_at' => Tz::parse($validated['starts_at']),
            'ends_at' => Tz::parse($validated['ends_at']),
            'announce_from' => isset($validated['announce_from']) ? Tz::parse($validated['announce_from']) : null,
            'message' => $validated['message'] ?? null,
            'read_only' => (bool) ($validated['read_only'] ?? false),
            'block_ingest' => (bool) ($validated['block_ingest'] ?? false),
        ], $request->user()?->id);

        return redirect()->route('admin.maintenance-windows.index')
            ->with('status', __('maintenance.window.flash.planned'));
    }

    public function transition(Request $request, MaintenanceWindow $maintenanceWindow, string $action): RedirectResponse {
        Gate::authorize(Permission::PlatformOperationsManage->value);

        // MaintenanceWindow trägt keinen OrganizationScope: ohne diese Prüfung
        // ließe sich jedes fremde Fenster über seine Sqid starten oder
        // abbrechen (S-02).
        $this->assertMayTouch($maintenanceWindow);

        try {
            match ($action) {
                'announce' => $this->service->announce($maintenanceWindow),
                'start' => $this->service->start($maintenanceWindow),
                'complete' => $this->service->complete($maintenanceWindow),
                'extend' => $this->service->extend(
                    $maintenanceWindow,
                    Tz::parse((string) $request->validate(['ends_at' => ['required', 'date', new \App\Rules\TimestampRange()]])['ends_at']),
                ),
                'rollback' => $this->service->rollback($maintenanceWindow, $request->input('notes')),
                'cancel' => $this->service->cancel($maintenanceWindow),
                default => abort(404),
            };
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.maintenance-windows.index')->with('error', ErrorText::for($e));
        }

        return redirect()->route('admin.maintenance-windows.index')
            ->with('status', __('maintenance.window.flash.' . $action));
    }

    /** System-Fenster darf nur der Plattform-Betreiber planen. */
    private function assertMayUseSystemScope(string $scope): void {
        if ($scope !== MaintenanceWindow::SCOPE_SYSTEM) {
            return;
        }

        $this->assertPlatformOperator();
    }

    /** Fremde Fenster (und System-Fenster) bleiben dem Betreiber vorbehalten. */
    private function assertMayTouch(MaintenanceWindow $window): void {
        if ($this->isPlatformOperator()) {
            return;
        }

        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        abort_unless(
            $window->scope === MaintenanceWindow::SCOPE_ORGANIZATION
            && $organization instanceof Organization
            && (int) $window->organization_id === (int) $organization->id,
            403
        );
    }

}
